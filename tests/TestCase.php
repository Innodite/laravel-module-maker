<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Tests;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\LaravelModuleMakerServiceProvider;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $tempBase;

    protected function setUp(): void
    {
        // tempBase debe estar definido ANTES de parent::setUp()
        // porque getEnvironmentSetUp() es invocado durante la inicialización
        $this->tempBase = sys_get_temp_dir() . '/innodite-tests-' . uniqid('', true);

        parent::setUp();

        // Crear estructura de directorios del entorno de prueba
        File::ensureDirectoryExists("{$this->tempBase}/Modules");
        File::ensureDirectoryExists("{$this->tempBase}/module-maker-config");
        File::ensureDirectoryExists("{$this->tempBase}/routes");

        // Publicar contexts.json de ejemplo al directorio temporal
        $contextSource = dirname(__DIR__) . '/stubs/contexts.json';
        if (File::exists($contextSource)) {
            File::copy($contextSource, "{$this->tempBase}/module-maker-config/contexts.json");
        }
    }

    protected function tearDown(): void
    {
        ContextResolver::flush();

        if (File::isDirectory($this->tempBase)) {
            File::deleteDirectory($this->tempBase);
        }

        parent::tearDown();
    }

    /**
     * Registra los Service Providers del paquete en la aplicación de prueba.
     */
    protected function getPackageProviders($app): array
    {
        return [LaravelModuleMakerServiceProvider::class];
    }

    /**
     * Configura el entorno de la aplicación de prueba para usar directorios temporales.
     * Garantiza que los tests no afecten el proyecto real.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('make-module.module_path',   $this->tempBase . '/Modules');
        $app['config']->set('make-module.config_path',   $this->tempBase . '/module-maker-config');
        $app['config']->set('make-module.contexts_path', $this->tempBase . '/module-maker-config/contexts.json');
    }

    /**
     * Helper: devuelve una ruta dentro del directorio temporal del test.
     */
    protected function tempPath(string $path = ''): string
    {
        return $path ? "{$this->tempBase}/{$path}" : $this->tempBase;
    }
}
