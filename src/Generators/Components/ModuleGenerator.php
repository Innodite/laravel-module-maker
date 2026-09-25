<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Innodite\LaravelModuleMaker\Support\Disk;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\FactoryGenerator;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

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
     * The MODULE-level folders of the v5 tree — and nothing else.
     *
     * ⛔⛔ Until 25/09/2026 this still created the 3.x/4.x skeleton at the module root: every layer
     * (Http, Models, Repositories, Services, Jobs, Notifications, Console, Exceptions, resources,
     * Tests) with its context subfolders, `Shared` included — all EMPTY, next to the correct v5 tree
     * the generator writes inside each subfeature. `Tests/Feature/Shared` then made `innodite:doctor`
     * stop on a module the generator had just written (retired test shape), and every project module
     * carried those empty root folders (measured in innodite_microcredit, Branding).
     *
     * ⭐ In v5 the layers live in `Modules/<Module>/<SubFeature>/<Layer>/[<Context>/]`, created by the
     * pieces themselves. Only what belongs to the module as a whole is created here: its docs, its
     * routes, its providers and its application master seeders.
     */
    public function createFolders(): void
    {
        Disk::ensureDirectory("{$this->modulePath}/Docs");
        Disk::ensureDirectory("{$this->modulePath}/Routes");
        Disk::ensureDirectory("{$this->modulePath}/Providers");

        // ⛔ No master seeder folders here: the masters live in Database/Seeders/Application/<Context>/
        // and whoever writes them creates that folder. Creating Database/Seeders/<Context>/Application
        // left three empty folders of the old layout, `Shared` among them.

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
     * @param  string       $contextKey    Clave del contexto: 'central' o 'tenant'
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

        // ── Las carpetas de lo que el módulo puede necesitar, vacías ─────────
        //
        // `Jobs/`, `Notifications/`, `Console/Commands/` y `Exceptions/` se crean **sin un solo
        // archivo dentro**: la estructura enseña dónde va cada cosa, y el generador no siembra un
        // ejemplo que nadie pidió.
        //
        // ⛔ Antes se escribía un archivo de cada, y con un criterio distinto por contexto sin
        // motivo escrito: la central se llevaba las cuatro, el inquilino tres y otro contexto solo
        // una. Eran cuatro `if` a mano de los que ninguno explicaba por qué la central merecía una
        // excepción — y el resultado era un módulo con cuatro clases de ejemplo que había que
        // borrar a mano en cada subfuncionalidad de cada proyecto.
        $this->crearCarpetasDeApoyo($componentConfig);

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

        // ── Lo que completa la subfuncionalidad ───────────────────────────────
        //
        // Solo cuando la entidad nace **entera**. Con flags parciales —`-M` a secas, por ejemplo—
        // lo generado no es una subfuncionalidad todavía: es una pieza suelta, y su grupo de
        // pruebas nacería rojo señalando lo que el usuario aún no pidió. Una suite que arranca en
        // rojo por diseño es una suite que el equipo aprende a ignorar.
        //
        // Y cuando sí nace entera, va **todo**: es lo mismo que emite `make-module`, porque las dos
        // puertas por las que aparece una subfuncionalidad tienen que dejar lo mismo detrás. Si una
        // emite menos, el módulo termina con subfuncionalidades de primera y de segunda clase — y
        // lo que falta no da error, simplemente no está.
        //
        // ⛔ Aquí faltaban cuatro piezas, y tres se notaban en cuanto alguien intentaba usar la
        // subfuncionalidad:
        //
        //   · **Las rutas.** Sin ellas el controlador es inalcanzable: existe, está bien escrito y
        //     no hay una sola URL que llegue a él. Y es el caso NORMAL, no el raro: en un módulo de
        //     varias subfuncionalidades la primera entra por `make-module` y **todas las demás por
        //     aquí**.
        //   · **La vista.** No se escribía… pero la PRUEBA de la vista sí, y la importa. El grupo
        //     nacía rojo apuntando a un archivo que nadie había creado.
        //   · **El provider del contexto.** Sin él, los bindings de esta subfuncionalidad no se
        //     registran: la interfaz se resuelve sola y revienta en la primera petición.
        //   · **La factory**, que usan las pruebas para crear registros.
        if (! in_array(false, $flags, true)) {
            $modelo = $modelName;

            $this->run(new TestGenerator($this->moduleName, $this->modulePath, true, $componentConfig));
            $this->run(new FactoryGenerator($this->moduleName, $this->modulePath, true, $modelo, $modelo, $componentConfig));
            $this->run(new VueGenerator($this->moduleName, $this->modulePath, true, $modelo, $componentConfig));
            $this->run(new RouteGenerator($this->moduleName, $this->modulePath, true, $modelo, $componentConfig));
            $this->run(new ProviderGenerator(
                $this->moduleName,
                $this->modulePath,
                true,
                [$componentConfig + ['name' => $modelo]],
                $componentConfig
            ));

            $this->crearCarpetasDeApoyo($componentConfig);
        }

        if ($this->command) {
            $this->command->info("✅ Componentes creados en el módulo '{$this->moduleName}'.");
        }
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * Las carpetas que una subfuncionalidad puede necesitar, creadas vacías.
     *
     * Van **dentro de la subfuncionalidad y de su contexto**, como el resto de sus capas: un job
     * exporta *esos* registros y una excepción es de *esa* entidad, así que no pertenecen al módulo
     * entero.
     *
     * Llevan un `.gitkeep` porque una carpeta vacía no llega a un repositorio Git, y entonces la
     * estructura —que es justo lo que se quiere enseñar— no la vería nadie más que quien generó.
     */
    private function crearCarpetasDeApoyo(array $componentConfig): void
    {
        $sub    = (string) ($componentConfig['subFeature'] ?? '');
        $folder = $this->carpetaDeContexto((string) ($componentConfig['context'] ?? ''));

        foreach (['Jobs', 'Notifications', 'Console/Commands', 'Exceptions'] as $carpeta) {
            $ruta = $this->modulePath
                . ($sub !== '' ? "/{$sub}" : '')
                . "/{$carpeta}"
                . ($folder !== '' ? "/{$folder}" : '');

            Disk::ensureDirectory($ruta);

            // Por `Disk::put()` y no por `File::put()`: es lo que respeta el ensayo. Escribiendo
            // directo, `--dry-run` reventaba al no existir la carpeta que el ensayo no llegó a
            // crear — un ensayo que falla es peor que no tenerlo, porque el fallo no dice nada del
            // módulo que se iba a generar.
            if (! File::exists("{$ruta}/.gitkeep")) {
                Disk::put("{$ruta}/.gitkeep", '');
            }
        }
    }

    /** La carpeta del contexto —`Central`, `Tenant`— o cadena vacía si el modo no tiene eje. */
    private function carpetaDeContexto(string $contextKey): string
    {
        if ($contextKey === '' || ! ModuleMode::current()->hasContextAxis()) {
            return '';
        }

        try {
            return (string) (ContextResolver::resolve($contextKey)['folder'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }
}
