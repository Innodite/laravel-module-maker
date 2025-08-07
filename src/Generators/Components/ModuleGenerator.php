<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;


use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\ControllerGenerator;
use Innodite\LaravelModuleMaker\Generators\Components\FactoryGenerator;
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
    protected function createFolders(): void
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
     * Orquesta la creación de un módulo limpio.
     *
     * @return void
     */
    public function createCleanModule(): void
    {
        $this->createFolders();

        $modelName = $this->moduleName;
        $controllerName = "{$modelName}Controller";
        $serviceName = "{$modelName}Service";
        $repositoryName = "{$modelName}Repository";
        $requestName = "{$modelName}StoreRequest";
        $migrationName = $modelName;
        $seederName = $modelName;
        $factoryName = $modelName;
        $testName = "{$modelName}Test";

        (new ModelGenerator($this->moduleName, $this->modulePath, true, $modelName))->generate();
        (new ControllerGenerator($this->moduleName, $this->modulePath, true, $controllerName, $modelName))->generate();
        (new ServiceGenerator($this->moduleName, $this->modulePath, true, $serviceName, $modelName))->generate();
        (new RepositoryGenerator($this->moduleName, $this->modulePath, true, $repositoryName, $modelName))->generate();
        (new RequestGenerator($this->moduleName, $this->modulePath, true, $requestName))->generate();
        (new ProviderGenerator($this->moduleName, $this->modulePath, true, $this->moduleName))->generate(); // Para módulo limpio, no pasamos componentes
        (new RouteGenerator($this->moduleName, $this->modulePath, true, $controllerName))->generate();
        (new MigrationGenerator($this->moduleName, $this->modulePath, true, $migrationName))->generate();
        (new SeederGenerator($this->moduleName, $this->modulePath, true, $seederName))->generate();
        (new FactoryGenerator($this->moduleName, $this->modulePath, true, $factoryName, $modelName))->generate();
        (new TestGenerator($this->moduleName, $this->modulePath, true, $testName))->generate();

        if ($this->command) {
            $this->command->info("✅ Módulo '{$this->moduleName}' creado con éxito (Estructura limpia).");
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
        (new ProviderGenerator($this->moduleName, $this->modulePath, false, $this->moduleName, $components))->generate();

        foreach ($components as $component) {
            $modelName = Str::studly($component['name']);
            $controllerName = "{$modelName}Controller";
            $serviceName = "{$modelName}Service";
            $repositoryName = "{$modelName}Repository";
            $requestName = "{$modelName}StoreRequest";
            $migrationName = $modelName;
            $seederName = $modelName;
            $factoryName = $modelName;
            $testName = "{$modelName}Test";

            (new ModelGenerator($this->moduleName, $this->modulePath, false, $modelName, $component))->generate();
            (new ControllerGenerator($this->moduleName, $this->modulePath, false, $controllerName, $modelName))->generate();
            (new ServiceGenerator($this->moduleName, $this->modulePath, false, $serviceName, $modelName))->generate();
            (new RepositoryGenerator($this->moduleName, $this->modulePath, false, $repositoryName, $modelName))->generate();
            (new RequestGenerator($this->moduleName, $this->modulePath, false, $requestName))->generate();
            (new MigrationGenerator($this->moduleName, $this->modulePath, false, $migrationName, $component['attributes'] ?? []), $component['indexes'] ?? [])->generate();
            (new SeederGenerator($this->moduleName, $this->modulePath, false, $seederName))->generate();
            (new FactoryGenerator($this->moduleName, $this->modulePath, false, $factoryName, $modelName))->generate();
            (new TestGenerator($this->moduleName, $this->modulePath, false, $testName))->generate();
            (new RouteGenerator($this->moduleName, $this->modulePath, false, $controllerName))->generate();
        }

        if ($this->command) {
            $this->command->info("✅ Módulo '{$this->moduleName}' creado con éxito (Generación dinámica).");
        }
    }

    /**
     * Orquesta la creación de componentes individuales.
     *
     * @param array $options Las opciones pasadas al comando.
     * @return void
     */
    public function createIndividualComponents(array $options): void
    {
        // Al crear componentes individuales, se usa la lógica del modo 'limpio'
        $isClean = true;
        
        $modelName = Str::studly($options['model'] ?? '');
        $controllerName = Str::studly($options['controller'] ?? '');
        $requestName = Str::studly($options['request'] ?? '');
        $serviceName = Str::studly($options['service'] ?? '');
        $repositoryName = Str::studly($options['repository'] ?? '');
        $migrationName = Str::studly($options['migration'] ?? '');

        if ($modelName) {
            (new ModelGenerator($this->moduleName, $this->modulePath, $isClean, $modelName))->generate();
        }
        if ($controllerName) {
            (new ControllerGenerator($this->moduleName, $this->modulePath, $isClean, $controllerName, $modelName))->generate();
        }
        if ($requestName) {
            (new RequestGenerator($this->moduleName, $this->modulePath, $isClean, $requestName))->generate();
        }
        if ($serviceName) {
            (new ServiceGenerator($this->moduleName, $this->modulePath, $isClean, $serviceName, $modelName))->generate();
        }
        if ($repositoryName) {
            (new RepositoryGenerator($this->moduleName, $this->modulePath, $isClean, $repositoryName, $modelName))->generate();
        }
        if ($migrationName) {
            (new MigrationGenerator($this->moduleName, $this->modulePath, $isClean, $migrationName))->generate();
        }
        
        if ($this->command) {
            $this->command->info("✅ Componentes individuales creados con éxito en el módulo '{$this->moduleName}'.");
        }
    }
}
