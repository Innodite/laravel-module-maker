<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Innodite\LaravelModuleMaker\Support\RouteMarkers;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SubFeaturePermissions;

/**
 * Genera el archivo de rutas del módulo respetando la convención de contextos.
 *
 * Comportamientos según contexto:
 *
 *   central       → routes/web.php, envuelto en foreach central_domains,
 *                   prefijo 'central-{functionality}', middleware central-permission
 *
 *   shared        → routes/web.php, prefijo 'shared-{functionality}',
 *                   middleware central-permission
 *
 *   tenant_shared → routes/tenant.php, genera un bloque por CADA tenant específico
 *                   definido en contexts.json (generates_routes_for_all_tenants = false),
 *                   el controlador apunta al TenantShared
 *
 *   tenant_alpha  → routes/tenant.php, un solo bloque para ese tenant
 *
 * Si el archivo ya existe, agrega la nueva sección sin sobreescribir las existentes.
 * Usa marcadores de comentario para saber dónde insertar.
 */
class RouteGenerator extends AbstractComponentGenerator
{
    /**
     * Nombre base del modelo para derivar el nombre del controlador.
     *
     * @var string
     */
    protected string $modelName;

    /**
     * @param  string  $moduleName       Nombre del módulo
     * @param  string  $modulePath       Ruta absoluta al directorio del módulo
     * @param  bool    $isClean          true = stubs clean, false = stubs dynamic
     * @param  string  $modelName        Nombre del modelo en StudlyCase
     * @param  array   $componentConfig  Configuración (debe incluir 'context' y 'functionality')
     */
    public function __construct(
        string $moduleName,
        string $modulePath,
        bool $isClean,
        string $modelName,
        array $componentConfig = []
    ) {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->modelName = Str::studly($modelName);
    }

    /**
     * Genera o actualiza el archivo de rutas según el contexto activo.
     *
     * @return void
     */
    public function generate(): void
    {
        // ── Quién decide la forma de las rutas es el MODO ─────────────────────
        //
        // Antes lo decidía la **ausencia de una clave**: sin `context` en la configuración, las
        // rutas salían por el camino simple. Eso tenía dos caras, y las dos malas.
        //
        // Hacia un lado convertía la aplicación única en un caso degradado —ahí el contexto está
        // vacío siempre, así que un proyecto sin tenants caía en el «fallback»— cuando es un modo
        // de primera clase. Es la misma corrección que ya se hizo en `RequestGenerator`, y por el
        // mismo motivo.
        //
        // Hacia el otro, y peor: en un proyecto **multitenant** cuyo componente no declarase
        // contexto, las rutas salían también por ahí. El resultado no era un error, era un archivo
        // plausible y equivocado: sin el `foreach` de dominios centrales, en `web.php` en vez de
        // `tenant.php`, y exigiendo `tenant-permission:tenant_…` porque eso es lo que responde el
        // modo cuando no se le dice el contexto. Rutas que protegen algo distinto de lo que dicen,
        // y ni una señal de que algo fuera mal.
        if (! $this->mode()->hasContextAxis()) {
            $this->generateSingleAppRoutes();
            return;
        }

        $context = $this->getContext();

        if (empty($context)) {
            // No se escribe nada, y se dice por qué. Escribir aquí el camino simple sería el
            // «éxito que no ocurrió» de A15: un archivo generado que hay que rehacer entero, y que
            // nadie va a mirar porque el comando terminó en verde.
            $this->error(
                "⛔ No se generaron las rutas de {$this->moduleName}: el proyecto es "
                . "«{$this->mode()->label()}» y este componente no declara contexto."
            );
            $this->warn(
                '   · FIX: declara `context` en la configuración del componente. Sin él no se '
                . 'puede saber qué dominio sirve la ruta ni con qué permiso protegerla, y lo que '
                . 'se escriba será plausible y equivocado.'
            );

            return;
        }

        $routesDir  = $this->getComponentBasePath() . '/Routes';
        $contextKey = $this->componentConfig['context'] ?? '';
        $this->ensureDirectoryExists($routesDir);

        // tenant (específico) → siempre un bloque para ese tenant
        if ($contextKey === 'tenant') {
            $this->generateSingleTenantRoutes($routesDir, $context);
            return;
        }

        // tenant_shared → genera un bloque por CADA tenant del proyecto
        if ($contextKey === 'tenant_shared') {
            $this->generateTenantSharedRoutes($routesDir);
            return;
        }

        // El resto —`central` y `shared`— escribe donde su contexto declare.
        $this->generateContextRoutes($routesDir, $context, $contextKey);
    }

