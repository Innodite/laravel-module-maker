<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Innodite\LaravelModuleMaker\Support\Disk;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Innodite\LaravelModuleMaker\Generators\Concerns\WritesGeneratedFiles;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clase base para todos los generadores de componentes del módulo.
 *
 * Centraliza la lógica de contexto (Central, Shared, TenantShared, tenant específico)
 * para que cada generator concreto derive automáticamente la carpeta correcta,
 * el prefijo de clase y el namespace sin repetir lógica.
 *
 * El contexto se configura en contexts.json del proyecto (publicado por innodite:setup).
 */
abstract class AbstractComponentGenerator
{
    use HasStubs;
    use WritesGeneratedFiles;

    protected string $moduleName;
    protected string $modulePath;
    protected bool $isClean;
    protected array $componentConfig;
    protected ?OutputInterface $output = null;

    /** Cache del modo: se consulta una vez por generador, no una por archivo. */
    private ?ModuleMode $mode = null;

    /**
     * Cache de la configuración del contexto activo.
     *
     * @var array<string, mixed>|null
     */
    private ?array $resolvedContext = null;

    /**
     * @param  string  $moduleName       Nombre del módulo (se convierte a StudlyCase)
     * @param  string  $modulePath       Ruta absoluta al directorio del módulo
     * @param  bool    $isClean          true = stubs clean, false = stubs dynamic
     * @param  array   $componentConfig  Configuración del componente (puede incluir 'context' y 'functionality')
     */
    public function __construct(
        string $moduleName,
        string $modulePath,
        bool $isClean,
        array $componentConfig = []
    ) {
        $this->moduleName      = Str::studly($moduleName);
        $this->modulePath      = $modulePath;
        $this->isClean         = $isClean;
        $this->componentConfig = $componentConfig;
    }

    /**
     * Establece el objeto de salida de la consola.
     *
     * @param  OutputInterface  $output
     * @return static
     */
    public function setOutput(OutputInterface $output): static
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Ejecuta la generación del componente.
     * Cada clase concreta debe implementar este método.
     *
     * @return void
     */
    abstract public function generate(): void;

    // ─── Stubs con contexto ──────────────────────────────────────────────────

    /**
     * Sobreescribe getStubContent del trait HasStubs para inyectar
     * automáticamente la clave de contexto activo en la resolución del stub.
     *
     * {@inheritdoc}
     */
    protected function getStubContent(string $stubFile, bool $isClean, array $placeholders = [], ?string $context = null): string
    {
        // Pasar la carpeta de contexto real (ej: "Central", "Tenant/Shared") para resolución por carpeta
        $contextFolder = $context ?? $this->getContextFolder() ?: ($this->componentConfig['context'] ?? null);
        $stub = $this->getStub($stubFile, $isClean, $contextFolder);
        return $this->replacePlaceholders($stub, $placeholders);
    }

    // ─── Helpers de contexto ─────────────────────────────────────────────────

    /**
     * Retorna la configuración completa del contexto activo.
     *
     * Usa 'context_id' del componentConfig para identificar el sub-contexto exacto
     * dentro del array del contexto seleccionado (ej: 'Energía España' en contexts.tenant).
     * Si no hay 'context' definido, retorna array vacío (retrocompatibilidad).
     *
     * @return array<string, mixed>
     */
    protected function getContext(): array
    {
        return $this->resolvedContext ??= $this->resolveContextFor($this->componentConfig);
    }

