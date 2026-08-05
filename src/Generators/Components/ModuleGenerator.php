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
     * Ejecuta un generador, conectándole antes la consola del comando.
     *
     * Sin esta línea, los 20 mensajes «✅ archivo creado» que los generadores escriben **no se
     * ven nunca**: `setOutput()` existía y no lo llamaba nadie, así que el usuario veía
     * «Creando estructura de archivos» y luego nada, sin saber qué se había escrito. El método
     * no era código sin propósito, era un cable sin conectar — y por eso se conecta en vez de
     * borrarse. El `--dry-run` de la fase 6 necesita exactamente esta vía para listar sin escribir.
     *
     * @param  object  $generator  Un generador de componente, con o sin consola propia
     */
    private function run(object $generator): void
    {
        if ($this->command && method_exists($generator, 'setOutput')) {
            $generator->setOutput($this->command->getOutput());
        }

        $generator->generate();
    }

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

        $this->run(new ModelGenerator($this->moduleName, $this->modulePath, true, $modelName));
        $this->run(new ControllerGenerator($this->moduleName, $this->modulePath, true, $modelName));
        $this->run(new ServiceGenerator($this->moduleName, $this->modulePath, true, $modelName));
        $this->run(new RepositoryGenerator($this->moduleName, $this->modulePath, true, $modelName));
        $this->run(new RequestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}StoreRequest"));
        $this->run(new ProviderGenerator($this->moduleName, $this->modulePath, true));
        $this->run(new RouteGenerator($this->moduleName, $this->modulePath, true, $modelName));
        $this->run(new MigrationGenerator($this->moduleName, $this->modulePath, true, $modelName));
        $this->run(new SeederGenerator($this->moduleName, $this->modulePath, true, "{$modelName}Seeder"));
        $this->run(new FactoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $modelName));
        $this->run(new TestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}Test"));

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
     * @param  string|null  $contextId   Valor del campo 'id' del sub-contexto
     * @return void
     */
    public function createCleanModuleWithContext(string $contextKey, string $functionality, ?string $contextId = null): void
    {
        $this->createFolders();
        $this->createDocs();

        $modelName = $this->moduleName;

        $componentConfig = [
            'name'          => $modelName,
            'entity'        => $modelName,
            'context'       => $contextKey,
            'context_id'    => $contextId,
            'functionality' => $functionality,
        ];

        // El modelo sí lleva contexto en v3: vive en Models/{ContextFolder}/
        $this->run(new ModelGenerator($this->moduleName, $this->modulePath, true, $modelName, [], [], [], $componentConfig));
        $this->run(new ControllerGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        $this->run(new ServiceGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        $this->run(new RepositoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        $this->run(new RequestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}StoreRequest", $componentConfig));
        $this->run(new ProviderGenerator($this->moduleName, $this->modulePath, true, [$componentConfig], $componentConfig));
        $this->run(new RouteGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        $this->run(new MigrationGenerator($this->moduleName, $this->modulePath, true, $modelName, [], [], $componentConfig));
        $this->run(new SeederGenerator($this->moduleName, $this->modulePath, true, "{$modelName}Seeder", $componentConfig));
        $this->run(new FactoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $modelName, $componentConfig));
        $this->run(new TestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}Test", $componentConfig));

        // ── Vistas Vue (axios + Inertia solo para navegación) ─────────────────
        $this->run(new VueGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));

        // ── Generadores extendidos según tipo de contexto ─────────────────────
        $isCentral      = ($contextKey === 'central');
        $isTenantShared = ($contextKey === 'tenant_shared');
        $isTenantSpecific = ($contextKey === 'tenant');

        // Resolver el array de contexto para pasarlo a los nuevos generadores
        try {
            $resolvedContext = $contextId !== null
                ? ContextResolver::resolveById($contextKey, $contextId)
                : ContextResolver::resolve($contextKey);
        } catch (\InvalidArgumentException) {
            $resolvedContext = [];
        }

        // Jobs (Central, TenantShared, TenantName)
        if (($isCentral || $isTenantShared || $isTenantSpecific) && !empty($resolvedContext)) {
            $this->run(new JobGenerator($resolvedContext, $this->modulePath, $this->moduleName));
        }

        // Notifications (Central, TenantName)
        if (($isCentral || $isTenantSpecific) && !empty($resolvedContext)) {
            $this->run(new NotificationGenerator($resolvedContext, $this->modulePath, $this->moduleName));
        }

        // Console Commands (Central, TenantName)
        if (($isCentral || $isTenantSpecific) && !empty($resolvedContext)) {
            $this->run(new ConsoleCommandGenerator($resolvedContext, $this->modulePath, $this->moduleName));
        }

        // Exceptions (solo Central)
        if ($isCentral && !empty($resolvedContext)) {
            $this->run(new ExceptionGenerator($resolvedContext, $this->modulePath, $this->moduleName));
        }

        // ── Inyectar rutas en el proyecto ─────────────────────────────────────
        $this->injectRoutes($contextKey, $contextId, $componentConfig);

        if ($this->command) {
            $this->command->info("✅ Módulo '{$this->moduleName}' creado (contexto: {$contextKey} / {$contextId}).");
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

        $this->run(new ProviderGenerator($this->moduleName, $this->modulePath, false, $components));

        foreach ($components as $component) {
            $modelName   = Str::studly($component['name']);
            $requestName = "{$modelName}StoreRequest";

            // Garantizar que 'entity' está en el config para el subfolder por entidad
            if (!isset($component['entity'])) {
                $component['entity'] = $modelName;
            }

            $this->run(new ModelGenerator($this->moduleName, $this->modulePath, false, $modelName, $component['attributes'] ?? [], $component['relations'] ?? [], [], $component));
            $this->run(new ControllerGenerator($this->moduleName, $this->modulePath, false, $modelName, $component));
            $this->run(new ServiceGenerator($this->moduleName, $this->modulePath, false, $modelName, $component));
            $this->run(new RepositoryGenerator($this->moduleName, $this->modulePath, false, $modelName, $component));
            $this->run(new RequestGenerator($this->moduleName, $this->modulePath, false, $requestName, $component));
            $this->run(new MigrationGenerator($this->moduleName, $this->modulePath, false, $modelName, $component['attributes'] ?? [], $component['indexes'] ?? [], $component));
            $this->run(new SeederGenerator($this->moduleName, $this->modulePath, false, "{$modelName}Seeder", $component));
            $this->run(new FactoryGenerator($this->moduleName, $this->modulePath, false, $modelName, $modelName, $component));
            $this->run(new TestGenerator($this->moduleName, $this->modulePath, false, "{$modelName}Test", $component));
            $this->run(new RouteGenerator($this->moduleName, $this->modulePath, false, $modelName, $component));
        }

        if ($this->command) {
            $this->command->info("✅ Módulo '{$this->moduleName}' creado (generación dinámica).");
        }
    }

    /**
     * Crea componentes individuales según los flags booleanos.
     *
     * @param  array        $flags            Mapa de flags: model, controller, service, repository, migration, request
     * @param  array        $componentConfig  Contexto activo: context, context_id
     * @param  string|null  $entityName       Nombre de la entidad (por defecto: igual al módulo)
     * @return void
     */
    public function createIndividualComponents(array $flags, array $componentConfig = [], ?string $entityName = null): void
    {
        $modelName = $entityName ?? $this->moduleName;

        // Garantizar que 'entity' está en componentConfig para el subfolder por entidad
        if (!isset($componentConfig['entity'])) {
            $componentConfig['entity'] = $modelName;
        }

        if ($flags['model'] ?? false) {
            $this->run(new ModelGenerator($this->moduleName, $this->modulePath, true, $modelName, [], [], [], $componentConfig));
        }

        if ($flags['controller'] ?? false) {
            $this->run(new ControllerGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        }

        if ($flags['service'] ?? false) {
            $this->run(new ServiceGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        }

        if ($flags['repository'] ?? false) {
            $this->run(new RepositoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        }

        if ($flags['migration'] ?? false) {
            $this->run(new MigrationGenerator($this->moduleName, $this->modulePath, true, $modelName, [], [], $componentConfig));
        }

        if ($flags['request'] ?? false) {
            $this->run(new RequestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}StoreRequest", $componentConfig));
        }

        // Si se generó un controller, inyectar (o actualizar) las rutas
        if (($flags['controller'] ?? false) && !empty($componentConfig['context'])) {
            $this->injectRoutes(
                $componentConfig['context'],
                $componentConfig['context_id'] ?? null,
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
     * @param  string|null  $contextId  ID del contexto
     * @param  array   $componentConfig  Configuración del componente
     * @return void
     */
    private function injectRoutes(string $contextKey, ?string $contextId, array $componentConfig): void
    {
        try {
            $contextConfig = $contextId
                ? ContextResolver::resolveById($contextKey, $contextId)
                : ContextResolver::resolve($contextKey);
        } catch (\InvalidArgumentException) {
            return;
        }

        // El controlador usa la entidad (puede diferir del módulo en add-entity)
        $entityName      = $componentConfig['entity'] ?? $this->moduleName;
        $controllerClass = ($contextConfig['class_prefix'] ?? '') . $entityName . 'Controller';
        $nsPath          = $contextConfig['namespace_path'] ?? '';
        $controllerNs    = $nsPath
            ? "Modules\\{$this->moduleName}\\Http\\Controllers\\{$nsPath}\\{$entityName}"
            : "Modules\\{$this->moduleName}\\Http\\Controllers\\{$entityName}";
        $controllerFqcn  = "{$controllerNs}\\{$controllerClass}";

        $injector = new RouteInjectionService($this->command);
        $injector->inject(
            contextKey:     $contextKey,
            entityName:     $this->moduleName,
            contextId:      $contextId ?? '',
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
}