    // ─── Generadores por tipo de contexto ────────────────────────────────────

    /**
     * Las rutas de un contexto, **en los archivos que ese contexto declara**.
     *
     * Antes este método era `generateSharedRoutes()` y escribía **siempre en los dos** —`web.php` y
     * `tenant.php`—, porque estaba pensado para `shared`, que sí vive en los dos lados. Pero era
     * también donde acababa `central`, y ahí el resultado era grave: el bloque `central-…`, con su
     * `central-permission:central_…`, quedaba dentro del archivo de rutas **que se sirve a los
     * tenants**. Rutas de la aplicación central publicadas en el dominio de cada cliente.
     *
     * Nadie lo veía porque el archivo es correcto: parsea, las rutas existen y sus permisos son los
     * que dicen ser. Solo está en el sitio equivocado.
     *
     * **El catálogo ya tenía la respuesta y no se leía**: cada contexto declara su `route_file`.
     * `central` dice `web.php`; `tenant_shared` y los tenants dicen `tenant.php`; `shared` **no
     * declara ninguno**, y esa ausencia es su forma de decir que vive en los dos — es el único que
     * de verdad es dual.
     *
     * @param  string  $routesDir   Ruta al directorio de rutas del módulo
     * @param  array   $context     Configuración del contexto ya resuelta
     * @param  string  $contextKey  Clave del contexto, que decide el marcador
     * @return void
     */
    private function generateContextRoutes(string $routesDir, array $context, string $contextKey): void
    {
        $middleware = $context['route_middleware'] ?? [];

        // Un `route_file` declarado significa «solo aquí». Sin él, el contexto vive en los dos.
        $archivos = isset($context['route_file'])
            ? [(string) $context['route_file']]
            : ['web.php', 'tenant.php'];

        foreach ($archivos as $archivo) {
            $this->escribirSeccion($routesDir, $context, $contextKey, $archivo, $middleware);
        }
    }

    /**
     * Escribe —o amplía— la sección de este contexto en uno de sus archivos de rutas.
     *
     * Los prefijos se resuelven por archivo porque `shared` los necesita distintos en cada lado: sus
     * dos bloques declaran las mismas acciones, y con el mismo nombre de ruta el segundo pisaría al
     * primero al registrarse.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, string>    $middleware
     */
    private function escribirSeccion(
        string $routesDir,
        array $context,
        string $contextKey,
        string $archivo,
        array $middleware
    ): void {
        $functionality   = $this->getFunctionality();
        $controllerClass = $this->buildControllerClass();
        $controllerFqcn  = $this->buildControllerNamespace() . '\\' . $controllerClass;
        $permPrefix      = $this->resolvePermissionPrefix($context, $contextKey ?: null);
        $permMiddleware  = $this->resolvePermissionMiddleware($context, $contextKey ?: null);

        $esTenant = $archivo === 'tenant.php';
        $prefijo  = ($esTenant ? $context['tenant_route_prefix'] ?? null : $context['web_route_prefix'] ?? null)
            ?? $context['route_prefix'] ?? 'shared';
        $nombre   = ($esTenant ? $context['tenant_route_name'] ?? null : $context['web_route_name'] ?? null)
            ?? $context['route_name'] ?? 'shared.';

        $bloque = $this->buildRouteBlock(
            routePrefix:     $prefijo . '-' . $functionality,
            routeName:       $nombre . $functionality . '.',
            controllerClass: $controllerClass,
            permMiddleware:  $permMiddleware,
            permPrefix:      $permPrefix,
            permKey:         SubFeaturePermissions::key($functionality),
            indent:          '    '
        );

        // El marcador sale de RouteMarkers, que es de donde lo lee también el inyector. Antes cada
        // lado lo componía por su cuenta y no coincidían.
        $marcador = RouteMarkers::key($contextKey, $archivo, (string) ($context['id'] ?? ''));

        $contenido = $this->buildSharedFileContent($controllerFqcn, $bloque, $middleware, $marcador);
        $this->writeOrAppend("{$routesDir}/{$archivo}", $contenido, $marcador, $bloque, $controllerFqcn);
    }

