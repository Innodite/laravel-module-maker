<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\ConsoleCommandGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\ExceptionGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\FactoryGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\JobGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\NotificationGenerator;
use Innodite\LaravelModuleMaker\Services\RouteInjectionService;
use Innodite\LaravelModuleMaker\Support\ContextResolver;

/**
 * Orquesta la creación de un módulo completo según la arquitectura v3.0.0.
 *
 * Estructura generada por contexto:
 *   Docs/               — history.md, architecture.md, schema.md
 *   Database/           — Factories/, Migrations/, Seeders/ (con subcarpetas de contexto)
 *   Http/               — Controllers/, Middleware/, Requests/ (con subcarpetas de contexto)
 *   Models/             — Subcarpetas de contexto
 *   Providers/
 *   Repositories/       — Implementaciones + Contracts/ (ambos con subcarpetas de contexto)
 *   Resources/js/Pages/ — Componentes Vue por contexto
 *   Routes/             — web.php, tenant.php, api.php
 *   Services/           — Implementaciones + Contracts/ (ambos con subcarpetas de contexto)
 *   Tests/Unit/
 */
class ModuleGenerator
{
    protected string $moduleName;
    protected string $modulePath;
    protected bool $isClean;
    protected ?array $config;
    protected $command;

    /**
     * Subcarpetas de contexto base que se crean en todas las capas.
     * Las carpetas de tenant específico se crean on-demand por cada generator.
     */
    private const BASE_CONTEXT_FOLDERS = [
        'Central',
        'Shared',
        'Tenant/Shared',
    ];

    public function __construct(string $moduleName, bool $isClean = true, ?array $config = null, $command = null)
    {
        $this->moduleName = Str::studly($moduleName);
        $this->modulePath = config('make-module.module_path') . "/{$this->moduleName}";
        $this->isClean    = $isClean;
        $this->config     = $config;
        $this->command    = $command;
    }

    // ─── Helpers de creación de estructura ───────────────────────────────────

    /**
     * Crea la estructura de carpetas completa v3.0.0.
     * Incluye subcarpetas de contexto base (Central, Shared, Tenant/Shared)
     * en todas las capas que lo requieren.
     *
     * @return void
     */
    public function createFolders(): void
    {
        // ── Docs (sin segregación de contexto) ───────────────────────────────
        File::ensureDirectoryExists("{$this->modulePath}/Docs");

        // ── Database ─────────────────────────────────────────────────────────
        foreach (['Factories', 'Migrations', 'Seeders'] as $sub) {
            $this->createContextSubfolders("Database/{$sub}");
        }

        // ── Http ─────────────────────────────────────────────────────────────
        foreach (['Controllers', 'Requests'] as $sub) {
            $this->createContextSubfolders("Http/{$sub}");
        }
        File::ensureDirectoryExists("{$this->modulePath}/Http/Middleware");

        // ── Models ───────────────────────────────────────────────────────────
        $this->createContextSubfolders('Models');

        // ── Providers ────────────────────────────────────────────────────────
        File::ensureDirectoryExists("{$this->modulePath}/Providers");

        // ── Repositories: implementaciones + Contracts ────────────────────────
        $this->createContextSubfolders('Repositories');
        $this->createContextSubfolders('Repositories/Contracts');

        // ── Resources/js/Pages ───────────────────────────────────────────────
        $this->createContextSubfolders('Resources/js/Pages');

        // ── Routes (raíz del módulo, sin subcarpetas de contexto) ─────────────
        File::ensureDirectoryExists("{$this->modulePath}/Routes");

        // ── Services: implementaciones + Contracts ────────────────────────────
        $this->createContextSubfolders('Services');
        $this->createContextSubfolders('Services/Contracts');

        // ── Jobs ─────────────────────────────────────────────────────────────
        $this->createContextSubfolders('Jobs');

        // ── Notifications ─────────────────────────────────────────────────────
        $this->createContextSubfolders('Notifications');

        // ── Console/Commands ──────────────────────────────────────────────────
        $this->createContextSubfolders('Console/Commands');

        // ── Exceptions ───────────────────────────────────────────────────────
        File::ensureDirectoryExists("{$this->modulePath}/Exceptions/Central");

        // ── Tests ────────────────────────────────────────────────────────────
        $this->createContextSubfolders('Tests/Feature');
        $this->createContextSubfolders('Tests/Unit');
        File::ensureDirectoryExists("{$this->modulePath}/Tests/Support/Central");

        if ($this->command) {
            $this->command->info("✅ Estructura de carpetas v3.0.0 creada para el módulo '{$this->moduleName}'.");
        }
    }