    /**
     * Resuelve la configuración de contexto de una configuración CUALQUIERA, no solo la propia.
     *
     * La necesita el generador que trabaja sobre varios componentes a la vez —el Provider, que
     * registra los bindings de todas las subfuncionalidades del módulo—: sin esto tiene que armar
     * los namespaces por su cuenta, que es exactamente lo que hacía y por lo que acabó importando
     * clases que nadie escribe.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function resolveContextFor(array $config): array
    {
        // La cadena vacía es «sin contexto», no «un contexto llamado ''»: es lo que entrega el
        // comando en single-app, donde no hay eje de contexto que resolver.
        $contextKey = $config['context'] ?? null;
        $contextKey = $contextKey ?: null;
        $contextId  = $config['context_id'] ?? null;

        if ($contextKey === null) {
            return [];
        }

        try {
            return $contextId !== null
                ? ContextResolver::resolveById($contextKey, $contextId)
                : ContextResolver::resolve($contextKey);
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    /**
     * El namespace de un componente de OTRA configuración, con las mismas reglas que buildNamespace().
     *
     * Mismo cálculo, distinta fuente: el contexto solo si el modo tiene eje, y la subfuncionalidad
     * como último tramo. Que el Provider use esto en vez de concatenar por su cuenta es lo que
     * garantiza que su `use` y el archivo que escribe el generador de esa capa digan lo mismo.
     *
     * @param  array<string, mixed>  $config
     */
    protected function namespaceForComponent(string $componentType, array $config, bool $contracts = false): string
    {
        $ctxNs = $this->mode()->hasContextAxis()
            ? ($this->resolveContextFor($config)['namespace_path'] ?? '')
            : '';
        $sub   = $config['subFeature'] ?? '';

        $ns = "Modules\\{$this->moduleName}";
        $ns .= $sub ? "\\{$sub}" : '';
        $ns .= "\\{$componentType}";
        $ns .= $contracts ? '\\Contracts' : '';

        return $ctxNs ? "{$ns}\\{$ctxNs}" : $ns;
    }

    /**
     * El prefijo de clase de OTRA configuración — si el modo lo pide.
     *
     * @param  array<string, mixed>  $config
     */
    protected function classPrefixFor(array $config): string
    {
        if (! $this->mode()->usesClassPrefix()) {
            return '';
        }

        return $this->resolveContextFor($config)['class_prefix'] ?? '';
    }

    /**
     * El modo del proyecto. Decide la FORMA de lo generado, no el contenido.
     *
     * @throws \Innodite\LaravelModuleMaker\Exceptions\ModeNotConfiguredException
     */
    protected function mode(): ModuleMode
    {
        return $this->mode ??= ModuleMode::current();
    }

    /**
     * Prefijo de clase del contexto activo — **si el modo lo pide**.
     *
     * Antes se antepondía siempre, así que una aplicación sin un solo tenant generaba
     * `CentralRoleController`: un prefijo que no desambigua nada, porque no hay nada de lo que
     * distinguirlo. El prefijo existe para separar contextos; sin eje de contexto es
     * ruido pegado al nombre de cada clase de cada módulo.
     *
     * @return string  'Central', 'TenantShared', 'TenantAlpha'… o vacío en single-app
     */
    protected function getClassPrefix(): string
    {
        if (! $this->mode()->usesClassPrefix()) {
            return '';
        }

        return $this->getContext()['class_prefix'] ?? '';
    }

    /**
     * Subcarpeta del contexto — **si el modo tiene eje de contexto**.
     *
     * En single-app la subfuncionalidad va directa bajo la capa: `Models/Role/`, no
     * `Models/Central/Role/`.
     *
     * @return string  'Central', 'Tenant/Shared', 'Tenant/Alpha'… o vacío en single-app
     */
    protected function getContextFolder(): string
    {
        if (! $this->mode()->hasContextAxis()) {
            return '';
        }

        return $this->getContext()['folder'] ?? '';
    }

    /**
     * Retorna el nombre de la entidad para usarlo como subcarpeta dentro de la carpeta de contexto.
     * Patrón: {Tipo}/{Contexto}/{Entidad}/
     * Ej: Models/Central/User/, Http/Controllers/Tenant/EnergySpain/Role/
     * Retorna cadena vacía si no hay entidad definida (retrocompatibilidad).
     *
     * @return string
     */
    protected function getSubFeatureFolder(): string
    {
        return $this->componentConfig['subFeature'] ?? '';
    }

    /**
     * El nombre de la subfuncionalidad para **componer clases**, no carpetas.
     *
     * Se diferencia de `getSubFeatureFolder()` en el caso vacío, y esa diferencia importa: una
     * carpeta puede no existir —y entonces la capa se escribe un nivel más arriba—, pero una clase
     * siempre tiene que llamarse de algo. Sin subfuncionalidad declarada, el nombre lo pone el
     * módulo.
     *
     * Existe aquí porque lo necesitan los dos lados de la misma pareja: el generador que **escribe**
     * los FormRequests y el que los **importa** en la firma del controlador. Cada uno resolviendo el
     * caso vacío por su cuenta es la forma exacta en que dos nombres correctos dejan de coincidir.
     */
    protected function subFeatureName(): string
    {
        return $this->getSubFeatureFolder() ?: $this->moduleName;
    }

