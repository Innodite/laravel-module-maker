<?php

namespace Innodite\LaravelModuleMaker;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Factories\Factory;
use Innodite\LaravelModuleMaker\Commands\CheckEnvCommand;
use Innodite\LaravelModuleMaker\Commands\MakeModuleCommand;
use Innodite\LaravelModuleMaker\Commands\MigrateModulesCommand;
use Innodite\LaravelModuleMaker\Commands\ModuleCheckCommand;
use Innodite\LaravelModuleMaker\Commands\PublishFrontendCommand;
use Innodite\LaravelModuleMaker\Commands\SetupModuleMakerCommand;
use Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Database\Seeders\InnoditeModuleSeeder;

class LaravelModuleMakerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/make-module.php', 'make-module'
        );

        // Alias del middleware para uso en rutas: Route::middleware('innodite.bridge')
        $this->app['router']->aliasMiddleware('innodite.bridge', InnoditeContextBridge::class);

        $this->app->singleton('innodite.module_seeder', function ($app) {
            $modulesPath      = base_path('Modules');
            $allModuleSeeders = [];

            if (File::exists($modulesPath)) {
                foreach (File::directories($modulesPath) as $modulePath) {
                    $moduleName   = Str::studly(basename($modulePath));
                    $seederClass  = "Modules\\{$moduleName}\\Database\\Seeders\\{$moduleName}DatabaseSeeder";
                    if (class_exists($seederClass)) {
                        $allModuleSeeders[] = $seederClass;
                    }
                }
            }

            $seeder = new InnoditeModuleSeeder();
            $seeder->setModuleSeeders($allModuleSeeders);
            return $seeder;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModuleCommand::class,
                MigrateModulesCommand::class,
                ModuleCheckCommand::class,
                SetupModuleMakerCommand::class,
                PublishFrontendCommand::class,
                CheckEnvCommand::class,
            ]);

            // ── Publicar configuración ────────────────────────────────────────
            $this->publishes([
                __DIR__ . '/../config/make-module.php' => config_path('make-module.php'),
            ], 'module-maker-config');

            // ── Publicar stubs contextuales para personalización ──────────────
            $this->publishes([
                __DIR__ . '/../stubs/contextual' => base_path('module-maker-config/stubs/contextual'),
            ], 'module-maker-stubs');

            // ── Publicar contexts.json de ejemplo ─────────────────────────────
            $this->publishes([
                __DIR__ . '/../stubs/contexts.json' => base_path('module-maker-config/contexts.json'),
            ], 'module-maker-contexts');

            // ── Publicar composables Vue 3 ────────────────────────────────────
            $this->publishes([
                __DIR__ . '/../stubs/resources/js/Composables' => resource_path('js/Composables'),
            ], 'module-maker-frontend');

            // ── First-run: sugerir setup si module-maker-config/ no existe ────
            $this->detectFirstInstall();
        }

        $modulesPath = base_path('Modules');
        if (!File::exists($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $modulePath) {
            $moduleName = Str::studly(basename($modulePath));

            // ── Service Provider del módulo ───────────────────────────────────
            $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }

            // ── Rutas v3.0.0 ─────────────────────────────────────────────────
            // Ahora en Routes/ (capital) con archivos fijos por tipo de contexto
            $this->loadModuleRoutes($modulePath);

            // ── Vistas ───────────────────────────────────────────────────────
            $viewsPath = "{$modulePath}/resources/views";
            if (File::isDirectory($viewsPath)) {
                $this->loadViewsFrom($viewsPath, Str::snake($moduleName));
            }

            // ── Traducciones ──────────────────────────────────────────────────
            $langPath = "{$modulePath}/resources/lang";
            if (File::isDirectory($langPath)) {
                $this->loadTranslationsFrom($langPath, Str::snake($moduleName));
            }

            // ── Migraciones — discovery dinámico de subcarpetas de contexto ───
            $this->loadModuleMigrations($modulePath);
        }

        // ── Factory resolution para módulos ──────────────────────────────────
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'Modules\\')) {
                $parts  = explode('\\', $modelName);
                $module = $parts[1];
                $class  = class_basename($modelName);
                return "Modules\\{$module}\\Database\\Factories\\{$class}Factory";
            }
            return 'Database\\Factories\\' . class_basename($modelName) . 'Factory';
        });
    }

    /**
     * Carga las rutas del módulo con backward compatibility (v2.x y v3.x).
     *
     * Prioridad de búsqueda:
     *   1. Routes/ (v3.0.0+) → Archivos específicos: web.php, tenant.php, api.php
     *   2. routes/ (v2.x legacy) → Todos los archivos .php
     *
     * Registra los archivos de ruta encontrados:
     *   - web.php    → middleware 'web' (Central + Shared)
     *   - tenant.php → sin middleware wrapper (gestionado por el archivo de ruta)
     *   - api.php    → middleware 'api' (soporte externo)
     *
     * @param  string  $modulePath  Ruta absoluta al directorio del módulo
     * @return void
     */
    private function loadModuleRoutes(string $modulePath): void
    {
        // Prioridad 1: Routes/ (v3.0.0+) — uppercase
        $routesPathV3 = "{$modulePath}/Routes";
        
        // Prioridad 2: routes/ (v2.x legacy) — lowercase
        $routesPathV2 = "{$modulePath}/routes";

        // Detectar qué convención usa este módulo
        if (File::isDirectory($routesPathV3)) {
            // Módulo v3.0.0+ con Routes/ (uppercase)
            $this->loadRoutesV3($routesPathV3);
        } elseif (File::isDirectory($routesPathV2)) {
            // Módulo legacy v2.x con routes/ (lowercase)
            $this->loadRoutesV2($routesPathV2);
        }
    }

    /**
     * Carga rutas v3.0.0+ (Routes/ con archivos específicos).
     *
     * @param  string  $routesPath  Ruta al directorio Routes/
     * @return void
     */
    private function loadRoutesV3(string $routesPath): void
    {
        $webFile    = "{$routesPath}/web.php";
        $tenantFile = "{$routesPath}/tenant.php";
        $apiFile    = "{$routesPath}/api.php";

        if (File::exists($webFile)) {
            Route::middleware('web')->group(function () use ($webFile) {
                require $webFile;
            });
        }

        if (File::exists($tenantFile)) {
            // El archivo tenant.php gestiona sus propios grupos y middlewares internamente
            require $tenantFile;
        }

        if (File::exists($apiFile)) {
            Route::middleware('api')->group(function () use ($apiFile) {
                require $apiFile;
            });
        }
    }

    /**
     * Carga rutas v2.x legacy (routes/ con todos los archivos .php).
     *
     * @param  string  $routesPath  Ruta al directorio routes/
     * @return void
     */
    private function loadRoutesV2(string $routesPath): void
    {
        foreach (File::files($routesPath) as $routeFile) {
            if ($routeFile->getExtension() !== 'php') {
                continue;
            }

            $filename = $routeFile->getFilename();

            if ($filename === 'api.php') {
                Route::middleware('api')->group(function () use ($routeFile) {
                    require $routeFile->getPathname();
                });
            } else {
                Route::middleware('web')->group(function () use ($routeFile) {
                    require $routeFile->getPathname();
                });
            }
        }
    }

    /**
     * Carga migraciones del módulo con discovery dinámico de subcarpetas de contexto.
     *
     * Escanea Database/Migrations/ y todas sus subcarpetas de contexto:
     *   Database/Migrations/Central/
     *   Database/Migrations/Shared/
     *   Database/Migrations/Tenant/Shared/
     *   Database/Migrations/Tenant/{Name}/   ← tenants específicos
     *
     * @param  string  $modulePath  Ruta absoluta al directorio del módulo
     * @return void
     */
    private function loadModuleMigrations(string $modulePath): void
    {
        $migrationsBase = "{$modulePath}/Database/Migrations";

        if (!File::isDirectory($migrationsBase)) {
            return;
        }

        // Registrar la raíz (para migraciones sin contexto / legacy)
        $this->loadMigrationsFrom($migrationsBase);

        // Discovery recursivo de subcarpetas de contexto
        foreach ($this->scanMigrationDirectories($migrationsBase) as $contextDir) {
            $this->loadMigrationsFrom($contextDir);
        }
    }

    /**
     * Escanea recursivamente el directorio de migraciones y retorna
     * todos los subdirectorios (contextos) que contienen archivos .php.
     *
     * @param  string  $baseDir  Directorio base de migraciones
     * @return array<string>     Lista de rutas absolutas a subdirectorios con migraciones
     */
    private function scanMigrationDirectories(string $baseDir): array
    {
        $directories = [];

        foreach (File::allFiles($baseDir) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $dir = $file->getPath();
            if ($dir !== $baseDir && !in_array($dir, $directories, true)) {
                $directories[] = $dir;
            }
        }

        return $directories;
    }

    /**
     * Detecta si es la primera instalación del paquete verificando si existe
     * la carpeta module-maker-config/ en el proyecto. Si no existe, registra
     * un evento que muestra una sugerencia en consola al terminar el comando.
     *
     * No interrumpe ningún flujo: solo es informativo.
     */
    private function detectFirstInstall(): void
    {
        $configPath = config('make-module.config_path');

        if (File::isDirectory($configPath)) {
            return;
        }

        // Sugerir setup tras la ejecución del comando actual
        $this->app->terminating(static function () {
            fwrite(STDERR, PHP_EOL
                . "\033[33m[Innodite ModuleMaker]\033[0m Primera instalación detectada." . PHP_EOL
                . "  Ejecuta el setup inicial para configurar el paquete:" . PHP_EOL
                . "\033[36m  php artisan innodite:module-setup\033[0m" . PHP_EOL . PHP_EOL
            );
        });
    }
}
