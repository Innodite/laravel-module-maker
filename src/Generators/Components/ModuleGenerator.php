<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\ControllerGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\FactoryGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\MigrationGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\ModelGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\ProviderGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\RepositoryGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\RequestGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\RouteGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\SeederGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\TestGenerator;

class ModuleGenerator
{
    protected string $moduleName;
    protected string $modulePath;
    protected bool $isClean;
    protected ?array $config; // Configuración JSON para módulos dinámicos
    protected $command; // Referencia al objeto Command para mensajes de consola

    public function __construct(string $moduleName, bool $isClean = true, ?array $config = null, $command = null)
    {
        $this->moduleName = Str::studly($moduleName);
        $this->modulePath = config('make-module.module_path') . "/{$this->moduleName}";
        $this->isClean = $isClean;
        $this->config = $config;
        $this->command = $command; // Inyectamos el comando para poder usar info/error
    }

    /**
     * Resuelve la ruta del archivo de configuración, buscando en varias ubicaciones.
     *
     * @param string $configPath
     * @return string
     */
    public function resolveConfigPath(string $configPath): string
    {
        // 1. Prioridad: Buscar en una carpeta de configuración del módulo
        $moduleConfigPath = config('make-module.module_path') . "/{$this->moduleName}/config/{$configPath}";
        if (File::exists($moduleConfigPath)) {
            return $moduleConfigPath;
        }
        // 2. Segunda opción: Buscar en la carpeta de configuración del paquete (la que crea el setup)
        $packageConfigPath = config('make-module.module_path') . "/module-maker-config/{$configPath}";
        if (File::exists($packageConfigPath)) {
            return $packageConfigPath;
        }
        // 3. Tercera opción: Buscar en la carpeta 'config' de la raíz de la aplicación
        $rootConfigPath = base_path('config') . "/{$configPath}"; // Corregido para usar base_path('config')
        if (File::exists($rootConfigPath)) {
            return $rootConfigPath;
        }
        // Si no se encuentra, devolvemos la ruta original para que el error sea claro.
        return $configPath;
    }

    /**
     * Crea las carpetas base para el módulo.
     *
     * @return void
     */
    public function createFolders(): void
    {
        File::ensureDirectoryExists($this->modulePath);
        File::ensureDirectoryExists("{$this->modulePath}/config");
        File::ensureDirectoryExists("{$this->modulePath}/Http/Controllers");
        File::ensureDirectoryExists("{$this->modulePath}/Http/Requests");
        File::ensureDirectoryExists("{$this->modulePath}/Database/Migrations");
        File::ensureDirectoryExists("{$this->modulePath}/Database/Seeders");
        File::ensureDirectoryExists("{$this->modulePath}/Database/Factories");
        File::ensureDirectoryExists("{$this->modulePath}/routes");
        File::ensureDirectoryExists("{$this->modulePath}/Models");
        File::ensureDirectoryExists("{$this->modulePath}/Services/Contracts");
        File::ensureDirectoryExists("{$this->modulePath}/Repositories/Contracts");
        File::ensureDirectoryExists("{$this->modulePath}/Providers");
        File::ensureDirectoryExists("{$this->modulePath}/resources/views");
        File::ensureDirectoryExists("{$this->modulePath}/resources/lang");
        File::ensureDirectoryExists("{$this->modulePath}/Tests/Unit");

        if ($this->command) {
            $this->command->info("✅ Carpetas base creadas para el módulo '{$this->moduleName}'.");
        }
    }