    /**
     * Fragmento de namespace del contexto — **si el modo tiene eje de contexto**.
     *
     * Espeja a getContextFolder(): la carpeta y el namespace no pueden discrepar, o las clases
     * generadas no se autocargan.
     *
     * @return string  'Central', 'Tenant\\Shared', 'Tenant\\Alpha'… o vacío en single-app
     */
    protected function getContextNamespacePath(): string
    {
        if (! $this->mode()->hasContextAxis()) {
            return '';
        }

        return $this->getContext()['namespace_path'] ?? '';
    }

    /**
     * Construye el namespace completo para un tipo de componente dentro del módulo.
     *
     * Patrón: `Modules\{Module}\{SubFeature}\{Type}\{ContextNs}`
     * Ej: buildNamespace('Http\\Controllers') → 'Modules\User\Role\Http\Controllers\Central'
     *
     * ⛔ El orden importa y hasta la 4.x estaba al revés — `{Type}\{ContextNs}\{SubFeature}`—, con
     * dos consecuencias. Una, que el árbol se leía por capas y no por funcionalidad: para ver qué
     * tiene `Role` había que abrir doce carpetas. Y dos, la que costaba de verdad: con el contexto
     * por delante, cada capa duplicaba su rama entera por contexto.
     *
     * Ahora cada subfuncionalidad es autocontenida y el contexto es la hoja. En aplicación única
     * ese último tramo no existe y el resto es idéntico, que es lo que permite que los dos modos
     * compartan estructura en vez de parecerse.
     *
     * @param  string  $componentType  Tipo de componente (ej: 'Http\\Controllers', 'Services', 'Models')
     * @return string
     */
    protected function buildNamespace(string $componentType): string
    {
        return $this->componerNamespace($componentType, contracts: false);
    }

    /**
     * Construye el namespace de la carpeta Contracts para un tipo de componente.
     *
     * Patrón: `Modules\{Module}\{SubFeature}\{Type}\Contracts\{ContextNs}`
     * Ej: buildContractsNamespace('Services') → 'Modules\User\Role\Services\Contracts\Tenant'
     *
     * @param  string  $componentType  Tipo de componente (ej: 'Services', 'Repositories')
     * @return string
     */
    protected function buildContractsNamespace(string $componentType): string
    {
        return $this->componerNamespace($componentType, contracts: true);
    }

    /**
     * El namespace, con la subfuncionalidad delante y el contexto detrás.
     *
     * Uno solo para los dos casos: el `Contracts` es un tramo más de la capa, no otra regla. Cuando
     * eran dos métodos con el mismo cálculo copiado, arreglar el orden en uno y no en el otro dejaba
     * la interfaz en una carpeta y su implementación en otra — con el `use` apuntando a la que no
     * era, que es la familia de defecto que este paquete lleva doce apariciones persiguiendo.
     */
    private function componerNamespace(string $componentType, bool $contracts): string
    {
        $sub   = $this->getSubFeatureFolder();
        $ctxNs = $this->getContextNamespacePath();

        $ns = "Modules\\{$this->moduleName}";
        $ns .= $sub ? "\\{$sub}" : '';
        $ns .= "\\{$componentType}";
        $ns .= $contracts ? '\\Contracts' : '';

        return $ctxNs ? "{$ns}\\{$ctxNs}" : $ns;
    }

    /**
     * Construye la ruta absoluta de carpeta para un tipo de componente dentro del módulo.
     *
     * Patrón: `{ModulePath}/{SubFeature}/{Type}/{ContextFolder}/`
     * Ej: buildPath('Http/Controllers') → '.../User/Role/Http/Controllers/Central'
     *
     * Espeja a {@see self::buildNamespace()}, y tiene que hacerlo tramo a tramo: PSR-4 busca la
     * clase por su carpeta, así que una discrepancia entre los dos no da error de sintaxis — da una
     * clase que no se autocarga.
     *
     * @param  string  $componentType  Tipo de componente (ej: 'Http/Controllers', 'Services', 'Models')
     * @return string
     */
    protected function buildPath(string $componentType): string
    {
        return $this->componerRuta($componentType, contracts: false);
    }

