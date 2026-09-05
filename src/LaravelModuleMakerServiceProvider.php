<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker;

use Innodite\LaravelModuleMaker\Support\ModuleMode;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Factories\Factory;
use Innodite\LaravelModuleMaker\Commands\AddEntityCommand;
use Innodite\LaravelModuleMaker\Commands\CreateTestDatabaseCommand;
use Innodite\LaravelModuleMaker\Commands\DeployCommand;
use Innodite\LaravelModuleMaker\Commands\DoctorCommand;
use Innodite\LaravelModuleMaker\Commands\MakeModuleCommand;
use Innodite\LaravelModuleMaker\Commands\MigrateOneCommand;
use Innodite\LaravelModuleMaker\Commands\MigratePlanCommand;
use Innodite\LaravelModuleMaker\Commands\PublishFrontendCommand;
use Innodite\LaravelModuleMaker\Commands\SetupModuleMakerCommand;
use Innodite\LaravelModuleMaker\Commands\TestCommand;
use Innodite\LaravelModuleMaker\Contracts\ProveedorDeCriterio;
use Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge;
use Innodite\LaravelModuleMaker\Services\Criterio\CriterioLocal;
use Illuminate\Support\Str;

class LaravelModuleMakerServiceProvider extends ServiceProvider
{
    /**
     * The commands this package registers.
     *
     * A constant and not a literal inside `register()` because the diagnostic needs the same list to
     * answer a question the developer cannot answer alone: whether a command of the host project is
     * shadowing one of these. Two lists would drift, and the drift would show up as a check that
     * quietly stops covering whatever was added last.
     *
     * @var array<int, class-string<\Illuminate\Console\Command>>
     */
    public const COMANDOS = [
        MakeModuleCommand::class,
        AddEntityCommand::class,
        MigrateOneCommand::class,
        MigratePlanCommand::class,
        DeployCommand::class,
        DoctorCommand::class,
        CreateTestDatabaseCommand::class,
        SetupModuleMakerCommand::class,
        PublishFrontendCommand::class,
        TestCommand::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/make-module.php',
            'make-module'
        );

        // Alias del middleware para uso en rutas: Route::middleware('innodite.bridge')
        $this->app['router']->aliasMiddleware('innodite.bridge', InnoditeContextBridge::class);

        // El enchufe del criterio. Quien pregunta pide la INTERFAZ; qué implementación llega lo dice
        // la configuración. Es lo que hace que conectar el criterio remoto sea cambiar una clave en
        // vez de tocar los comandos que preguntan.
        $this->app->bind(ProveedorDeCriterio::class, function ($app) {
            $clase = config('make-module.criterio.proveedor', CriterioLocal::class);

            return $app->make(is_string($clase) && class_exists($clase) ? $clase : CriterioLocal::class);
        });

        // Aquí vivía el singleton `innodite.module_seeder`. Construía un InnoditeModuleSeeder
        // y le llamaba a setModuleSeeders() — un método que esa clase nunca tuvo—, así que resolverlo
        // era un fatal. No lo resolvía nadie: por eso llevaba desde la v3 sin dar un solo síntoma.
        // Su trabajo lo hace ahora el seeder de despliegue del proyecto, que además lee el orden
        // declarado en vez de recorrer el disco por orden alfabético.
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(self::COMANDOS);

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

            // ── Publicar composables y componentes Vue 3 ──────────────────────
            // Los dos grupos van bajo el mismo tag: la vista generada importa de ambos, así que
            // publicar solo uno deja la pantalla con imports que no resuelven.
            $this->publishes([
                __DIR__ . '/../stubs/resources/js/Composables' => resource_path('js/Composables'),
                __DIR__ . '/../stubs/resources/js/Components'  => resource_path('js/Components'),
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
     * Sugiere el instalador mientras el paquete no esté configurado.
     *
     * **La señal es el MODO, no una carpeta.** Antes miraba si existía `module-maker-config/`, y esa
     * pregunta no es la misma: la carpeta puede existir sin que nadie haya instalado nada —basta un
     * `vendor:publish` suelto— y, sobre todo, el instalador puede haber terminado bien y la señal no
     * cambiar, con lo que el aviso seguía saliendo en cada comando después de hacer justo lo que
     * pedía. Lo que el instalador deja decidido es el modo, y sin modo ningún comando genera nada.
     *
     * No interrumpe ningún flujo: solo es informativo.
     */
    private function detectFirstInstall(): void
    {
        if (ModuleMode::isConfigured()) {
            return;
        }

        // Sugerir setup tras la ejecución del comando actual
        $this->app->terminating(static function () {
            fwrite(STDERR, PHP_EOL
                . "\033[33m[Innodite ModuleMaker]\033[0m Primera instalación detectada." . PHP_EOL
                . "  Ejecuta el setup inicial para configurar el paquete:" . PHP_EOL
                . "\033[36m  php artisan innodite:module-setup\033[0m" . PHP_EOL . PHP_EOL);
        });
    }

}