    /**
     * Construye el contenido de archivo de rutas para contexto Shared.
     * Si route_middleware está vacío, omite el ->middleware() (hereda del grupo padre).
     *
     * @param  string  $controllerFqcn  FQCN del controlador
     * @param  string  $block           Bloque de rutas CRUD
     * @param  array   $middleware      Array de middlewares (vacío = sin wrapper)
     * @param  string  $markerKey       Clave del marcador sin llaves
     * @return string
     */
    private function buildSharedFileContent(string $controllerFqcn, string $block, array $middleware, string $markerKey): string
    {
        $marker = "// {{{$markerKey}}}";

        if (empty($middleware)) {
            // Sin middleware wrapper — hereda seguridad del grupo padre
            return <<<PHP
            <?php

            declare(strict_types=1);

            use Illuminate\Support\Facades\Route;
            use {$controllerFqcn};

            {$block}
                {$marker}
            PHP;
        }

        $mw = $this->buildMiddlewareArray($middleware);
        return <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\Support\Facades\Route;
        use {$controllerFqcn};

        Route::middleware({$mw})->group(function () {

        {$block}
            {$marker}
        });
        PHP;
    }

    /**
     * Genera un bloque de rutas para un tenant específico.
     *
     * @param  string  $routesDir  Ruta al directorio de rutas del módulo
     * @param  array   $context    Configuración del contexto del tenant
     * @return void
     */
    private function generateSingleTenantRoutes(string $routesDir, array $context): void
    {
        $classPrefix     = $context['class_prefix'] ?? 'TENANT';
        $markerKey       = strtoupper(Str::snake($classPrefix));
        $functionality   = $this->getFunctionality();
        $controllerClass = $this->buildControllerClass();
        $controllerFqcn  = $this->buildControllerNamespace() . '\\' . $controllerClass;
        $permPrefix      = $this->resolvePermissionPrefix($context, $this->componentConfig['context'] ?? null, $context['id'] ?? null);
        $permMiddleware  = $this->resolvePermissionMiddleware($context, $this->componentConfig['context'] ?? null);
        $permKey         = SubFeaturePermissions::key($functionality);
        $middleware      = $this->buildMiddlewareArray($context['route_middleware'] ?? []);
        $label           = $context['id'] ?? $context['label'] ?? $classPrefix;
        $separator       = str_repeat('─', 74);

        $block = $this->buildRouteBlock(
            routePrefix:     $context['route_prefix'] . '-' . $functionality,
            routeName:       $context['route_name'] . $functionality . '.',
            controllerClass: $controllerClass,
            permMiddleware:  $permMiddleware,
            permPrefix:      $permPrefix,
            permKey:         $permKey,
            indent:          '    '
        );

        $section = <<<PHP
        // {$separator}
        // {$label} — {$this->moduleName}
        // {$separator}
        Route::middleware({$middleware})->group(function () {

        {$block}
            // {{{$markerKey}_END}}
        });
        PHP;

        $this->writeOrAppend("{$routesDir}/tenant.php", $section, "{$markerKey}_END", $block, $controllerFqcn);
    }

    /**
     * Genera bloques de rutas para TODOS los tenants específicos,
     * apuntando al controlador TenantShared.
     *
     * @param  string  $routesDir  Ruta al directorio de rutas del módulo
     * @return void
     */
    private function generateTenantSharedRoutes(string $routesDir): void
    {
        $tenants = ContextResolver::allTenants();

        foreach ($tenants as $tenantItem) {
            // Sustituir temporalmente el context_id para generar cada bloque con los datos del tenant
            $this->componentConfig['context_id'] = $tenantItem['id'];
            $this->resolveContextCache($tenantItem);
            $this->generateSingleTenantRoutes($routesDir, $tenantItem);
        }

        // Restaurar el contexto original
        $this->componentConfig['context_id'] = null;
        $this->resolveContextCache(null);
    }

    // ─── De dónde salen el prefijo y el middleware del permiso ───────────────
    //
    // Los dos resolvedores **subieron al generador base** en la fase 3: los necesita también el
    // generador de seeders, que es quien crea los permisos que estas rutas exigen. Tenerlos aquí,
    // privados, era garantizar que el día que uno cambiara el otro seguiría emitiendo el nombre
    // viejo — los dos lados de la misma pareja calculando por separado.

    // ─── Helpers de construcción de rutas ────────────────────────────────────

