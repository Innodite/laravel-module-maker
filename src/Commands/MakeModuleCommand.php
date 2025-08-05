<?php

// Innodite\LaravelModuleMaker\Commands\MakeModuleCommand.php
// Código corregido para el comando de creación de módulos.

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'innodite:make-module {name}
                             {--model= : Nombre del modelo a crear}
                             {--controller= : Nombre del controlador a crear}
                             {--request= : Nombre del request a crear}
                             {--service= : Nombre del servicio a crear}
                             {--repository= : Nombre del repositorio a crear}
                             {--migration= : Nombre de la migración a crear}
                             {--config= : Archivo de configuración JSON para la generación dinámica}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un módulo base, un componente individual o un módulo dinámico.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $module = Str::studly($this->argument('name'));
        $modulePath = config('make-module.module_path')."/{$module}";

        // Lógica para crear componentes individuales
        if ($this->option('model') || $this->option('controller') || $this->option('request') || $this->option('service') || $this->option('repository') || $this->option('migration')) {
            if (!File::exists($modulePath)) {
                $this->error("El módulo '{$module}' no existe. No se puede crear un componente en él.");
                return Command::FAILURE;
            }
            $this->createIndividualComponents($module, $modulePath);
            return Command::SUCCESS;
        }

        // Si el módulo ya existe y no se piden componentes individuales, mostramos un error
        if (File::exists($modulePath)) {
            $this->error("El módulo '{$module}' ya existe. Usa las opciones para añadir componentes.");
            return Command::FAILURE;
        }

        // Lógica para crear un módulo completo (limpio o dinámico)
        $configPath = $this->option('config');
        if ($configPath) {
            $this->createDynamicModule($module, $modulePath, $configPath);
        } else {
            $this->createCleanModule($module, $modulePath);
        }

        return Command::SUCCESS;
    }

    /**
     * Crea los componentes individuales especificados por las opciones.
     *
     * @param string $module
     * @param string $modulePath
     * @return void
     */
    protected function createIndividualComponents(string $module, string $modulePath): void
    {
        // Al crear componentes individuales, se usa la lógica del modo 'limpio'
        $isClean = true;
        if ($modelName = $this->option('model')) {
            $this->createModel($module, $modulePath, Str::studly($modelName), $isClean);
        }
        if ($controllerName = $this->option('controller')) {
            $this->createController($module, $modulePath, Str::studly($controllerName), $isClean);
        }
        if ($requestName = $this->option('request')) {
            $this->createRequest($module, $modulePath, Str::studly($requestName), $isClean);
        }
        if ($serviceName = $this->option('service')) {
            $this->createServiceAndInterface($module, $modulePath, Str::studly($serviceName), $isClean);
        }
        if ($repositoryName = $this->option('repository')) {
            $this->createRepositoryAndInterface($module, $modulePath, Str::studly($repositoryName), $isClean);
        }
        if ($migrationName = $this->option('migration')) {
            $this->createMigration($module, $modulePath, Str::studly($migrationName), [], $isClean);
        }
        $this->info("✅ Componentes individuales creados con éxito en el módulo '{$module}'.");
    }

    /**
     * Crea un módulo completo y vacío.
     *
     * @param string $module
     * @param string $modulePath
     * @return void
     */
    protected function createCleanModule(string $module, string $modulePath): void
    {
        $modelName = Str::studly($module);
        $controllerName = "{$modelName}Controller";
        $isClean = true;

        $this->createFolders($modulePath);

        $this->createModel($module, $modulePath, $modelName, $isClean);
        $this->createController($module, $modulePath, $controllerName, $isClean);
        $this->createServiceAndInterface($module, $modulePath, $modelName . 'Service', $isClean);
        $this->createRepositoryAndInterface($module, $modulePath, $modelName . 'Repository', $isClean);
        $this->createRequest($module, $modulePath, $modelName . 'StoreRequest', $isClean);
        // Los archivos de idioma y vistas se generarán por el comando de setup.
        $this->createProvider($module, $modulePath, $modelName, $isClean);
        $this->createRoutes($module, $modulePath, $controllerName, $isClean);
        $this->createMigration($module, $modulePath, $modelName, [], $isClean);
        $this->createSeeder($module, $modulePath, $modelName, $isClean);
        $this->createFactory($module, $modulePath, $modelName, $isClean);
        $this->createTest($module, $modulePath, $modelName . 'Test', $isClean);

        $this->info("✅ Módulo '{$module}' creado con éxito (Estructura limpia).");
    }

    /**
     * Crea un módulo dinámicamente a partir de un archivo de configuración JSON.
     *
     * @param string $moduleName
     * @param string $modulePath
     * @param string $configPath
     * @return void
     */
    protected function createDynamicModule(string $moduleName, string $modulePath, string $configPath): void
    {
        $resolvedConfigPath = $this->resolveConfigPath($configPath);

        if (!File::exists($resolvedConfigPath)) {
            $this->error("El archivo de configuración '{$configPath}' no existe.");
            return;
        }

        $config = json_decode(File::get($resolvedConfigPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Error al parsear el archivo JSON: " . json_last_error_msg());
            return;
        }

        $moduleNameFromConfig = Str::studly($config['module_name'] ?? $moduleName);
        $components = $config['components'] ?? [];

        $this->createFolders($modulePath);
        
        $isClean = false;
        $this->createProvider($moduleNameFromConfig, $modulePath, $moduleNameFromConfig, $isClean);

        foreach ($components as $component) {
            $modelName = Str::studly($component['name']);
            $this->createModuleComponent($moduleNameFromConfig, $modulePath, $component);
            $this->createRoutes($moduleNameFromConfig, $modulePath, "{$modelName}Controller", $isClean);
        }

        $this->info("✅ Módulo '{$moduleNameFromConfig}' creado con éxito (Generación dinámica).");
    }

    /**
     * Resuelve la ruta del archivo de configuración, buscando en varias ubicaciones
     * para adaptarse a la estructura modular.
     *
     * @param string $configPath
     * @return string
     */
    protected function resolveConfigPath(string $configPath): string
    {
        // 1. Prioridad: Buscar en una carpeta de configuración del módulo
        $moduleName = Str::studly($this->argument('name'));
        $moduleConfigPath = config('make-module.module_path')."/{$moduleName}/config/{$configPath}";
        if (File::exists($moduleConfigPath)) {
            return $moduleConfigPath;
        }

        // 2. Segunda opción: Buscar en la carpeta de configuración del paquete (la que crea el setup)
        $packageConfigPath = config('make-module.module_path')."/module-maker-config/{$configPath}";
        if (File::exists($packageConfigPath)) {
             return $packageConfigPath;
        }

        // 3. Tercera opción: Buscar en la carpeta 'config' de la raíz de la aplicación
        $rootConfigPath = config('make-module.module_path')."/../config/{$configPath}";
        if (File::exists($rootConfigPath)) {
            return $rootConfigPath;
        }

        // Si no se encuentra, devolvemos la ruta original para que el error sea claro.
        return $configPath;
    }

    /**
     * Itera sobre la configuración de un componente para crear sus archivos.
     *
     * @param string $module
     * @param string $modulePath
     * @param array $componentConfig
     * @return void
     */
    protected function createModuleComponent(string $module, string $modulePath, array $componentConfig): void
    {
        $modelName = Str::studly($componentConfig['name']);
        $isClean = false;
        
        $this->createModel($module, $modulePath, $modelName, $isClean, $componentConfig);
        $this->createController($module, $modulePath, "{$modelName}Controller", $isClean, $modelName);
        $this->createServiceAndInterface($module, $modulePath, "{$modelName}Service", $isClean, $modelName);
        $this->createRepositoryAndInterface($module, $modulePath, "{$modelName}Repository", $isClean, $modelName);
        $this->createRequest($module, $modulePath, "{$modelName}StoreRequest", $isClean);
        $this->createMigration($module, $modulePath, $modelName, $componentConfig['attributes'] ?? [], $isClean);
        $this->createSeeder($module, $modulePath, $modelName, $isClean);
        $this->createFactory($module, $modulePath, $modelName, $isClean);
        $this->createTest($module, $modulePath, $modelName . 'Test', $isClean);
    }

    /**
     * Crea todas las carpetas necesarias para un módulo.
     *
     * @param string $modulePath
     * @return void
     */
    protected function createFolders(string $modulePath): void
    {
        $folders = [
            'Http/Controllers', 'Http/Requests', 'Models',
            'Repositories/Contracts', 'Services/Contracts',
            'Providers', 'Routes', 'resources/views',
            'resources/lang/en', 'resources/lang/es',
            'Database/Migrations', 'Database/Seeders', 'Database/Factories',
            'Tests/Unit', 'Tests/Feature',
        ];

        foreach ($folders as $folder) {
            File::makeDirectory("{$modulePath}/{$folder}", 0755, true);
        }
    }

    /**
     * Obtiene el contenido de un stub y reemplaza los placeholders.
     *
     * @param string $stubName
     * @param bool $isClean
     * @param array $replacements
     * @return string
     */
    protected function getStubContent(string $stubName, bool $isClean, array $replacements = []): string
    {
        $stubFolder = $isClean ? 'clean' : 'dynamic';
        $customStubPath = config('make-module.stubs.path')."/{$stubFolder}/{$stubName}";
        
        if (File::exists($customStubPath)) {
            $stubPath = $customStubPath;
        } else {
            // Se corrige la ruta de fallback para que sea consistente con el setup
            $packageStubsPath = dirname(__DIR__, 2) . '/stubs';
            $stubPath = "{$packageStubsPath}/{$stubFolder}/{$stubName}";
        }

        if (!File::exists($stubPath)) {
            $this->error("El stub '{$stubName}' no existe en ninguna de las ubicaciones para el modo '{$stubFolder}'.");
            return '';
        }

        $content = File::get($stubPath);
        foreach ($replacements as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $content = str_replace("{{ {$key} }}", $value, $content);
        }

        return $content;
    }

    /**
     * Genera la estructura de la migración a partir de los atributos.
     *
     * @param array $attributes
     * @return string
     */
    protected function generateMigrationSchema(array $attributes): string
    {
        $schema = '';
        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $type = $attribute['type'];
            $nullable = $attribute['nullable'] ?? false;
            
            $column = "\$table->{$type}('{$name}')";
            if ($nullable) {
                $column .= "->nullable()";
            }
            $schema .= "            {$column};\n";
        }
        return rtrim($schema);
    }

    /**
     * Genera los métodos de relación del modelo a partir de la configuración.
     *
     * @param array $relationships
     * @return string
     */
    protected function generateRelationships(array $relationships): string
    {
        $rel = '';
        foreach ($relationships as $relationship) {
            $type = $relationship['type'];
            $model = $relationship['model'];
            $rel .= "    public function " . Str::camel($model) . "()\n";
            $rel .= "    {\n";
            $rel .= "        return \$this->{$type}(" . Str::studly($model) . "::class);\n";
            $rel .= "    }\n";
        }
        return rtrim($rel);
    }

    protected function createModel(string $module, string $path, string $modelName, bool $isClean, array $config = []): void
    {
        $stubFile = 'model.stub';
        // Se corrige la lógica para obtener las relaciones de la configuración del componente
        $relationships = $this->generateRelationships($config['relationships'] ?? []);
        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Models",
            'modelName' => $modelName,
            'relationships' => $relationships,
        ]);
        File::put("{$path}/Models/{$modelName}.php", $stub);
        $this->info("✅ Modelo {$modelName}.php creado en Modules/{$module}/Models");
    }

    protected function createController(string $module, string $path, string $controllerName, bool $isClean, string $modelName = ''): void
    {
        $stubFile = 'controller.stub';
        $serviceName = Str::studly(str_replace('Controller', '', $controllerName));
        $serviceInterface = "{$serviceName}ServiceInterface";
        $serviceInstance = Str::camel($serviceName);

        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Http\\Controllers",
            'controllerName' => $controllerName,
            'serviceInterface' => $serviceInterface,
            'serviceInstance' => $serviceInstance,
            'module' => $module,
        ]);
        File::put("{$path}/Http/Controllers/{$controllerName}.php", $stub);
        $this->info("✅ Controlador {$controllerName}.php creado en Modules/{$module}/Http/Controllers");
    }

    protected function createRequest(string $module, string $path, string $requestName, bool $isClean): void
    {
        $stubFile = 'request.stub';
        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Http\\Requests",
            'requestName' => $requestName,
        ]);
        File::put("{$path}/Http/Requests/{$requestName}.php", $stub);
        $this->info("✅ Request {$requestName}.php creado en Modules/{$module}/Http/Requests");
    }

    protected function createServiceAndInterface(string $module, string $path, string $serviceName, bool $isClean, string $modelName = ''): void
    {
        $serviceInterfaceName = "{$serviceName}Interface";
        $repositoryInterfaceName = str_replace('Service', 'RepositoryInterface', $serviceName);
        $repositoryInstance = Str::camel(str_replace('Service', 'Repository', $serviceName));

        $serviceInterfaceStubFile = 'service_interface.stub';
        $serviceInterfaceStub = $this->getStubContent($serviceInterfaceStubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Services\\Contracts",
            'serviceInterfaceName' => $serviceInterfaceName,
        ]);
        File::put("{$path}/Services/Contracts/{$serviceInterfaceName}.php", $serviceInterfaceStub);
        $this->info("✅ Contrato {$serviceInterfaceName} creado en Modules/{$module}/Services/Contracts");

        $serviceStubFile = 'service_implementation.stub';
        $serviceStub = $this->getStubContent($serviceStubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Services",
            'repositoryInterfaceName' => $repositoryInterfaceName,
            'serviceInterfaceName' => $serviceInterfaceName,
            'modelName' => $modelName,
            'repositoryInstance' => $repositoryInstance,
            'module' => $module,
        ]);
        File::put("{$path}/Services/{$serviceName}.php", $serviceStub);
        $this->info("✅ Servicio {$serviceName}.php creado en Modules/{$module}/Services");
    }

    protected function createRepositoryAndInterface(string $module, string $path, string $repositoryName, bool $isClean, string $modelName = ''): void
    {
        $repositoryInterfaceName = "{$repositoryName}Interface";

        $repositoryInterfaceStubFile = 'repository_interface.stub';
        $repositoryInterfaceStub = $this->getStubContent($repositoryInterfaceStubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Repositories\\Contracts",
            'repositoryInterfaceName' => $repositoryInterfaceName,
        ]);
        File::put("{$path}/Repositories/Contracts/{$repositoryInterfaceName}.php", $repositoryInterfaceStub);
        $this->info("✅ Contrato {$repositoryInterfaceName} creado en Modules/{$module}/Repositories/Contracts");

        $repositoryStubFile = 'repository_implementation.stub';
        $repositoryStub = $this->getStubContent($repositoryStubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Repositories",
            'modelName' => $modelName,
            'repositoryInterfaceName' => $repositoryInterfaceName,
            'module' => $module,
        ]);
        File::put("{$path}/Repositories/{$repositoryName}.php", $repositoryStub);
        $this->info("✅ Repositorio {$repositoryName}.php creado en Modules/{$module}/Repositories");
    }

    protected function createProvider(string $module, string $path, string $modelName, bool $isClean): void
    {
        $providerDir = config('make-module.module_path')."/{$module}/Providers";
        File::ensureDirectoryExists($providerDir);
        $stubFile = 'provider.stub';
        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Providers",
            'module' => $module,
            'modelName' => $modelName,
        ]);
        File::put("{$providerDir}/{$module}ServiceProvider.php", $stub);
        $this->info("✅ Service Provider {$module}ServiceProvider.php creado en Modules/{$module}/Providers");
    }

    protected function createRoutes(string $module, string $path, string $controllerName, bool $isClean): void
    {
        $routesDir = config('make-module.module_path')."/{$module}/Routes";
        File::ensureDirectoryExists($routesDir);
        $stub = $this->getStubContent('route-api.stub', $isClean, [
            'namespace' => "Modules\\{$module}\\Http\\Controllers",
            'module' => $module,
            'controllerName' => $controllerName,
        ]);
        File::put("{$routesDir}/api.php", $stub);
        $this->info("✅ Archivo de rutas API creado en Modules/{$module}/Routes");

        $stubWeb = $this->getStubContent('route-web.stub', $isClean, [
            'namespace' => "Modules\\{$module}\\Http\\Controllers",
            'module' => $module,
            'controllerName' => $controllerName,
        ]);
        File::put("{$routesDir}/web.php", $stubWeb);
        $this->info("✅ Archivo de rutas web creado en Modules/{$module}/Routes");
    }

    protected function createMigration(string $module, string $path, string $migrationModelName, array $attributes = [], bool $isClean = false): void
    {
        $timestamp = now()->format('Y_m_d_His');
        $filenameBase = 'create_' . Str::snake(Str::plural($migrationModelName)) . '_table';
        $filename = "{$timestamp}_{$filenameBase}.php";
        $migrationPath = config('make-module.module_path')."/{$module}/Database/Migrations";
        File::ensureDirectoryExists($migrationPath);

        $tableName = Str::snake(Str::plural($migrationModelName));
        $migrationClassName = Str::studly($filenameBase);

        $stubFile = 'migration.stub';
        $schema = $this->generateMigrationSchema($attributes);

        $stub = $this->getStubContent($stubFile, $isClean, [
            'tableName' => $tableName,
            'schema' => $schema,
            'migrationClassName' => $migrationClassName
        ]);
        File::put("{$migrationPath}/{$filename}", $stub);
        $this->info("✅ Migración {$filename} creada en Modules/{$module}/Database/Migrations");
    }

    protected function createSeeder(string $module, string $path, string $seederName, bool $isClean): void
    {
        $seederDir = config('make-module.module_path')."/{$module}/Database/Seeders";
        File::ensureDirectoryExists($seederDir);
        $stubFile = 'seeder.stub';
        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Database\\Seeders",
            'seederName' => $seederName,
            'module' => $module,
        ]);
        File::put("{$seederDir}/{$seederName}Seeder.php", $stub);
        $this->info("✅ Seeder {$seederName}Seeder.php creado en Modules/{$module}/Database/Seeders");
    }

    protected function createFactory(string $module, string $path, string $factoryName, bool $isClean): void
    {
        $factoryDir = config('make-module.module_path')."/{$module}/Database/Factories";
        File::ensureDirectoryExists($factoryDir);
        $stubFile = 'factory.stub';
        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Database\\Factories",
            'factoryName' => $factoryName,
            'modelName' => $factoryName,
        ]);
        File::put("{$factoryDir}/{$factoryName}Factory.php", $stub);
        $this->info("✅ Factory {$factoryName}Factory.php creado en Modules/{$module}/Database/Factories");
    }

    protected function createTest(string $module, string $path, string $testName, bool $isClean): void
    {
        $testDir = config('make-module.module_path')."/{$module}/Tests/Unit";
        File::ensureDirectoryExists($testDir);
        $stubFile = 'test.stub';
        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Tests\\Unit",
            'testName' => $testName,
        ]);
        File::put("{$testDir}/{$testName}.php", $stub);
        $this->info("✅ Test {$testName}.php creado en Modules/{$module}/Tests/Unit");
    }
}