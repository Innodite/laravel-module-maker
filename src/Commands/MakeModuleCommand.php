<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\ModuleGenerator;
use Innodite\LaravelModuleMaker\Services\ModuleAuditor;
use Innodite\LaravelModuleMaker\Services\RouteInjectionService;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Throwable;

/**
 * MakeModuleCommand — Orquestador Maestro del Generador de Módulos v3.0.0
 *
 * Modos de uso:
 *   1. Módulo completo con contexto explícito:
 *        php artisan innodite:make-module User --context=central
 *
 *   2. Módulo completo con selección interactiva (sin --context):
 *        php artisan innodite:make-module User
 *
 *   3. Componentes individuales en módulo existente:
 *        php artisan innodite:make-module User --context=shared -S -R
 *
 *   4. Desde JSON de configuración dinámica:
 *        php artisan innodite:make-module User --json
 *
 *   5. Sin inyección de rutas:
 *        php artisan innodite:make-module User --context=central --no-routes
 */
class MakeModuleCommand extends Command
{
    protected $signature = 'innodite:make-module
        {name                  : Nombre de la entidad en singular (se convierte a PascalCase)}
        {--context=            : Contexto: central | shared | tenant_shared | nombre-del-tenant}
        {--json                : Usa module-maker-config/{module}.json como fuente de configuración}
        {--no-routes           : Omite la inyección de rutas en el proyecto}
        {--M|model             : Solo añade el modelo}
        {--C|controller        : Solo añade el controlador}
        {--S|service           : Solo añade el servicio e interface}
        {--R|repository        : Solo añade el repositorio e interface}
        {--G|migration         : Solo añade la migración contextualizada}
        {--Q|request           : Solo añade el form request}';

    protected $description = 'Genera un módulo completo con inyección de rutas contextualizada.';

    // ─── Entry point ──────────────────────────────────────────────────────────

    public function handle(): int
    {
        // ── Pre-flight: validar nombre ────────────────────────────────────────
        try {
            $moduleName = $this->resolveModuleName($this->argument('name'));
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            return Command::FAILURE;
        }

        $modulePath = config('make-module.module_path') . "/{$moduleName}";

        // ── Modo JSON ─────────────────────────────────────────────────────────
        if ($this->option('json')) {
            return $this->handleJsonMode($moduleName);
        }

        // ── Modo componentes individuales ─────────────────────────────────────
        if ($this->hasIndividualFlags()) {
            return $this->handleComponentMode($moduleName, $modulePath);
        }

        // ── Módulo ya existe (modo completo) ──────────────────────────────────
        if (File::exists($modulePath)) {
            $this->components->error(
                "El módulo '{$moduleName}' ya existe en {$modulePath}."
                . " Usa -M -C -S -R -G -Q para añadir componentes."
            );
            return Command::FAILURE;
        }

        // ── Modo completo ─────────────────────────────────────────────────────
        return $this->handleFullModule($moduleName, $modulePath);
    }

    // ─── Modos de ejecución ───────────────────────────────────────────────────