    /**
     * Crea los tres archivos maestros de documentación del módulo.
     * Estos archivos son la única carpeta no segregada por contexto.
     *
     * → TAREA DELEGABLE A AGENTE OPERATIVO:
     *   Esta sección puede ser procesada por un agente operativo usando la siguiente instrucción:
     *   "Crea los archivos history.md, architecture.md y schema.md dentro de
     *   Modules/{ModuleName}/Docs/ con las plantillas de encabezado Markdown definidas
     *   en stubs/contextual/docs/ del paquete."
     *
     * @return void
     */
    public function createDocs(): void
    {
        $docsPath = "{$this->modulePath}/Docs";
        File::ensureDirectoryExists($docsPath);

        $date = now()->format('Y-m-d');

        $files = [
            'history.md' => "# {$this->moduleName} — Historial de Cambios\n\n## [{$date}] — Creación inicial\n- Módulo generado con `innodite:make-module`.\n",
            'architecture.md' => "# {$this->moduleName} — Decisiones de Arquitectura\n\n## Contexto\n_Describe aquí las decisiones técnicas y diagramas de flujo._\n",
            'schema.md' => "# {$this->moduleName} — Esquema de Base de Datos\n\n## Tablas\n_Diccionario de datos y relaciones de base de datos._\n",
        ];

        foreach ($files as $filename => $content) {
            $filePath = "{$docsPath}/{$filename}";
            if (!File::exists($filePath)) {
                File::put($filePath, $content);
            }
        }

        if ($this->command) {
            $this->command->info("✅ Docs/ creados: history.md, architecture.md, schema.md");
        }
    }

    // ─── Orchestrators ────────────────────────────────────────────────────────