    /**
     * Construye la ruta absoluta a la carpeta Contracts para un tipo de componente.
     *
     * Patrón: `{ModulePath}/{SubFeature}/{Type}/Contracts/{ContextFolder}/`
     * Ej: buildContractsPath('Services') → '.../User/Role/Services/Contracts/Tenant'
     *
     * @param  string  $componentType  Tipo de componente (ej: 'Services', 'Repositories')
     * @return string
     */
    protected function buildContractsPath(string $componentType): string
    {
        return $this->componerRuta($componentType, contracts: true);
    }

    /** La ruta, con la subfuncionalidad delante y el contexto detrás. El espejo de componerNamespace(). */
    private function componerRuta(string $componentType, bool $contracts): string
    {
        $sub    = $this->getSubFeatureFolder();
        $folder = $this->getContextFolder();

        $path = $this->getComponentBasePath();
        $path .= $sub ? "/{$sub}" : '';
        $path .= "/{$componentType}";
        $path .= $contracts ? '/Contracts' : '';

        return $folder ? "{$path}/{$folder}" : $path;
    }

    /**
     * La ruta de un archivo escrito, tal como se le enseña al usuario: `Modules/User/Role/Models/…`
     *
     * Sale de la ruta REAL, no de recomponerla. Cada generador la componía a mano en su mensaje
     * —`"Modules/{$module}/Services/{$contexto}/{$nombre}.php"`— y trece de ellos quedaron mintiendo
     * el día que cambió el orden del árbol: decían una carpeta y el archivo estaba en otra. Un
     * mensaje equivocado no rompe nada, y por eso nadie lo arregla; solo enseña a desconfiar de lo
     * que dice el generador.
     */
    protected function rutaVisible(string $absoluta): string
    {
        $base = $this->getComponentBasePath();

        return str_starts_with($absoluta, $base)
            ? "Modules/{$this->moduleName}" . substr($absoluta, strlen($base))
            : $absoluta;
    }

    /**
    * Prefija el nombre de la clase con el prefijo del contexto activo.
    * Ej: prefixClass('UserController') con contexto 'alpha' → 'TenantAlphaUserController'
     *
     * @param  string  $className  Nombre de la clase sin prefijo
     * @return string
     */
    protected function prefixClass(string $className): string
    {
        $prefix = $this->getClassPrefix();
        return $prefix ? $prefix . $className : $className;
    }

    /**
     * Retorna el nombre de la funcionalidad en kebab-case para el prefijo de ruta.
     * Ej: 'users', 'campaign-goals'
     * Si no está definido, usa el nombre del módulo en kebab-case.
     *
     * @return string
     */
    protected function getFunctionality(): string
    {
        return $this->componentConfig['functionality']
            ?? Str::kebab(Str::plural(Str::snake($this->moduleName)));
    }

    // ─── Lo que más de un generador necesita saber, decidido UNA vez ──────────

    /**
     * El prefijo del permiso: lo dice **el modo**, y el contexto solo puede afinarlo.
     *
     * Vive aquí y no en el generador de rutas porque lo necesitan **los dos lados de la misma
     * pareja**: el que escribe el `->middleware()` de cada ruta y el que escribe el seeder que crea
     * esos permisos. Calculado por separado, el día que uno cambie el otro seguirá emitiendo el
     * nombre viejo — y el síntoma será un 403 a quien sí tiene el permiso, o una pantalla que no
     * abre nadie.
     *
     * Antes se leía únicamente de `contexts.json`. Cuando ese archivo no declaraba
     * `permission_prefix` —lo normal en un proyecto recién instalado— el prefijo llegaba **vacío** y
     * las rutas exigían `invoices_index` en vez de `central_invoices_index`: un permiso que el
     * seeder no crea. `ModuleMode::permissionPrefix()` responde exactamente esta pregunta desde la
     * fase 1, con su prueba.
     *
     * @param  array<string, mixed>  $context  Contexto ya resuelto (vacío en single-app)
     */
    protected function resolvePermissionPrefix(array $context, ?string $contextKey, ?string $tenantId = null): string
    {
        $delContexto = $context['permission_prefix'] ?? '';

        return $delContexto !== ''
            ? $delContexto
            : $this->mode()->permissionPrefix($contextKey);
    }