    /**
     * Genera el módulo completo: estructura + inyección de rutas.
     */
    private function handleFullModule(string $moduleName, string $modulePath): int
    {
        // Pre-flight: resolver contexto
        try {
            [$contextKey, $contextItem] = $this->resolveContext();
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            return Command::FAILURE;
        }

        $contextId     = $contextItem['id'] ?? '';
        $functionality = $this->resolveFunctionality($moduleName);

        $this->newLine();
        $this->components->info("Generando módulo <comment>{$moduleName}</comment>");
        $this->displayConfigTable($moduleName, $contextKey, $contextId, $functionality);
        $this->newLine();

        $filesGenerated = false;

        try {
            // ── Paso 1: Generar estructura de archivos (Fases 1 & 2) ──────────
            $this->components->task('Creando estructura de archivos', function () use (
                $moduleName, $contextKey, $functionality, $contextId, &$filesGenerated
            ) {
                (new ModuleGenerator($moduleName, true, null, $this))
                    ->createCleanModuleWithContext($contextKey, $functionality, $contextId);

                $filesGenerated = true;
                return true;
            });

            // ── Paso 2: Inyectar rutas en el proyecto (Fase 3) ────────────────
            if (!$this->option('no-routes')) {
                $this->components->task('Inyectando rutas en el proyecto', function () use (
                    $contextKey, $moduleName, $contextId, $contextItem
                ) {
                    $controllerFqcn = $this->buildControllerFqcn($moduleName, $contextItem);

                    (new RouteInjectionService($this))->inject(
                        contextKey:     $contextKey,
                        entityName:     $moduleName,
                        contextId:      $contextId,
                        controllerFqcn: $controllerFqcn,
                        contextConfig:  $contextItem
                    );

                    return true;
                });
            }

            $this->newLine();
            $this->displaySuccess($moduleName, $contextKey, $contextId);

            // ── Auditoría ─────────────────────────────────────────────────────
            ModuleAuditor::log('module.created', [
                'module'        => $moduleName,
                'context_key'   => $contextKey,
                'context_id'    => $contextId,
                'functionality' => $functionality,
                'routes'        => !$this->option('no-routes'),
            ]);

            return Command::SUCCESS;

        } catch (Throwable $e) {
            $this->newLine();
            $this->components->error("Error: {$e->getMessage()}");

            // ── Rollback opcional si hay archivos generados ───────────────────
            if ($filesGenerated && File::exists($modulePath)) {
                if ($this->confirm("¿Deseas eliminar los archivos generados en '{$modulePath}'? (Rollback)")) {
                    $this->performRollback($modulePath);
                    ModuleAuditor::log('module.rollback', [
                        'module'      => $moduleName,
                        'context_key' => $contextKey ?? 'unknown',
                        'reason'      => $e->getMessage(),
                    ]);
                } else {
                    $this->components->warn("Los archivos generados se mantienen. Revisa el estado manualmente.");
                }
            }

            return Command::FAILURE;
        }
    }