    /**
     * Orquesta la creación de un módulo limpio sin contexto (estructura básica).
     * Úsalo cuando no hay contexts.json disponible o como fallback.
     *
     * @return void
     */
    public function createCleanModule(): void
    {
        $this->createFolders();

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
            $this->command->info("✅ Módulo '{$this->moduleName}' creado con éxito (Estructura básica).");
        }
    }

    /**
     * Orquesta la creación de un módulo limpio con contexto explícito.
     * Aplica la convención de nombres y carpetas según el contexto seleccionado.
     * Si el contexto tiene múltiples variantes, $contextName identifica cuál usar.
     *
     * @param  string       $contextKey    Clave del contexto (ej: 'central', 'tenant', 'tenant_shared')
     * @param  string       $functionality Nombre de la funcionalidad para el prefijo de ruta (ej: 'users')
     * @param  string|null  $contextName   Valor del campo 'name' del sub-contexto (ej: 'Energía España')
     * @return void
     */
    public function createCleanModuleWithContext(string $contextKey, string $functionality, ?string $contextName = null): void
    {
        $this->createFolders();

        $modelName = $this->moduleName;

        // El componentConfig le indica a cada generator en qué contexto operar
        $componentConfig = [
            'name'          => $modelName,
            'context'       => $contextKey,
            'context_name'  => $contextName,
            'functionality' => $functionality,
        ];

        (new ModelGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
        (new ControllerGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        (new ServiceGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        (new RepositoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        (new RequestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}StoreRequest", $componentConfig))->generate();
        (new ProviderGenerator($this->moduleName, $this->modulePath, true, [$componentConfig], $componentConfig))->generate();
        (new RouteGenerator($this->moduleName, $this->modulePath, true, $modelName, $componentConfig))->generate();
        (new MigrationGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
        (new SeederGenerator($this->moduleName, $this->modulePath, true, "{$modelName}Seeder"))->generate();
        (new FactoryGenerator($this->moduleName, $this->modulePath, true, $modelName, $modelName))->generate();
        (new TestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}Test"))->generate();

        if ($this->command) {
            $this->command->info("✅ Módulo '{$this->moduleName}' creado con éxito (contexto: {$contextKey}).");
        }
    }

    /**
     * Orquesta la creación de un módulo dinámico a partir de una configuración JSON.
     *
     * @return void
     * @throws \Exception Si el archivo de configuración no existe o es inválido.
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

        $components = $this->config['components'] ?? [];

        // El ProviderGenerator necesita la lista completa de componentes para generar los bindings
        (new ProviderGenerator($this->moduleName, $this->modulePath, false, $components))->generate();

        foreach ($components as $component) {
            $modelName     = Str::studly($component['name']);
            $requestName   = "{$modelName}StoreRequest";
            $migrationName = $modelName;
            $seederName    = "{$modelName}Seeder";
            $factoryName   = $modelName;
            $testName      = "{$modelName}Test";

            // componentConfig incluye 'context' y 'functionality' si están definidos en el JSON
            (new ModelGenerator($this->moduleName, $this->modulePath, false, $modelName, $component['attributes'] ?? [], $component['relations'] ?? [], [], $component))->generate();
            (new ControllerGenerator($this->moduleName, $this->modulePath, false, $modelName, $component))->generate();
            (new ServiceGenerator($this->moduleName, $this->modulePath, false, $modelName, $component))->generate();
            (new RepositoryGenerator($this->moduleName, $this->modulePath, false, $modelName, $component))->generate();
            (new RequestGenerator($this->moduleName, $this->modulePath, false, $requestName, $component))->generate();
            (new MigrationGenerator($this->moduleName, $this->modulePath, false, $migrationName, $component['attributes'] ?? [], $component['indexes'] ?? [], $component))->generate();
            (new SeederGenerator($this->moduleName, $this->modulePath, false, $seederName, $component))->generate();
            (new FactoryGenerator($this->moduleName, $this->modulePath, false, $factoryName, $modelName, $component))->generate();
            (new TestGenerator($this->moduleName, $this->modulePath, false, $testName, $component))->generate();
            (new RouteGenerator($this->moduleName, $this->modulePath, false, $modelName, $component))->generate();
        }

        if ($this->command) {
            $this->command->info("✅ Módulo '{$this->moduleName}' creado con éxito (Generación dinámica).");
        }
    }

    /**
     * Orquesta la creación de componentes individuales según los flags booleanos.
     * El nombre de cada archivo se deriva del nombre del módulo + prefijo del contexto.
     *
     * @param  array  $flags            Mapa de flags booleanos: model, controller, service, repository, migration, request
     * @param  array  $componentConfig  Contexto activo: context, context_name
     * @return void
     */
    public function createIndividualComponents(array $flags, array $componentConfig = []): void
    {
        $modelName = $this->moduleName;

        if ($flags['model'] ?? false) {
            // El modelo NO lleva prefijo de contexto
            (new ModelGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
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
            (new MigrationGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
        }

        if ($flags['request'] ?? false) {
            (new RequestGenerator($this->moduleName, $this->modulePath, true, "{$modelName}StoreRequest", $componentConfig))->generate();
        }

        if ($this->command) {
            $this->command->info("✅ Componentes creados en el módulo '{$this->moduleName}'.");
        }
    }
}