    /**
     * Construye el bloque de rutas CRUD estándar para una funcionalidad.
     *
    * @param  string  $routePrefix     Prefijo de URL (ej: 'tenant-alpha-users')
    * @param  string  $routeName       Prefijo de nombre (ej: 'tenant-alpha.users.')
     * @param  string  $controllerClass Nombre corto de la clase del controlador (sin namespace)
     * @param  string  $permMiddleware  Middleware de permisos ('tenant-permission' o 'central-permission')
    * @param  string  $permPrefix      Prefijo del permiso (ej: 'tenant_alpha')
     * @param  string  $permKey         Clave de la funcionalidad en snake_case (ej: 'users')
     * @param  string  $indent          Indentación del bloque
     * @return string
     */
    private function buildRouteBlock(
        string $routePrefix,
        string $routeName,
        string $controllerClass,
        string $permMiddleware,
        string $permPrefix,
        string $permKey,
        string $indent = '    '
    ): string {
        $i  = $indent;
        $i2 = $indent . '    ';

        // Las rutas y su permiso salen de RoutePermissions, que es también de donde los lee el
        // PermissionsSeeder. Escribirlas aquí a mano las convertiría en la mitad de un par que puede
        // dejar de coincidir: la ruta exigiría un permiso que el seeder no crea, y la pantalla daría
        // 403 para todo el mundo.
        $rutas = [];

        foreach (SubFeaturePermissions::routes($permPrefix, $permKey) as $ruta) {
            $metodo = strtolower($ruta['verb']);

            $rutas[] = <<<PHP
            {$i2}// {$ruta['comment']}
            {$i2}Route::{$metodo}('{$ruta['uri']}', [{$controllerClass}::class, '{$ruta['action']}'])
            {$i2}    ->name('{$ruta['route']}')
            {$i2}    ->middleware('{$permMiddleware}:{$ruta['permission']}');
            PHP;
        }

        $bloque = implode("\n\n", $rutas);

        return <<<PHP
        {$i}Route::prefix('{$routePrefix}')
        {$i}    ->name('{$routeName}')
        {$i}    ->group(function () {
        {$bloque}
        {$i}});
        PHP;
    }

    /**
    * Retorna solo el nombre corto de la clase del controlador (sin namespace).
    * Ej: 'TenantAlphaUserController'
     *
     * @return string
     */
    private function buildControllerClass(): string
    {
        return $this->prefixClass("{$this->modelName}Controller");
    }

    /**
    * Retorna el namespace completo (FQCN) del controlador para el import use.
    * Ej: 'Modules\Products\Http\Controllers\Tenant\Alpha\TenantAlphaUserController'
     *
     * @return string
     */
    private function buildControllerNamespace(): string
    {
        return $this->buildNamespace('Http\\Controllers');
    }

    /**
     * Construye el array de middleware como string PHP para el archivo de rutas.
     * Ej: ['web', 'auth'] → "['web', 'auth']"
     *
     * @param  array  $middleware  Lista de middleware
     * @return string
     */
    private function buildMiddlewareArray(array $middleware): string
    {
        $items = array_map(fn ($m) => "    '{$m}'", $middleware);
        return "[\n" . implode(",\n", $items) . ",\n]";
    }

    /**
     * Escribe el archivo de rutas o agrega una nueva sección si el archivo ya existe.
     * Busca el marcador y agrega el nuevo bloque antes de él.
     * Si el marcador no está en el archivo, agrega la sección completa al final.
     * Cuando el archivo ya existe, agrega el import `use` si aún no está presente.
     *
     * @param  string  $filePath       Ruta absoluta al archivo de rutas
     * @param  string  $fullContent    Contenido completo para archivo nuevo
     * @param  string  $markerKey      Clave del marcador sin llaves (ej: 'CENTRAL_END')
     * @param  string  $newBlock       Bloque de rutas a insertar
     * @param  string  $controllerFqcn FQCN del controlador para el import use
     * @return void
     */
    private function writeOrAppend(
        string $filePath,
        string $fullContent,
        string $markerKey,
        string $newBlock,
        string $controllerFqcn = ''
    ): void {
        $marker = "// {{{$markerKey}}}";

        if (! file_exists($filePath)) {
            file_put_contents($filePath, $fullContent);
            $this->info("✅ Archivo de rutas creado: " . basename(dirname($filePath, 2)) . '/Routes/' . basename($filePath));
            return;
        }

        $existing = file_get_contents($filePath);

        // Añadir el import use si el FQCN está definido y no está ya en el archivo
        if ($controllerFqcn !== '' && ! str_contains($existing, "use {$controllerFqcn};")) {
            // Insertar después del último `use ...;` existente
            if (preg_match('/^(use [^;]+;)(?!.*^use [^;]+;)/ms', $existing)) {
                $existing = preg_replace(
                    '/(use [^;]+;)(?=(?:(?!use [^;]+;)[\s\S])*$)/',
                    "$1\nuse {$controllerFqcn};",
                    $existing,
                    1
                );
            }
        }

        if (str_contains($existing, $marker)) {
            $updated = str_replace($marker, $newBlock . PHP_EOL . '    ' . $marker, $existing);
            file_put_contents($filePath, $updated);
            $this->info("✅ Rutas agregadas en sección existente: " . basename($filePath));
        } else {
            file_put_contents($filePath, $existing . PHP_EOL . PHP_EOL . $fullContent);
            $this->info("✅ Nueva sección de rutas creada en: " . basename($filePath));
        }
    }

