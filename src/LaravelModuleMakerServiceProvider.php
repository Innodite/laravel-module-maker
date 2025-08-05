<?php

namespace Innodite\LaravelModuleMaker;

use Illuminate\Support\ServiceProvider;
use Innodite\LaravelModuleMaker\Commands\MakeModuleCommand;
use Innodite\LaravelModuleMaker\Commands\SetupModuleMakerCommand;

class LaravelModuleMakerServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
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
     * Bootstrap any package services.
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
    }
}
