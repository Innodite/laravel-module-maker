<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Support\ContextResolver;

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
 *   energy_spain  → routes/tenant.php, un solo bloque para ese tenant
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
        $context = $this->getContext();

        // Sin contexto definido → comportamiento legacy (ruta simple)
        if (empty($context)) {
            $this->generateLegacy();
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

        // central → envuelto en foreach central_domains
        if ($context['wrap_central_domains'] ?? false) {
            $this->generateCentralRoutes($routesDir);
            return;
        }

        // shared / central sin wrap → web.php simple
        $this->generateSharedRoutes($routesDir, $context);
    }

    // ─── Generadores por tipo de contexto ────────────────────────────────────

    /**
     * Genera rutas para la app central, envueltas en foreach de central_domains.
     *
     * @param  string  $routesDir  Ruta al directorio de rutas del módulo
     * @return void
     */
    private function generateCentralRoutes(string $routesDir): void
    {
        $context          = $this->getContext();
        $functionality    = $this->getFunctionality();
        $controllerClass  = $this->buildControllerClass();
        $controllerFqcn   = $this->buildControllerNamespace() . '\\' . $controllerClass;
        $permPrefix       = $context['permission_prefix'];
        $permMiddleware   = $context['permission_middleware'];
        $permKey          = Str::snake(str_replace('-', '_', $functionality));

        $block = $this->buildRouteBlock(
            routePrefix:    $context['route_prefix'] . '-' . $functionality,
            routeName:      $context['route_name'] . $functionality . '.',
            controllerClass: $controllerClass,
            permMiddleware: $permMiddleware,
            permPrefix:     $permPrefix,
            permKey:        $permKey,
            indent:         '        '
        );

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\Support\Facades\Route;
        use {$controllerFqcn};

        foreach (config('tenancy.central_domains') as \$domain) {
            Route::domain(\$domain)->group(function () {

        {$block}
            // {{CENTRAL_END}}
            });
        }
        PHP;

        $this->writeOrAppend("{$routesDir}/web.php", $content, '{{CENTRAL_END}}', $block, $controllerFqcn);
    }

    /**
     * Genera rutas para un contexto Shared (dual: web.php + tenant.php).
     *
     * El contexto Shared escribe en DOS archivos con prefijos distintos para
     * evitar colisiones de nombres de ruta:
     *   web.php    → usa web_route_prefix / web_route_name    (ej: central.shared-users)
     *   tenant.php → usa tenant_route_prefix / tenant_route_name (ej: tenant.shared-users)
     *
     * Si route_middleware es vacío, NO se añade ->middleware() (hereda del grupo padre).
     *
     * @param  string  $routesDir  Ruta al directorio de rutas del módulo
     * @param  array   $context    Configuración del contexto shared
     * @return void
     */
    private function generateSharedRoutes(string $routesDir, array $context): void
    {
        $functionality   = $this->getFunctionality();
        $controllerClass = $this->buildControllerClass();
        $controllerFqcn  = $this->buildControllerNamespace() . '\\' . $controllerClass;
        $permPrefix      = $context['permission_prefix'] ?? '';
        $permMiddleware  = $context['permission_middleware'] ?? '';
        $permKey         = Str::snake(str_replace('-', '_', $functionality));
        $middleware      = $context['route_middleware'] ?? [];

        // ── Prefijos diferenciados por archivo ────────────────────────────────
        $webPrefix  = ($context['web_route_prefix']  ?? $context['route_prefix']  ?? 'shared') . '-' . $functionality;
        $webName    = ($context['web_route_name']    ?? $context['route_name']    ?? 'shared.') . $functionality . '.';
        $tnntPrefix = ($context['tenant_route_prefix'] ?? $context['route_prefix']  ?? 'shared') . '-' . $functionality;
        $tnntName   = ($context['tenant_route_name']   ?? $context['route_name']    ?? 'shared.') . $functionality . '.';

        // ── Bloque para web.php ───────────────────────────────────────────────
        $webBlock = $this->buildRouteBlock(
            routePrefix:     $webPrefix,
            routeName:       $webName,
            controllerClass: $controllerClass,
            permMiddleware:  $permMiddleware,
            permPrefix:      $permPrefix,
            permKey:         $permKey,
            indent:          '    '
        );

        $webContent = $this->buildSharedFileContent($controllerFqcn, $webBlock, $middleware, 'SHARED_WEB_END');
        $this->writeOrAppend("{$routesDir}/web.php", $webContent, 'SHARED_WEB_END', $webBlock, $controllerFqcn);

        // ── Bloque para tenant.php ────────────────────────────────────────────
        $tenantBlock = $this->buildRouteBlock(
            routePrefix:     $tnntPrefix,
            routeName:       $tnntName,
            controllerClass: $controllerClass,
            permMiddleware:  $permMiddleware,
            permPrefix:      $permPrefix,
            permKey:         $permKey,
            indent:          '    '
        );

        $tenantContent = $this->buildSharedFileContent($controllerFqcn, $tenantBlock, $middleware, 'SHARED_TENANT_END');
        $this->writeOrAppend("{$routesDir}/tenant.php", $tenantContent, 'SHARED_TENANT_END', $tenantBlock, $controllerFqcn);
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
        $permPrefix      = $context['permission_prefix'];
        $permMiddleware  = $context['permission_middleware'];
        $permKey         = Str::snake(str_replace('-', '_', $functionality));
        $middleware      = $this->buildMiddlewareArray($context['route_middleware'] ?? []);
        $label           = $context['name'] ?? $context['label'] ?? $classPrefix;
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
            // Sustituir temporalmente el context_name para generar cada bloque con los datos del tenant
            $this->componentConfig['context_name'] = $tenantItem['name'];
            $this->resolveContextCache($tenantItem);
            $this->generateSingleTenantRoutes($routesDir, $tenantItem);
        }

        // Restaurar el contexto original
        $this->componentConfig['context_name'] = null;
        $this->resolveContextCache(null);
    }

    // ─── Helpers de construcción de rutas ────────────────────────────────────

    /**
     * Construye el bloque de rutas CRUD estándar para una funcionalidad.
     *
     * @param  string  $routePrefix     Prefijo de URL (ej: 'energy-spain-users')
     * @param  string  $routeName       Prefijo de nombre (ej: 'energy-spain.users.')
     * @param  string  $controllerClass Nombre corto de la clase del controlador (sin namespace)
     * @param  string  $permMiddleware  Middleware de permisos ('tenant-permission' o 'central-permission')
     * @param  string  $permPrefix      Prefijo del permiso (ej: 'energy_spain')
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

        return <<<PHP
        {$i}Route::prefix('{$routePrefix}')
        {$i}    ->name('{$routeName}')
        {$i}    ->group(function () {
        {$i2}// Vista principal
        {$i2}Route::get('/', [{$controllerClass}::class, 'index'])
        {$i2}    ->name('index')
        {$i2}    ->middleware('{$permMiddleware}:{$permPrefix}_{$permKey}_index');

        {$i2}// Endpoint JSON listado
        {$i2}Route::get('/list', [{$controllerClass}::class, 'list'])
        {$i2}    ->name('list')
        {$i2}    ->middleware('{$permMiddleware}:{$permPrefix}_{$permKey}_index');

        {$i2}// Crear
        {$i2}Route::post('/', [{$controllerClass}::class, 'store'])
        {$i2}    ->name('store')
        {$i2}    ->middleware('{$permMiddleware}:{$permPrefix}_{$permKey}_store');

        {$i2}// Ver uno
        {$i2}Route::get('/{id}', [{$controllerClass}::class, 'show'])
        {$i2}    ->name('show')
        {$i2}    ->middleware('{$permMiddleware}:{$permPrefix}_{$permKey}_show');

        {$i2}// Actualizar
        {$i2}Route::put('/{id}', [{$controllerClass}::class, 'update'])
        {$i2}    ->name('update')
        {$i2}    ->middleware('{$permMiddleware}:{$permPrefix}_{$permKey}_update');

        {$i2}// Eliminar
        {$i2}Route::delete('/{id}', [{$controllerClass}::class, 'destroy'])
        {$i2}    ->name('destroy')
        {$i2}    ->middleware('{$permMiddleware}:{$permPrefix}_{$permKey}_delete');
        {$i}});
        PHP;
    }

    /**
     * Retorna solo el nombre corto de la clase del controlador (sin namespace).
     * Ej: 'TenantEnergySpainUserController'
     *
     * @return string
     */
    private function buildControllerClass(): string
    {
        return $this->prefixClass("{$this->modelName}Controller");
    }

    /**
     * Retorna el namespace completo (FQCN) del controlador para el import use.
     * Ej: 'Modules\Products\Http\Controllers\Tenant\EnergySpain\TenantEnergySpainUserController'
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
     * Comportamiento legacy para componentes sin contexto definido.
     * Mantiene retrocompatibilidad con proyectos que no usan contexts.json.
     *
     * @return void
     */
    private function generateLegacy(): void
    {
        $routesDir = $this->getComponentBasePath() . '/Routes';
        $this->ensureDirectoryExists($routesDir);

        $controllerName = "{$this->modelName}Controller";

        $stubApi = $this->getStubContent('route-api.stub', $this->isClean, [
            'StudlyModule'   => $this->moduleName,
            'snakeModule'    => Str::snake($this->moduleName),
            'controllerName' => $controllerName,
        ]);
        file_put_contents("{$routesDir}/api.php", $stubApi);

        $stubWeb = $this->getStubContent('route-web.stub', $this->isClean, [
            'StudlyModule'   => $this->moduleName,
            'snakeModule'    => Str::snake($this->moduleName),
            'controllerName' => $controllerName,
        ]);
        file_put_contents("{$routesDir}/web.php", $stubWeb);

        $this->info("✅ Rutas legacy creadas: Modules/{$this->moduleName}/Routes/");
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