    /**
     * El middleware que protege la ruta, con la misma regla: manda el modo.
     *
     * Un middleware vacío no deja la ruta desprotegida de forma visible: produce
     * `->middleware(':invoices_index')`, con los dos puntos sueltos y el nombre vacío. Eso no es
     * «sin permiso», es una ruta que revienta al resolverse — y solo en ejecución.
     *
     * @param  array<string, mixed>  $context  Contexto ya resuelto (vacío en single-app)
     */
    protected function resolvePermissionMiddleware(array $context, ?string $contextKey): string
    {
        $delContexto = $context['permission_middleware'] ?? '';

        return $delContexto !== ''
            ? $delContexto
            : $this->mode()->permissionMiddleware($contextKey);
    }

    /**
     * El prefijo del permiso de **esta** subfuncionalidad, con sus tres datos ya puestos.
     *
     * La forma corta de la pregunta anterior, para quien no está resolviendo contextos a mano.
     */
    protected function permissionPrefix(): string
    {
        $context = $this->getContext();

        return $this->resolvePermissionPrefix(
            $context,
            ($this->componentConfig['context'] ?? '') ?: null,
            $context['id'] ?? null,
        );
    }

    /**
     * La conexión de base de datos que declara esta subfuncionalidad, o `null` si no declara ninguna.
     *
     * Las tres respuestas del patrón, que el enum sabe dar desde la fase 1:
     *
     *   single-app          no declara: hay una sola base de datos, nada que conmutar
     *   central             declara siempre `'central'`
     *   tenant compartido   **no** declara — la conmuta el paquete de tenancy al inicializar el
     *                       contexto, y nombrarla aquí ataría el módulo a un solo inquilino
     *   tenant con lógica propia   declara la suya
     *
     * La necesitan el modelo (como propiedad `$connection`) y los tres seeders ejecutables (para
     * `Schema::connection()`). Dos cálculos de esto es un modelo leyendo de una base y su seeder
     * sembrando en otra.
     */
    protected function connectionKey(): ?string
    {
        $contextKey = ($this->componentConfig['context'] ?? '') ?: null;

        if (! $this->mode()->declaresModelConnection($contextKey)) {
            return null;
        }

        return $this->getContext()['connection_key'] ?? $contextKey;
    }

    /**
     * El nombre de la tabla de esta subfuncionalidad.
     *
     * Lo usan la migración que la crea y los seeders que la validan y la vacían. Si divergen, el
     * seeder valida una tabla que no existe mientras la real queda sin comprobar.
     */
    protected function tableName(?string $entidad = null): string
    {
        $declarada = $this->componentConfig['table'] ?? null;

        if (is_string($declarada) && $declarada !== '') {
            return $declarada;
        }

        return Str::snake(Str::plural($entidad ?: ($this->getSubFeatureFolder() ?: $this->moduleName)));
    }

    // ─── Helpers de filesystem ────────────────────────────────────────────────

    /**
     * Retorna la ruta base del módulo dentro del directorio de módulos.
     *
     * @return string
     */
    protected function getComponentBasePath(): string
    {
        return config('make-module.module_path') . "/{$this->moduleName}";
    }

    /**
     * Crea los directorios necesarios si no existen.
     *
     * @param  string  $directoryPath  Ruta absoluta del directorio
     * @return void
     */
    protected function ensureDirectoryExists(string $directoryPath): void
    {
        Disk::ensureDirectory($directoryPath);
    }

    // putFile() vive en WritesGeneratedFiles: la comparten también los cinco generadores que
    // no heredan de esta clase, y el chequeo de salida tiene que alcanzarlos igual.

    // ─── Output ───────────────────────────────────────────────────────────────

    /**
     * Muestra un mensaje de información en consola.
     *
     * @param  string  $message
     * @return void
     */
    public function info(string $message): void
    {
        $this->output?->writeln("<info>{$message}</info>");
    }

    /**
     * Muestra un mensaje de advertencia en consola.
     *
     * @param  string  $message
     * @return void
     */
    public function warn(string $message): void
    {
        $this->output?->writeln("<comment>{$message}</comment>");
    }

    /**
     * Muestra un mensaje de error en consola.
     *
     * @param  string  $message
     * @return void
     */
    public function error(string $message): void
    {
        $this->output?->writeln("<error>{$message}</error>");
    }
}
