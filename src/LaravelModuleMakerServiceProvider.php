<?php

namespace Innodite\LaravelModuleMaker;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Factories\Factory;
use Innodite\LaravelModuleMaker\Commands\MakeModuleCommand;
use Innodite\LaravelModuleMaker\Commands\SetupModuleMakerCommand;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class LaravelModuleMakerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/make-module.php', 'make-module'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModuleCommand::class,
                SetupModuleMakerCommand::class,
            ]);
        }
        
        $modulesPath = base_path('Modules');
        if (!File::exists($modulesPath)) {
            return;
        }

        $allModuleSeeders = [];

        foreach (File::directories($modulesPath) as $modulePath) {
            $moduleName = basename($modulePath);
            $moduleName = Str::studly($moduleName);

            // REGISTRAR EL SERVICE PROVIDER DEL MÓDULO
            $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }

            // CARGAR RUTAS
            $routesPath = "{$modulePath}/routes";
            if (File::isDirectory($routesPath)) {
                foreach (File::files($routesPath) as $routeFile) {
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

            // VISTAS
            $viewsPath = "{$modulePath}/resources/views";
            if (File::isDirectory($viewsPath)) {
                $this->loadViewsFrom($viewsPath, Str::snake($moduleName));
            }

            // TRADUCCIONES
            $langPath = "{$modulePath}/resources/lang";
            if (File::isDirectory($langPath)) {
                $this->loadTranslationsFrom($langPath, Str::snake($moduleName));
            }

            // MIGRACIONES
            $migrationsPath = "{$modulePath}/Database/Migrations";
            if (File::isDirectory($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }

            // NUEVO: Recopilar los seeders principales de los módulos
            $seederClass = "Modules\\{$moduleName}\\Database\\Seeders\\{$moduleName}DatabaseSeeder";
            if (class_exists($seederClass)) {
                $allModuleSeeders[] = $seederClass;
            }
        }

        // Registrar un Seeder maestro que contiene todos los seeders de los módulos
        $this->app->singleton('innodite.module_seeder', function ($app) use ($allModuleSeeders) {
            return new class extends Seeder
            {
                public function run()
                {
                    foreach ($allModuleSeeders as $seederClass) {
                        $this->call($seederClass);
                    }
                }
            };
        });

        // Factories
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'Modules\\')) {
                $parts = explode('\\', $modelName);
                $module = $parts[1];
                $class = class_basename($modelName);
                return "Modules\\{$module}\\Database\\Factories\\{$class}Factory";
            }
            return 'Database\\Factories\\' . class_basename($modelName) . 'Factory';
        });
    }
}