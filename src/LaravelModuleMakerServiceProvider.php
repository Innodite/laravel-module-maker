<?php

namespace Innodite\LaravelModuleMaker;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Factories\Factory;
use Innodite\LaravelModuleMaker\Commands\MakeModuleCommand;
use Innodite\LaravelModuleMaker\Commands\SetupModuleMakerCommand;
use Illuminate\Support\Str;

class LaravelModuleMakerServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/make-module.php', 'make-module'
        );
    }

    /**
     * Boot the application events.
     *
     * @return void
     */
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

        foreach (File::directories($modulesPath) as $modulePath) {
            $moduleName = basename($modulePath);
            $moduleName = Str::studly($moduleName);

            // REGISTRO DEL SERVICE PROVIDER DEL MÓDULO
            $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }

            // Cargar todos los archivos de rutas sin definir namespace externo
            //rutas sin definir namespace externo
            $routesPath = "{$modulePath}/routes";
            if (File::isDirectory($routesPath)) {
                foreach (File::files($routesPath) as $routeFile) {
                    $filename = $routeFile->getFilename();
                    
                    // Determinar el middleware según el nombre del archivo
                    if ($filename === 'api.php') {
                        Route::middleware('api')
                            ->group(function () use ($routeFile) {
                                require $routeFile->getPathname();
                            });
                    } else {
                        Route::middleware('web')
                            ->group(function () use ($routeFile) {
                                require $routeFile->getPathname();
                            });
                    }
                }
            }

            // Vistas
            $viewsPath = "{$modulePath}/resources/views";
            if (File::isDirectory($viewsPath)) {
                $this->loadViewsFrom($viewsPath, Str::snake($moduleName));
            }

            // Traducciones
            $langPath = "{$modulePath}/resources/lang";
            if (File::isDirectory($langPath)) {
                $this->loadTranslationsFrom($langPath, Str::snake($moduleName));
            }

            // Migraciones
            $migrationsPath = "{$modulePath}/Database/Migrations";
            if (File::isDirectory($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }

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
