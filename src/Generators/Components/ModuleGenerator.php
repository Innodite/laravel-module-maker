<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Innodite\LaravelModuleMaker\Support\Disk;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\ConsoleCommandGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\ExceptionGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\FactoryGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\JobGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\NotificationGenerator;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;

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
 *   resources/js/Pages/ — Componentes Vue por contexto
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
        Disk::ensureDirectory("{$this->modulePath}/Docs");

        // ── Database ─────────────────────────────────────────────────────────
        foreach (['Factories', 'Migrations', 'Seeders'] as $sub) {
            $this->createContextSubfolders("Database/{$sub}");
        }

        // Los 3 maestros del módulo cuelgan de Database/Seeders/{Contexto}/Application/
        $this->createMasterSeederFolders();

        // ── Http ─────────────────────────────────────────────────────────────
        foreach (['Controllers', 'Requests'] as $sub) {
            $this->createContextSubfolders("Http/{$sub}");
        }
        Disk::ensureDirectory("{$this->modulePath}/Http/Middleware");

        // ── Models ───────────────────────────────────────────────────────────
        $this->createContextSubfolders('Models');

        // ── Providers ────────────────────────────────────────────────────────
        Disk::ensureDirectory("{$this->modulePath}/Providers");

        // ── Repositories: implementaciones + Contracts ────────────────────────
        $this->createContextSubfolders('Repositories');
        $this->createContextSubfolders('Repositories/Contracts');

        // ── resources/js/Pages ───────────────────────────────────────────────
        $this->createContextSubfolders('resources/js/Pages');

        // ── Routes (raíz del módulo, sin subcarpetas de contexto) ─────────────
        Disk::ensureDirectory("{$this->modulePath}/Routes");

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
        // Se saltaban el helper y escribian /Central a mano, asi que aparecian tambien en
        // single-app, donde ese contexto no existe.
        $this->createContextSubfolders('Exceptions');

        // ── Tests ────────────────────────────────────────────────────────────
        //
        // `Tests/Support` ya no se siembra: el soporte del grupo es su `{SubFunc}TestCase`, que vive
        // con las piezas que lo usan. Una carpeta vacía en el árbol no rompe nada, y por eso es peor
        // que un error: sugiere un sitio donde poner cosas que el contrato no contempla, y alguien
        // acaba poniéndolas.
        $this->createContextSubfolders('Tests/Feature');
        $this->createContextSubfolders('Tests/Unit');

        if ($this->command) {
            $this->command->info("✅ Estructura de carpetas creada para el módulo '{$this->moduleName}'.");
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
        Disk::ensureDirectory($docsPath);

        $date = now()->format('Y-m-d');

        $files = [
            'history.md' => "# {$this->moduleName} — Historial de Cambios\n\n## [{$date}] — Creación inicial\n- Módulo generado con `innodite:make-module`.\n",
            'architecture.md' => "# {$this->moduleName} — Decisiones de Arquitectura\n\n## Contexto\n_Describe aquí las decisiones técnicas y diagramas de flujo._\n",
            'schema.md' => "# {$this->moduleName} — Esquema de Base de Datos\n\n## Tablas\n_Diccionario de datos y relaciones de base de datos._\n",
        ];

        foreach ($files as $filename => $content) {
            $filePath = "{$docsPath}/{$filename}";
            if (!File::exists($filePath)) {
                Disk::put($filePath, $content);
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
            'subFeature'        => $modelName,
            'context'       => $contextKey,
            'context_id'    => $contextId,
            'functionality' => $functionality,
        ];

        // El modelo sí lleva contexto en v3: vive en Models/{ContextFolder}/
        $this->run(new ModelGenerator($this->moduleName, $this->modulePath, true, $modelName, [], [], [], $componentConfig));
        $this->run(new ControllerGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        $this->run(new ServiceGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        $this->run(new RepositoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        $this->run(new RequestGenerator($this->moduleName, $this->modulePath, true, $componentConfig));
        $this->run(new ProviderGenerator($this->moduleName, $this->modulePath, true, [$componentConfig], $componentConfig));
        $this->run(new RouteGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig));
        $this->run(new MigrationGenerator($this->moduleName, $this->modulePath, true, $modelName, [], [], $componentConfig));
        $this->run(new SubFeatureSeederGenerator($this->moduleName, $this->modulePath, true, $componentConfig));
        $this->run(new ModuleMasterSeederGenerator($this->moduleName, $this->modulePath, true, $componentConfig));
        $this->run(new FactoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $modelName, $componentConfig));
        $this->run(new TestGenerator($this->moduleName, $this->modulePath, true, $componentConfig));

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

        // Las rutas ya quedaron escritas por RouteGenerator, dentro del módulo. Aquí se inyectaba
        // además una segunda copia en el `routes/web.php` del proyecto —y se hacía **sin mirar
        // `--no-routes`**, así que esa opción nunca detuvo de verdad la inyección en el camino del
        // módulo completo. Retirada: el ServiceProvider del paquete carga Modules/*/Routes/ solo.

        if ($this->command) {
            $donde = $contextKey === '' && $contextId === ''
                ? ''
                : " (contexto: {$contextKey} / {$contextId})";
            $this->command->info("✅ Módulo '{$this->moduleName}' creado{$donde}.");
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

            // Garantizar que 'subFeature' está en el config para el subfolder por entidad
            if (!isset($component['subFeature'])) {
                $component['subFeature'] = $modelName;
            }

            $this->run(new ModelGenerator($this->moduleName, $this->modulePath, false, $modelName, $component['attributes'] ?? [], $component['relations'] ?? [], [], $component));
            $this->run(new ControllerGenerator($this->moduleName, $this->modulePath, false, $modelName, $component));
            $this->run(new ServiceGenerator($this->moduleName, $this->modulePath, false, $modelName, $component));
            $this->run(new RepositoryGenerator($this->moduleName, $this->modulePath, false, $modelName, $component));
            $this->run(new RequestGenerator($this->moduleName, $this->modulePath, false, $component));
            $this->run(new MigrationGenerator($this->moduleName, $this->modulePath, false, $modelName, $component['attributes'] ?? [], $component['indexes'] ?? [], $component));
            $this->run(new SubFeatureSeederGenerator($this->moduleName, $this->modulePath, false, $component));
            $this->run(new ModuleMasterSeederGenerator($this->moduleName, $this->modulePath, false, $component));
            $this->run(new FactoryGenerator($this->moduleName, $this->modulePath, false, $modelName, $modelName, $component));
            $this->run(new TestGenerator($this->moduleName, $this->modulePath, false, $component));
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

        // Garantizar que 'subFeature' está en componentConfig para el subfolder por entidad
        if (!isset($componentConfig['subFeature'])) {
            $componentConfig['subFeature'] = $modelName;
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

            // Las seis piezas van juntas o no van. El generador de migraciones deja escritas dos
            // —`MigrationsList` e `InlineAlters`—, y sin las otras cuatro quedan dos traits que no
            // llama nadie: métodos escritos y sin invocador, que es exactamente el defecto que esta
            // fase acaba de cerrar en el otro extremo. Una entidad con persistencia nace con su
            // despliegue entero, venga de `make-module` o de `add-entity`.
            $this->run(new SubFeatureSeederGenerator($this->moduleName, $this->modulePath, true, $componentConfig));
            $this->run(new ModuleMasterSeederGenerator($this->moduleName, $this->modulePath, true, $componentConfig));
        }

        if ($flags['request'] ?? false) {
            $this->run(new RequestGenerator($this->moduleName, $this->modulePath, true, $componentConfig));
        }

        // ── El grupo de pruebas de la subfuncionalidad ────────────────────────
        //
        // Solo cuando la entidad nace **entera**. Con flags parciales —`-M` a secas, por ejemplo—
        // lo generado no es una subfuncionalidad todavía: es una pieza suelta, y su grupo de
        // pruebas nacería rojo señalando lo que el usuario aún no pidió. Una suite que arranca en
        // rojo por diseño es una suite que el equipo aprende a ignorar.
        //
        // Y cuando sí nace entera, va: es el mismo grupo que emite `make-module`, porque las dos
        // puertas por las que aparece una subfuncionalidad tienen que dejar lo mismo detrás. Si una
        // emite menos, el módulo termina con subfuncionalidades de primera y de segunda clase — y
        // lo que falta no da error, simplemente no está.
        if (! in_array(false, $flags, true)) {
            $this->run(new TestGenerator($this->moduleName, $this->modulePath, true, $componentConfig));
        }

        if ($this->command) {
            $this->command->info("✅ Componentes creados en el módulo '{$this->moduleName}'.");
        }
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * Crea las subcarpetas de contexto base dentro de un tipo de componente — **si el modo las tiene**.
     *
     * En single-app no se crean: sembrar `Central/`, `Shared/` y `Tenant/Shared/` vacías en cada capa
     * de un proyecto sin tenants deja doce carpetas que no significan nada, y sugieren una estructura
     * que el modo dice que no existe. La capa se crea igual; lo que no se crea es el eje.
     *
     * @param  string  $componentType  Ruta relativa dentro del módulo (ej: 'Services', 'Http/Controllers')
     * @return void
     */
    private function createContextSubfolders(string $componentType): void
    {
        $base = "{$this->modulePath}/{$componentType}";
        Disk::ensureDirectory($base);

        if (! ModuleMode::current()->hasContextAxis()) {
            return;
        }

        foreach (self::BASE_CONTEXT_FOLDERS as $folder) {
            Disk::ensureDirectory("{$base}/{$folder}");
        }
    }

    /**
     * Crea la carpeta de los tres maestros `Application` del módulo.
     *
     * Es el punto de entrada único del módulo en su contexto, y por eso tiene carpeta propia en vez
     * de ser «una subfuncionalidad más»: `deploy-{contexto}` llama a estos tres, y estos hacen
     * fan-out en orden a las seis piezas de cada subfuncionalidad. Existe **uno por contexto** — el
     * central no arrastra al del tenant.
     *
     * Aquí se crea la carpeta y queda fijada la convención de nombres (ver SeederNames). El
     * contenido —el fan-out en orden y la propagación de `destructive`— es de FEAT-003: emitir ahora
     * tres seeders con `run()` vacío sería repetir B3, que es el hallazgo que esa fase corrige.
     */
    private function createMasterSeederFolders(): void
    {
        $base = "{$this->modulePath}/Database/Seeders";

        if (! ModuleMode::current()->hasContextAxis()) {
            Disk::ensureDirectory("{$base}/" . SeederNames::MASTER_FOLDER);

            return;
        }

        foreach (self::BASE_CONTEXT_FOLDERS as $folder) {
            Disk::ensureDirectory("{$base}/{$folder}/" . SeederNames::MASTER_FOLDER);
        }
    }
}