    /**
     * Las rutas de una aplicación **sin eje de contexto**: el modo `single-app`.
     *
     * Se llamaba `generateLegacy()`, y el nombre describía lo que este camino **fue**, no lo que
     * es. No es un fallback ni un resto de la v3: es la forma que tienen las rutas cuando el
     * proyecto no tiene tenants que separar, que es un modo de primera clase de los tres. Un
     * método que se llama «legacy» se lee como algo a punto de retirarse, y nadie lo mantiene.
     *
     * Todo lo que lo diferencia del camino con contexto es lo que **no** hay: sin prefijo de
     * contexto en la URL, sin `foreach` de dominios, sin envoltorio de middleware de tenancy. Las
     * seis rutas y sus seis permisos son exactamente los mismos, salidos del mismo sitio.
     *
     * Dos cosas se arreglaron aquí antes, y las dos las destapó el arnés al poner el archivo
     * generado contra el árbol:
     *
     *   1. El import se armaba dentro del stub —`Modules\{Módulo}\Http\Controllers\{Clase}`—
     *      **sin la carpeta de la subfuncionalidad**, que es carpeta en todas las capas desde
     *      TASK-004a. Las cinco rutas del módulo apuntaban a un controlador inexistente: sintaxis
     *      correcta, archivo escrito, 500 en cada petición. Ahora el FQCN lo entrega quien sabe
     *      dónde vive la clase, `buildNamespace()`, que es el mismo que decidió dónde escribirla.
     *
     *   2. Escribía con `file_put_contents` directo, así que **no pasaba por el chequeo de
     *      salida**: el sexto agujero de la red, después de los cinco que cerró TASK-003b. Un
     *      `routes/web.php` que no parsea tumba la aplicación entera, no un módulo.
     *
     * @return void
     */
    private function generateSingleAppRoutes(): void
    {
        $routesDir = $this->getComponentBasePath() . '/Routes';
        $this->ensureDirectoryExists($routesDir);

        $functionality   = $this->getFunctionality();
        $controllerClass = $this->prefixClass("{$this->modelName}Controller");
        $controllerFqcn  = $this->buildNamespace('Http\\Controllers') . '\\' . $controllerClass;

        // El mismo bloque que usan los contextos: las 6 rutas, cada una con su permiso. Antes este
        // camino escribía desde dos stubs propios que no ponían ni un solo `->middleware()`.
        $block = $this->buildRouteBlock(
            routePrefix:     $functionality,
            routeName:       $functionality . '.',
            controllerClass: $controllerClass,
            permMiddleware:  $this->resolvePermissionMiddleware([], null),
            permPrefix:      $this->resolvePermissionPrefix([], null),
            permKey:         SubFeaturePermissions::key($functionality),
            indent:          ''
        );

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\Support\Facades\Route;
        use {$controllerFqcn};

        {$block}
        PHP;

        $this->putFile(
            "{$routesDir}/web.php",
            $content,
            "Rutas creadas: Modules/{$this->moduleName}/Routes/web.php"
        );
    }

    /**
     * Actualiza el cache del contexto resuelto. Usado internamente por generateTenantSharedRoutes
     * para iterar los tenants sin tener que reinstanciar el generator.
     *
     * @param  array|null  $contextData  Datos del contexto o null para limpiar el cache
     * @return void
     */
    private function resolveContextCache(?array $contextData): void
    {
        // Accedemos a la propiedad privada del padre mediante reflexión para limpiar el cache
        $reflection = new \ReflectionProperty(AbstractComponentGenerator::class, 'resolvedContext');
        $reflection->setAccessible(true);
        $reflection->setValue($this, $contextData);
    }
}
