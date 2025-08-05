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

        // Pasamos los componentes para que el provider se genere correctamente
        $this->createProvider($moduleNameFromConfig, $modulePath, $moduleNameFromConfig, $isClean, $components);

        foreach ($components as $component) {
            $modelName = Str::studly($component['name']);
            $this->createModel($moduleNameFromConfig, $modulePath, $modelName, $isClean, $component);
            $this->createController($moduleNameFromConfig, $modulePath, "{$modelName}Controller", $isClean, $modelName);
            $this->createServiceAndInterface($moduleNameFromConfig, $modulePath, "{$modelName}Service", $isClean, $modelName);
            $this->createRepositoryAndInterface($moduleNameFromConfig, $modulePath, "{$modelName}Repository", $isClean, $modelName);
            $this->createRequest($moduleNameFromConfig, $modulePath, "{$modelName}StoreRequest", $isClean);
            $this->createMigration($moduleNameFromConfig, $modulePath, $modelName, $component['attributes'] ?? [], $isClean);
            $this->createSeeder($moduleNameFromConfig, $modulePath, $modelName, $isClean);
            $this->createFactory($moduleNameFromConfig, $modulePath, $modelName, $isClean);
            $this->createTest($moduleNameFromConfig, $modulePath, $modelName . 'Test', $isClean);
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
     * Crea un Service Provider para el módulo.
     *
     * @param string $module
     * @param string $path
     * @param string $providerName
     * @param bool $isClean
     * @param array $components
     * @return void
     */
    protected function createProvider(string $module, string $path, string $providerName, bool $isClean, array $components = []): void
    {
        $providerDir = config('make-module.module_path')."/{$module}/Providers";
        File::ensureDirectoryExists($providerDir);

        $stubFile = 'provider.stub';
        $modelImports = '';
        $modelBindings = '';

        // Generar los imports y bindings si el módulo es dinámico y tiene componentes
        if (!$isClean && !empty($components)) {
            foreach ($components as $component) {
                $modelName = Str::studly($component['name']);
                $modelImports .= "use Modules\\{$module}\\Services\\Contracts\\{$modelName}ServiceInterface;\n";
                $modelImports .= "use Modules\\{$module}\\Services\\{$modelName}Service;\n";
                $modelImports .= "use Modules\\{$module}\\Repositories\\Contracts\\{$modelName}RepositoryInterface;\n";
                $modelImports .= "use Modules\\{$module}\\Repositories\\{$modelName}Repository;\n";
                $modelBindings .= "        \$this->app->bind(\n";
                $modelBindings .= "            {$modelName}ServiceInterface::class,\n";
                $modelBindings .= "            {$modelName}Service::class\n";
                $modelBindings .= "        );\n";
                $modelBindings .= "        \$this->app->bind(\n";
                $modelBindings .= "            {$modelName}RepositoryInterface::class,\n";
                $modelBindings .= "            {$modelName}Repository::class\n";
                $modelBindings .= "        );\n";
            }
        }

        $stub = $this->getStubContent($stubFile, $isClean, [
            'module' => $module,
            'modelImports' => trim($modelImports),
            'modelBindings' => trim($modelBindings),
        ]);

        File::put("{$providerDir}/{$module}ServiceProvider.php", $stub);
        $this->info("✅ Provider {$module}ServiceProvider.php creado en Modules/{$module}/Providers");
    }

    /**
     * Obtiene el contenido de un stub y reemplaza los marcadores de posición.
     *
     * @param string $stubFile
     * @param bool $isClean
     * @param array $placeholders
     * @return string
     */
    protected function getStubContent(string $stubFile, bool $isClean, array $placeholders = []): string
    {
        $stub = $this->getStub($stubFile, $isClean);
        return $this->replacePlaceholders($stub, $placeholders);
    }

    /**
     * Obtiene el contenido del archivo stub.
     *
     * @param string $stubFile
     * @param bool $isClean
     * @return string
     */
    protected function getStub(string $stubFile, bool $isClean): string
    {
        $stubPath = $this->getStubPath($stubFile, $isClean);
        if (!File::exists($stubPath)) {
            $this->error("El archivo stub '{$stubFile}' no se encuentra en '{$stubPath}'.");
            return '';
        }
        return File::get($stubPath);
    }

    /**
     * Resuelve la ruta completa del archivo stub.
     *
     * @param string $stubFile
     * @param bool $isClean
     * @return string
     */
    protected function getStubPath(string $stubFile, bool $isClean): string
    {
        $baseStubPath = config('make-module.stubs.path');
        $subfolder = $isClean ? 'clean' : 'dynamic';
        return "{$baseStubPath}/{$subfolder}/{$stubFile}";
    }

    /**
     * Reemplaza los marcadores de posición en el contenido del stub.
     *
     * @param string $stub
     * @param array $placeholders
     * @return string
     */
    protected function replacePlaceholders(string $stub, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $stub = str_replace("{{ {$key} }}", $value, $stub);
        }
        return $stub;
    }

    protected function createFolders(string $path): void
    {
        File::ensureDirectoryExists($path);
        File::ensureDirectoryExists("{$path}/config");
        File::ensureDirectoryExists("{$path}/Http/Controllers");
        File::ensureDirectoryExists("{$path}/Http/Requests");
        File::ensureDirectoryExists("{$path}/Database/Migrations");
        File::ensureDirectoryExists("{$path}/Database/Seeders");
        File::ensureDirectoryExists("{$path}/Database/Factories");
        File::ensureDirectoryExists("{$path}/routes");
        File::ensureDirectoryExists("{$path}/Models");
        // Nueva estructura para Services e Interfaces (Contracts)
        File::ensureDirectoryExists("{$path}/Services/Contracts");
        File::ensureDirectoryExists("{$path}/Repositories/Contracts");
        File::ensureDirectoryExists("{$path}/Providers");
        File::ensureDirectoryExists("{$path}/resources/views");
        File::ensureDirectoryExists("{$path}/resources/lang");
        File::ensureDirectoryExists("{$path}/Tests/Unit");
    }

    /**
     * Crea un modelo para el módulo.
     *
     * @param string $module
     * @param string $path
     * @param string $modelName
     * @param bool $isClean
     * @param array $component
     * @return void
     */
    protected function createModel(string $module, string $path, string $modelName, bool $isClean, array $component = []): void
    {
        $modelDir = config('make-module.module_path')."/{$module}/Models";
        File::ensureDirectoryExists($modelDir);

        $stubFile = 'model.stub';
        $fillable = $this->getFillableForModel($component);
        // Pasamos el nombre del módulo a la función de relaciones
        $relationships = $this->getRelationshipsForModel($component, $module);

        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Models",
            'modelName' => $modelName,
            'fillable' => $fillable,
            'relationships' => $relationships,
            'module' => $module,
        ]);
        File::put("{$modelDir}/{$modelName}.php", $stub);
        $this->info("✅ Modelo {$modelName}.php creado en Modules/{$module}/Models");
    }

    protected function getFillableForModel(array $component): string
    {
        if (empty($component['attributes'])) {
            return '';
        }

        $fillable = collect($component['attributes'])
            ->filter(fn ($attr) => $attr['type'] !== 'relationship')
            ->map(fn ($attr) => "'" . $attr['name'] . "'")
            ->implode(', ');

        return $fillable ? "protected \$fillable = [{$fillable}];" : '';
    }

    /**
     * Genera el código para los métodos de relación del modelo.
     *
     * @param array $component
     * @param string $module
     * @return string
     */
    protected function getRelationshipsForModel(array $component, string $module): string
    {
        if (empty($component['attributes'])) {
            return '';
        }

        $relationships = collect($component['attributes'])
            ->filter(fn ($attr) => $attr['type'] === 'relationship')
            ->map(function ($attr) use ($component, $module) {
                $relationship = $attr['relationship'];
                $modelName = Str::studly($relationship['model']);
                $methodName = Str::camel($attr['name']);
                $type = $relationship['type'];

                // Usamos el nombre del módulo pasado como parámetro
                $relatedModelClass = "Modules\\{$module}\\Models\\{$modelName}";

                // Construye el código de la relación
                $code = "    /**\n";
                $code .= "     * Get the {$attr['name']} associated with the {$component['name']}.\n";
                $code .= "     */\n";
                $code .= "    public function {$methodName}(): \\Illuminate\\Database\\Eloquent\\Relations\\{$type}\n";
                $code .= "    {\n";
                $code .= "        return \$this->{$type}(" . $relatedModelClass . "::class);\n";
                $code .= "    }\n\n";

                return $code;
            })
            ->implode('');

        return $relationships;
    }

    protected function createController(string $module, string $path, string $controllerName, bool $isClean, string $modelName = null): void
    {
        $controllerDir = config('make-module.module_path')."/{$module}/Http/Controllers";
        File::ensureDirectoryExists($controllerDir);

        $stubFile = 'controller.stub';
        $modelStudly = Str::studly($modelName ?? $module);
        $serviceInterface = "{$modelStudly}ServiceInterface";
        $serviceInstance = Str::camel($modelStudly) . 'Service';

        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' =>  "Modules\\{$module}\\Http\\Controllers",
            'controllerName' => $controllerName,
            'modelName' => $modelStudly,
            'module' => $module,
            'serviceInterface' => $serviceInterface,
            'serviceInstance' => $serviceInstance,
        ]);

        File::put("{$controllerDir}/{$controllerName}.php", $stub);
        $this->info("✅ Controlador {$controllerName}.php creado en Modules/{$module}/Http/Controllers");
    }

    protected function createRequest(string $module, string $path, string $requestName, bool $isClean): void
    {
        $requestDir = config('make-module.module_path')."/{$module}/Http/Requests";
        File::ensureDirectoryExists($requestDir);

        $stubFile = 'request.stub';
        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Http\\Requests",
            'requestName' => $requestName,
        ]);
        File::put("{$requestDir}/{$requestName}.php", $stub);
        $this->info("✅ Request {$requestName}.php creado en Modules/{$module}/Http/Requests");
    }

    protected function createServiceAndInterface(string $module, string $path, string $serviceName, bool $isClean, string $modelName = null): void
    {
        $serviceDir = config('make-module.module_path')."/{$module}/Services";
        File::ensureDirectoryExists($serviceDir);
        $serviceContractDir = "{$serviceDir}/Contracts";
        File::ensureDirectoryExists($serviceContractDir);

        $modelNameStudly = Str::studly($modelName ?? Str::replaceLast('Service', '', $serviceName));

        // Crear la interfaz primero
        $stubFileInterface = 'service-interface.stub';
        $serviceInterfaceName = "{$serviceName}Interface";
        $stubInterface = $this->getStubContent($stubFileInterface, $isClean, [
            'namespace' => "Modules\\{$module}\\Services\\Contracts",
            'serviceInterfaceName' => $serviceInterfaceName,
            'modelName' => $modelNameStudly,
            'module' => $module,
        ]);
        File::put("{$serviceContractDir}/{$serviceInterfaceName}.php", $stubInterface);
        $this->info("✅ Interfaz {$serviceInterfaceName}.php creada en Modules/{$module}/Services/Contracts");

        // Crear la implementación del servicio
        $stubFileService = 'service.stub';
        $repositoryName = Str::replaceLast('Service', 'Repository', $serviceName);
        $repositoryInterfaceName = "{$repositoryName}Interface";
        $repositoryInstance = Str::camel($repositoryName);
        
        $stubService = $this->getStubContent($stubFileService, $isClean, [
            'namespace' => "Modules\\{$module}\\Services",
            'serviceName' => $serviceName,
            'modelName' => $modelNameStudly,
            'module' => $module,
            'serviceInterfaceName'=>$serviceInterfaceName,
            'repositoryInterfaceName' => $repositoryInterfaceName,
            'repositoryInstance' => $repositoryInstance,
        ]);
        File::put("{$serviceDir}/{$serviceName}.php", $stubService);
        $this->info("✅ Servicio {$serviceName}.php creado en Modules/{$module}/Services");
    }

    protected function createRepositoryAndInterface(string $module, string $path, string $repositoryName, bool $isClean, string $modelName = null): void
    {
        $repositoryDir = config('make-module.module_path')."/{$module}/Repositories";
        File::ensureDirectoryExists($repositoryDir);
        $repositoryContractDir = "{$repositoryDir}/Contracts";
        File::ensureDirectoryExists($repositoryContractDir);
        
        $modelNameStudly = Str::studly($modelName ?? $module); // Obtener el nombre del modelo
        $repositoryInterfaceName = "{$repositoryName}Interface";

        // Crear la interfaz primero
        $stubFileInterface = 'repository-interface.stub';
        $stubInterface = $this->getStubContent($stubFileInterface, $isClean, [
            'namespace' => "Modules\\{$module}\\Repositories\\Contracts",
            'repositoryInterfaceName' => $repositoryInterfaceName,
            'modelName' => $modelNameStudly,
            'module' => $module,
        ]);
        File::put("{$repositoryContractDir}/{$repositoryInterfaceName}.php", $stubInterface);
        $this->info("✅ Interfaz {$repositoryInterfaceName}.php creada en Modules/{$module}/Repositories/Contracts");

        // Crear la implementación del repositorio
        $stubFileRepository = 'repository.stub';
        // Generar el nombre de la variable del modelo en camelCase
        $modelNameLowerCase = Str::camel($modelNameStudly);
        
        $stubRepository = $this->getStubContent($stubFileRepository, $isClean, [
            'namespace' => "Modules\\{$module}\\Repositories",
            'repositoryName' => $repositoryName,
            'modelName' => $modelNameStudly,
            'module' => $module,
            'repositoryInterfaceName' => $repositoryInterfaceName,
            'modelNameLowerCase' => $modelNameLowerCase,
        ]);
        File::put("{$repositoryDir}/{$repositoryName}.php", $stubRepository);
        $this->info("✅ Repositorio {$repositoryName}.php creado en Modules/{$module}/Repositories");
    }

    protected function createMigration(string $module, string $path, string $migrationName, array $attributes = [], bool $isClean = true): void
    {
        $migrationDir = config('make-module.module_path')."/{$module}/Database/Migrations";
        File::ensureDirectoryExists($migrationDir);

        $stubFile = 'migration.stub';
        $tableName = Str::snake(Str::plural($migrationName));
        $className = 'Create' . Str::studly($tableName) . 'Table';
        $tableSchema = $this->getMigrationSchema($attributes);

        $stub = $this->getStubContent($stubFile, $isClean, [
            'className' => $className,
            'tableName' => $tableName,
            'tableSchema' => $tableSchema,
        ]);

        File::put("{$migrationDir}/".date('Y_m_d_His')."_create_{$tableName}_table.php", $stub);
        $this->info("✅ Migración '{$tableName}' creada en Modules/{$module}/Database/Migrations");
    }

    protected function getMigrationSchema(array $attributes): string
    {
        if (empty($attributes)) {
            return "\$table->id();\n \$table->timestamps();";
        }

        $schema = "\$table->id();\n";
        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $type = $attribute['type'];
            $nullable = $attribute['nullable'] ?? false;
            $length = $attribute['length'] ?? null;
            $references = $attribute['references'] ?? null;
            $on = $attribute['on'] ?? null;

            if ($type === 'relationship') {
                continue;
            }

            if ($type === 'foreignId') {
                $schema .= "\$table->foreignId('{$name}')";
                if ($references && $on) {
                    $schema .= "->constrained('{$on}')";
                }
                if ($nullable) {
                    $schema .= "->nullable()";
                }
                $schema .= ";\n";
            } else {
                $schema .= "\$table->{$type}('{$name}'";
                if ($length) {
                    $schema .= ", {$length}";
                }
                $schema .= ")";
                if ($nullable) {
                    $schema .= "->nullable()";
                }
                $schema .= ";\n";
            }
        }
        $schema .= "\$table->timestamps();";

        return $schema;
    }

    protected function createRoutes(string $module, string $path, string $controllerName, bool $isClean): void
    {
        $routesDir = config('make-module.module_path')."/{$module}/routes";
        File::ensureDirectoryExists($routesDir);

        $stubFile = 'route-api.stub';
        $stub = $this->getStubContent($stubFile, $isClean, [
            'StudlyModule' => Str::studly($module),
            'snakeModule' => Str::snake($module),
            'controllerName' => $controllerName,
        ]);

        File::put("{$routesDir}/api.php", $stub);
        $this->info("✅ Rutas API creadas en Modules/{$module}/routes/api.php");

        $stubFileWeb = 'route-web.stub';
        $stubWeb = $this->getStubContent($stubFileWeb, $isClean, [
            'StudlyModule' => Str::studly($module),
            'snakeModule' => Str::snake($module),
            'controllerName' => $controllerName,
        ]);

        File::put("{$routesDir}/web.php", $stubWeb);
        $this->info("✅ Rutas WEB creadas en Modules/{$module}/routes/web.php");
    }

    protected function createSeeder(string $module, string $path, string $seederName, bool $isClean): void
    {
        $seederDir = config('make-module.module_path')."/{$module}/Database/Seeders";
        File::ensureDirectoryExists($seederDir);
        $stubFile = 'seeder.stub';
        $stub = $this->getStubContent($stubFile, $isClean, [
            'namespace' => "Modules\\{$module}\\Database\\Seeders",
            'seederName' => $seederName,
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
            'module' => $module,
        ]);
        File::put("{$testDir}/{$testName}.php", $stub);
        $this->info("✅ Test {$testName}.php creado en Modules/{$module}/Tests/Unit");
    }
}
