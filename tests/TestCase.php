<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Tests;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\LaravelModuleMakerServiceProvider;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Tests\Support\GeneratedModule;
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

        // La ruta de stubs del proyecto, al temporal — y esto no es simetría, es lo que evita que
        // la suite pruebe stubs que no son los del paquete. El resolutor da prioridad al override
        // del proyecto (nivel 2) sobre el stub del paquete (nivel 3), así que basta con que algo
        // publique los 27 stubs en el `base_path` del skeleton de Testbench —lo hace `module-setup`,
        // y hay pruebas que lo ejecutan— para que TODAS las pruebas de generación siguientes lean
        // esa copia congelada en `vendor/`, corrida tras corrida. Pasó: la copia era de la v3, con
        // los placeholders en `{{ }}`, y sobrevivía a cada `composer install` de otro sin aparecer
        // en el repositorio. Apuntando al temporal, el nivel 2 nunca existe salvo que una prueba lo
        // cree a propósito, y cada prueba nace con el árbol vacío.
        $app['config']->set('make-module.stubs.path',    $this->tempBase . '/module-maker-config/stubs');

        // El modo se declara aquí porque sin modo el paquete se niega a generar, y eso es
        // deliberado. Se fija `multitenant-per-tenant` porque es el escenario que describe el
        // contexts.json de ejemplo —con tenant-one y tenant-two nombrados—, así que las pruebas
        // heredadas siguen midiendo lo mismo que medían. Las que comprueban otro modo lo cambian
        // con withMode().
        $app['config']->set('make-module.mode', ModuleMode::MultitenantPerTenant->value);
    }

    /**
     * Cambia el modo para la prueba en curso.
     *
     * La forma de todo lo generado depende del modo, así que las pruebas que comparan modos
     * necesitan cambiarlo sin reconstruir la aplicación.
     */
    protected function withMode(ModuleMode $mode): static
    {
        config()->set('make-module.mode', $mode->value);

        return $this;
    }

    /**
     * Helper: devuelve una ruta dentro del directorio temporal del test.
     */
    protected function tempPath(string $path = ''): string
    {
        return $path ? "{$this->tempBase}/{$path}" : $this->tempBase;
    }

    /**
     * Genera un módulo de verdad y devuelve el arnés para contrastarlo (A1 · R77).
     *
     * Es la puerta de entrada de las fases 2 a 6: `$this->generateModule('Invoice')->assertCoherent()`
     * ejecuta el comando real, falla con la salida completa si no generó, y contrasta lo escrito.
     * Sin modo explícito se usa el de la configuración, que aquí es `multitenant-per-tenant`.
     *
     * @param array<string, mixed> $options Opciones extra del comando
     */
    protected function generateModule(
        string $name,
        ?ModuleMode $mode = null,
        ?string $context = null,
        array $options = [],
    ): GeneratedModule {
        return GeneratedModule::generate(
            $name,
            $this->tempPath('Modules'),
            $mode ?? ModuleMode::current(),
            $context,
            $options,
        );
    }
}