    /**
     * Crea un módulo limpio sin contexto (fallback cuando no hay contexts.json).
     *
     * @return void
     */
    public function createCleanModule(): void
    {
        $this->createFolders();
        $this->createDocs();

        $modelName = $this->moduleName;

        (new ModelGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
        (new ControllerGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
        (new ServiceGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
        (new RepositoryGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
        (new RequestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}StoreRequest"))->generate();
        (new ProviderGenerator($this->moduleName, $this->modulePath, true))->generate();
        (new RouteGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
        (new MigrationGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
        (new SeederGenerator($this->moduleName, $this->modulePath, true, "{$modelName}Seeder"))->generate();
        (new FactoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $modelName))->generate();
        (new TestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}Test"))->generate();

        if ($this->command) {
            $this->command->info("✅ Módulo '{$this->moduleName}' creado (estructura básica sin contexto).");
        }
    }

    /**
     * Crea un módulo limpio con contexto explícito.
     * Aplica prefijos de clase y subcarpetas según el contexto seleccionado.
     *
     * @param  string       $contextKey    Clave del contexto (ej: 'central', 'tenant', 'tenant_shared')
     * @param  string       $functionality Nombre funcional para prefijo de ruta (ej: 'users')
     * @param  string|null  $contextName   Valor del campo 'name' del sub-contexto
     * @return void
     */
    public function createCleanModuleWithContext(string $contextKey, string $functionality, ?string $contextName = null): void
    {
        $this->createFolders();
        $this->createDocs();

        $modelName = $this->moduleName;

        $componentConfig = [
            'name'          => $modelName,
            'context'       => $contextKey,
            'context_name'  => $contextName,
            'functionality' => $functionality,
        ];

        // El modelo sí lleva contexto en v3: vive en Models/{ContextFolder}/
        (new ModelGenerator($this->moduleName, $this->modulePath, true, $modelName, [], [], [], $componentConfig))->generate();
        (new ControllerGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        (new ServiceGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        (new RepositoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        (new RequestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}StoreRequest", $componentConfig))->generate();
        (new ProviderGenerator($this->moduleName, $this->modulePath, true, [$componentConfig], $componentConfig))->generate();
        (new RouteGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        (new MigrationGenerator($this->moduleName, $this->modulePath, true, $modelName, [], [], $componentConfig))->generate();
        (new SeederGenerator($this->moduleName, $this->modulePath, true, "{$modelName}Seeder", $componentConfig))->generate();
        (new FactoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $modelName, $componentConfig))->generate();
        (new TestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}Test", $componentConfig))->generate();

        // ── Vistas Vue (axios + Inertia solo para navegación) ─────────────────
        (new VueGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();

        // ── Generadores extendidos según tipo de contexto ─────────────────────
        $isCentral      = ($contextKey === 'central');
        $isTenantShared = ($contextKey === 'tenant_shared');
        $isTenantSpecific = ($contextKey === 'tenant');

        // Resolver el array de contexto para pasarlo a los nuevos generadores
        try {
            $resolvedContext = $contextName !== null
                ? ContextResolver::resolveItem($contextKey, $contextName)
                : ContextResolver::resolve($contextKey);
        } catch (\InvalidArgumentException) {
            $resolvedContext = [];
        }

        // Jobs (Central, TenantShared, TenantName)
        if (($isCentral || $isTenantShared || $isTenantSpecific) && !empty($resolvedContext)) {
            (new JobGenerator($resolvedContext, $this->modulePath, $this->moduleName))->generate();
        }

        // Notifications (Central, TenantName)
        if (($isCentral || $isTenantSpecific) && !empty($resolvedContext)) {
            (new NotificationGenerator($resolvedContext, $this->modulePath, $this->moduleName))->generate();
        }

        // Console Commands (Central, TenantName)
        if (($isCentral || $isTenantSpecific) && !empty($resolvedContext)) {
            (new ConsoleCommandGenerator($resolvedContext, $this->modulePath, $this->moduleName))->generate();
        }

        // Exceptions (solo Central)
        if ($isCentral && !empty($resolvedContext)) {
            (new ExceptionGenerator($resolvedContext, $this->modulePath, $this->moduleName))->generate();
        }

        // ── Inyectar rutas en el proyecto ─────────────────────────────────────
        $this->injectRoutes($contextKey, $contextName, $componentConfig);

        if ($this->command) {
            $this->command->info("✅ Módulo '{$this->moduleName}' creado (contexto: {$contextKey} / {$contextName}).");
        }
    }

    /**
     * Crea un módulo dinámico a partir de una configuración JSON.
     *
     * @return void
     */
    public function createDynamicModule(): void
    {
        if (!$this->config) {
            if ($this->command) {
                $this->command->error("No se proporcionó configuración para el módulo dinámico.");
            }
            return;
        }

        $this->createFolders();
        $this->createDocs();

        $components = $this->config['components'] ?? [];

        (new ProviderGenerator($this->moduleName, $this->modulePath, false, $components))->generate();

        foreach ($components as $component) {
            $modelName   = Str::studly($component['name']);
            $requestName = "{$modelName}StoreRequest";

            (new ModelGenerator($this->moduleName, $this->modulePath, false, $modelName, $component['attributes'] ?? [], $component['relations'] ?? [], [], $component))->generate();
            (new ControllerGenerator($this->moduleName, $this->modulePath, false, $modelName, $component))->generate();
            (new ServiceGenerator($this->moduleName, $this->modulePath, false, $modelName, $component))->generate();
            (new RepositoryGenerator($this->moduleName, $this->modulePath, false, $modelName, $component))->generate();
            (new RequestGenerator($this->moduleName, $this->modulePath, false, $requestName, $component))->generate();
            (new MigrationGenerator($this->moduleName, $this->modulePath, false, $modelName, $component['attributes'] ?? [], $component['indexes'] ?? [], $component))->generate();
            (new SeederGenerator($this->moduleName, $this->modulePath, false, "{$modelName}Seeder", $component))->generate();
            (new FactoryGenerator($this->moduleName, $this->modulePath, false, $modelName, $modelName, $component))->generate();
            (new TestGenerator($this->moduleName, $this->modulePath, false, "{$modelName}Test", $component))->generate();
            (new RouteGenerator($this->moduleName, $this->modulePath, false, $modelName, $component))->generate();
        }

        if ($this->command) {
            $this->command->info("✅ Módulo '{$this->moduleName}' creado (generación dinámica).");
        }
    }

    /**
     * Crea componentes individuales según los flags booleanos.
     *
     * @param  array  $flags            Mapa de flags: model, controller, service, repository, migration, request
     * @param  array  $componentConfig  Contexto activo: context, context_name
     * @return void
     */
    public function createIndividualComponents(array $flags, array $componentConfig = []): void
    {
        $modelName = $this->moduleName;

        if ($flags['model'] ?? false) {
            (new ModelGenerator($this->moduleName, $this->modulePath, true, $modelName, [], [], [], $componentConfig))->generate();
        }

        if ($flags['controller'] ?? false) {
            (new ControllerGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        }

        if ($flags['service'] ?? false) {
            (new ServiceGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        }

        if ($flags['repository'] ?? false) {
            (new RepositoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        }

        if ($flags['migration'] ?? false) {
            (new MigrationGenerator($this->moduleName, $this->modulePath, true, $modelName, [], [], $componentConfig))->generate();
        }

        if ($flags['request'] ?? false) {
            (new RequestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}StoreRequest", $componentConfig))->generate();
        }

        // Si se generó un controller, inyectar (o actualizar) las rutas
        if (($flags['controller'] ?? false) && !empty($componentConfig['context'])) {
            $this->injectRoutes(
                $componentConfig['context'],
                $componentConfig['context_name'] ?? null,
                $componentConfig
            );
        }

        if ($this->command) {
            $this->command->info("✅ Componentes creados en el módulo '{$this->moduleName}'.");
        }
    }

    // ─── Inyección de rutas en el proyecto ───────────────────────────────────

    /**
     * Inyecta las rutas del módulo en los archivos de rutas del proyecto.
     * Solo se ejecuta si el contexto tiene configuración de ruta en contexts.json.
     *
     * @param  string  $contextKey      Clave del contexto
     * @param  string|null  $contextName  Nombre del sub-contexto
     * @param  array   $componentConfig  Configuración del componente
     * @return void
     */
    private function injectRoutes(string $contextKey, ?string $contextName, array $componentConfig): void
    {
        try {
            $contextConfig = $contextName
                ? ContextResolver::resolveItem($contextKey, $contextName)
                : ContextResolver::resolve($contextKey);
        } catch (\InvalidArgumentException) {
            return;
        }

        // El controlador principal del módulo es el del contexto activo
        $controllerClass = ($contextConfig['class_prefix'] ?? '') . $this->moduleName . 'Controller';
        $nsPath          = $contextConfig['namespace_path'] ?? '';
        $controllerNs    = $nsPath
            ? "Modules\\{$this->moduleName}\\Http\\Controllers\\{$nsPath}"
            : "Modules\\{$this->moduleName}\\Http\\Controllers";
        $controllerFqcn  = "{$controllerNs}\\{$controllerClass}";

        $injector = new RouteInjectionService($this->command);
        $injector->inject(
            contextKey:     $contextKey,
            entityName:     $this->moduleName,
            contextName:    $contextName ?? '',
            controllerFqcn: $controllerFqcn,
            contextConfig:  $contextConfig
        );
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * Crea las subcarpetas de contexto base (Central, Shared, Tenant/Shared)
     * dentro de un tipo de componente dado.
     *
     * @param  string  $componentType  Ruta relativa dentro del módulo (ej: 'Services', 'Http/Controllers')
     * @return void
     */
    private function createContextSubfolders(string $componentType): void
    {
        $base = "{$this->modulePath}/{$componentType}";
        File::ensureDirectoryExists($base);

        foreach (self::BASE_CONTEXT_FOLDERS as $folder) {
            File::ensureDirectoryExists("{$base}/{$folder}");
        }
    }

    /**
     * Resuelve la ruta del archivo de configuración (mantenido por retrocompatibilidad).
     *
     * @param  string  $configPath
     * @return string
     */
    public function resolveConfigPath(string $configPath): string
    {
        $moduleConfigPath = config('make-module.module_path') . "/{$this->moduleName}/config/{$configPath}";
        if (File::exists($moduleConfigPath)) {
            return $moduleConfigPath;
        }

        $packageConfigPath = config('make-module.config_path') . "/{$configPath}";
        if (File::exists($packageConfigPath)) {
            return $packageConfigPath;
        }

        return base_path("config/{$configPath}");
    }
}