    /**
     * Añade componentes individuales a un módulo existente.
     */
    private function handleComponentMode(string $moduleName, string $modulePath): int
    {
        // Si el módulo no existe, ofrecer crear la estructura base
        if (!File::exists($modulePath)) {
            $this->components->warn("El módulo '{$moduleName}' no existe.");
            if (!$this->confirm("¿Crear la estructura base antes de continuar?")) {
                return Command::FAILURE;
            }
            (new ModuleGenerator($moduleName, true, null, $this))->createFolders();
        }

        try {
            [$contextKey, $contextItem] = $this->resolveContext();
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            return Command::FAILURE;
        }

        $contextId = $contextItem['id'] ?? '';

        $componentConfig = [
            'context'    => $contextKey,
            'context_id' => $contextId,
        ];

        $flags = [
            'model'      => $this->option('model'),
            'controller' => $this->option('controller'),
            'service'    => $this->option('service'),
            'repository' => $this->option('repository'),
            'migration'  => $this->option('migration'),
            'request'    => $this->option('request'),
        ];

        $this->newLine();
        $this->components->info("Añadiendo componentes a <comment>{$moduleName}</comment> [{$contextKey}]");
        $this->newLine();

        try {
            $this->components->task('Generando componentes', function () use (
                $moduleName, $flags, $componentConfig
            ) {
                (new ModuleGenerator($moduleName, true, null, $this))
                    ->createIndividualComponents($flags, $componentConfig);
                return true;
            });

            // Inyectar rutas si se generó un controller
            if (!$this->option('no-routes') && ($flags['controller'] ?? false)) {
                $this->components->task('Inyectando rutas', function () use (
                    $contextKey, $moduleName, $contextId, $contextItem
                ) {
                    (new RouteInjectionService($this))->inject(
                        contextKey:     $contextKey,
                        entityName:     $moduleName,
                        contextId:      $contextId,
                        controllerFqcn: $this->buildControllerFqcn($moduleName, $contextItem),
                        contextConfig:  $contextItem
                    );
                    return true;
                });
            }

            return Command::SUCCESS;

        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Genera el módulo desde un archivo JSON de configuración dinámica.
     */
    private function handleJsonMode(string $moduleName): int
    {
        $configDir     = config('make-module.config_path');
        $jsonPath      = "{$configDir}/" . Str::lower($moduleName) . '.json';
        $jsonPathKebab = "{$configDir}/" . Str::kebab($moduleName) . '.json';

        if (!File::exists($jsonPath) && File::exists($jsonPathKebab)) {
            $jsonPath = $jsonPathKebab;
        }

        if (!File::exists($jsonPath)) {
            $this->components->error("No se encontró archivo de configuración para '{$moduleName}'.");
            $this->line("  Buscado en: <comment>{$jsonPath}</comment>");
            return Command::FAILURE;
        }

        $config = json_decode(File::get($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->components->error("JSON inválido en '{$jsonPath}': " . json_last_error_msg());
            return Command::FAILURE;
        }

        $this->components->info("Usando configuración: <comment>{$jsonPath}</comment>");

        $resolvedName = Str::studly($config['module_name'] ?? $moduleName);

        try {
            $this->components->task('Generando módulo dinámico', function () use ($resolvedName, $config) {
                (new ModuleGenerator($resolvedName, false, $config, $this))->createDynamicModule();
                return true;
            });

            return Command::SUCCESS;

        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    // ─── Pre-flight: Validaciones ─────────────────────────────────────────────

    /**
     * Convierte el input a PascalCase y valida que sea un identificador PHP válido.
     *
     * Sub-proceso atómico:
     *   input "user-profile" → Str::studly() → "UserProfile"
     *   Regex: /^[A-Z][a-zA-Z0-9]+$/
     *
     * @throws \InvalidArgumentException Si el nombre no es válido
     */
    // ─── Palabras reservadas que no pueden usarse como nombre de módulo ──────────

    private const RESERVED_NAMES = [
        // PHP keywords
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do',
        'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
        'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final',
        'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if',
        'implements', 'include', 'include_once', 'instanceof', 'insteadof',
        'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'print',
        'private', 'protected', 'public', 'readonly', 'require', 'require_once',
        'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use',
        'var', 'while', 'xor', 'yield',
        // PHP built-in class names
        'exception', 'error', 'closure', 'generator', 'iterator', 'arrayaccess',
        'countable', 'stringable', 'throwable',
        // Laravel names que generarían conflictos de namespace
        'app', 'config', 'route', 'request', 'response', 'model', 'controller',
        'middleware', 'provider', 'facade', 'auth', 'event', 'job', 'mail',
        'notification', 'policy', 'rule', 'seeder', 'factory', 'migration',
    ];

    private function resolveModuleName(string $input): string
    {
        $name = Str::studly($input);

        if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $name)) {
            throw new \InvalidArgumentException(
                "'{$name}' no es un nombre de módulo válido. "
                . "Usa letras y números en PascalCase (ej: User, InvoiceItem)."
            );
        }

        if (in_array(strtolower($name), self::RESERVED_NAMES, true)) {
            throw new \InvalidArgumentException(
                "'{$name}' es una palabra reservada de PHP o Laravel y no puede "
                . "usarse como nombre de módulo. Usa un nombre específico del dominio (ej: UserAccount)."
            );
        }

        return $name;
    }

    /**
     * Resuelve el contexto desde --context o via selección interactiva.
     *
     * Orden de resolución:
     *   1. Si --context=X y X es una clave directa (central, shared, tenant_shared) → usar
     *   2. Si --context=X y X coincide con name/class_prefix de un tenant → usar ese tenant
     *   3. Si --context vacío → selección interactiva
     *   4. Si nada coincide → error con lista de disponibles
     *
     * @return array{0: string, 1: array}  [contextKey, contextItem]
     * @throws \InvalidArgumentException
     */
    private function resolveContext(): array
    {
        $allContexts = $this->loadContexts();
        $option      = trim($this->option('context') ?? '');

        // Sin opción → selección interactiva
        if ($option === '') {
            return $this->askContextInteractive($allContexts);
        }

        // Coincidencia directa con clave de contexto
        if (isset($allContexts[$option])) {
            $item = $allContexts[$option];
            
            if (!is_array($item)) {
                throw new \InvalidArgumentException("Contexto '{$option}' tiene formato inválido.");
            }
            
            // Detectar si es array asociativo (contexto único) vs array indexado (lista)
            $isAssociative = array_keys($item) !== range(0, count($item) - 1);
            
            // Array asociativo → contexto único (central, shared, tenant_shared)
            if ($isAssociative) {
                return [$option, $item];
            }
            
            // Array indexado → múltiples variantes (ej. tenant)
            if (count($item) === 1) {
                return [$option, $item[0]];
            }
            
            // Múltiples variantes → preguntar cuál
            return $this->askVariant($option, $item);
        }

        // Buscar en tenants por id, class_prefix o slug
        foreach ($allContexts['tenant'] ?? [] as $item) {
            if ($this->tenantMatches($option, $item)) {
                return ['tenant', $item];
            }
        }

        // No encontrado → error descriptivo
        $available = implode(', ', array_keys($allContexts));
        $tenants   = implode(', ', array_map(
            fn ($t) => $t['id'] ?? 'unknown',
            $allContexts['tenant'] ?? []
        ));

        throw new \InvalidArgumentException(
            "Contexto '{$option}' no encontrado en contexts.json.\n"
            . "  Contextos disponibles: {$available}\n"
            . "  Tenants disponibles:   {$tenants}"
        );
    }

    /**
     * Verifica si un tenant coincide con el input del usuario.
     * Acepta: id exacto, class_prefix, o route_prefix.
     */
    private function tenantMatches(string $option, array $item): bool
    {
        return
            strcasecmp($option, $item['id'] ?? '')           === 0 ||
            strcasecmp($option, $item['class_prefix'] ?? '') === 0 ||
            strcasecmp($option, $item['route_prefix'] ?? '') === 0;
    }

    /**
     * Selección interactiva de contexto cuando no se pasa --context.
     *
     * @return array{0: string, 1: array}
     * @throws \InvalidArgumentException
     */
    private function askContextInteractive(array $allContexts): array
    {
        $keys        = array_keys($allContexts);
        $selectedKey = $this->choice('Selecciona el contexto:', $keys, 0);
        $item        = $allContexts[$selectedKey];

        if (!is_array($item)) {
            throw new \InvalidArgumentException("El contexto '{$selectedKey}' tiene formato inválido.");
        }

        // Detectar si es array asociativo (contexto único) vs array indexado (lista)
        $isAssociative = array_keys($item) !== range(0, count($item) - 1);

        // Array asociativo → contexto único
        if ($isAssociative) {
            return [$selectedKey, $item];
        }

        // Array indexado → lista de variantes
        if (count($item) === 1) {
            return [$selectedKey, $item[0]];
        }

        return $this->askVariant($selectedKey, $item);
    }

    /**
     * Selección interactiva cuando un contexto tiene múltiples variantes.
     *
     * @return array{0: string, 1: array}
     */
    private function askVariant(string $contextKey, array $items): array
    {
        $names = array_map(fn($item) => $item['id'] ?? '?', $items);
        $selected = $this->choice("Selecciona la variante de '{$contextKey}':", $names, 0);

        foreach ($items as $item) {
            if (($item['id'] ?? '') === $selected) {
                return [$contextKey, $item];
            }
        }

        throw new \InvalidArgumentException("Variante '{$selected}' no encontrada.");
    }

    /**
     * Carga todos los contextos desde contexts.json.
     * 
     * ARQUITECTURA HÍBRIDA:
     *   - central, shared, tenant_shared → objetos únicos (acceso directo)
     *   - tenant → array de objetos (múltiples instancias)
     *
     * @throws \InvalidArgumentException Si no hay contexts.json o está vacío
     */
    private function loadContexts(): array
    {
        try {
            return ContextResolver::all();
        } catch (Throwable $e) {
            throw new \InvalidArgumentException(
                "No se pudo leer contexts.json: {$e->getMessage()}\n"
                . "Ejecuta: php artisan innodite:module-setup"
            );
        }
    }

    // ─── Helpers de orquestación ──────────────────────────────────────────────

    /**
     * Construye el FQCN del controlador para el contexto dado.
     * Con la nueva estructura de subfolder por entidad, el patrón es:
     *   Modules\{Module}\Http\Controllers\{ContextNs}\{Entity}\{Prefix}{Entity}Controller
     *
     * En make-module, entity = module (son el mismo nombre).
     * En add-entity, entity es diferente del module (se pasa explícitamente).
     *
     * @param  string  $moduleName   Nombre del módulo contenedor
     * @param  array   $contextItem  Configuración del contexto
     * @param  string|null  $entityName  Nombre de la entidad (por defecto igual a $moduleName)
     */
    private function buildControllerFqcn(string $moduleName, array $contextItem, ?string $entityName = null): string
    {
        $entity    = $entityName ?? $moduleName;
        $prefix    = $contextItem['class_prefix']   ?? '';
        $nsPath    = $contextItem['namespace_path'] ?? '';
        $className = "{$prefix}{$entity}Controller";

        $namespace = $nsPath
            ? "Modules\\{$moduleName}\\Http\\Controllers\\{$nsPath}\\{$entity}"
            : "Modules\\{$moduleName}\\Http\\Controllers\\{$entity}";

        return "{$namespace}\\{$className}";
    }

    /**
     * Derive la funcionalidad (prefijo de ruta) desde el nombre del módulo.
     * Sub-proceso atómico: User → users, InvoiceItem → invoice-items
     */
    private function resolveFunctionality(string $moduleName): string
    {
        return Str::kebab(Str::plural(Str::snake($moduleName)));
    }

    /**
     * Determina si el usuario pasó algún flag de componente individual.
     */
    private function hasIndividualFlags(): bool
    {
        return (bool) (
            $this->option('model')      ||
            $this->option('controller') ||
            $this->option('service')    ||
            $this->option('repository') ||
            $this->option('migration')  ||
            $this->option('request')
        );
    }

    /**
     * Elimina el directorio del módulo como parte de un rollback.
     */
    private function performRollback(string $modulePath): void
    {
        $this->components->task('Ejecutando rollback', function () use ($modulePath) {
            File::deleteDirectory($modulePath);
            return true;
        });

        $this->components->warn("Rollback completado. Los archivos de '{$modulePath}' fueron eliminados.");
    }

    // ─── Output visual ────────────────────────────────────────────────────────

    /**
     * Muestra la tabla de configuración antes de ejecutar.
     */
    private function displayConfigTable(
        string $moduleName,
        string $contextKey,
        string $contextId,
        string $functionality
    ): void {
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Módulo',        $moduleName],
                ['Contexto',      $contextKey],
                ['Variante',      $contextId],
                ['Prefijo ruta',  $functionality],
                ['Inyectar rutas', $this->option('no-routes') ? 'No' : 'Sí'],
            ]
        );
    }

    /**
     * Muestra el resumen final tras la generación exitosa.
     */
    private function displaySuccess(string $moduleName, string $contextKey, string $contextId): void
    {
        $this->components->info("Módulo <comment>{$moduleName}</comment> generado exitosamente.");
        $this->newLine();
        $this->line("  Próximos pasos:");
        $this->line("    1. Añade los marcadores en <comment>routes/web.php</comment> o <comment>routes/tenant.php</comment> si aún no los tienes.");
        $this->line("    2. Registra el Service Provider en <comment>bootstrap/providers.php</comment> si usas Laravel 11+.");
        $this->line("    3. Ejecuta <comment>php artisan migrate</comment> para crear las tablas.");
        $this->newLine();
    }
}
