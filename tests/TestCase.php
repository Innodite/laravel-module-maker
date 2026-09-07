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
        File::ensureDirectoryExists("{$this->tempBase}/database/seeders");

        // Publicar contexts.json de ejemplo al directorio temporal
        $contextSource = dirname(__DIR__) . '/stubs/contexts.json';
        if (File::exists($contextSource)) {
            File::copy($contextSource, "{$this->tempBase}/module-maker-config/contexts.json");
        }
    }

    protected function tearDown(): void
    {
        ContextResolver::flush();
        $this->retirarConfigPublicada();

        if (File::isDirectory($this->tempBase)) {
            File::deleteDirectory($this->tempBase);
        }

        parent::tearDown();
    }

    /**
     * Retira el `config/make-module.php` que cualquier prueba haya dejado publicado.
     *
     * ⚠️ **Sin esto hay un rojo fantasma, y de los peores: aparece en un archivo que nadie tocó.**
     * `config_path()` es lo único del contrato que no se puede mover al temporal —el `TestCase`
     * desvía los módulos, los stubs y `database/`, pero la configuración se publica donde Laravel
     * diga—, así que apunta al skeleton de Testbench: dentro de `vendor/`, fuera del repositorio y
     * superviviente a la prueba.
     *
     * Media docena de pruebas ejecutan `innodite:module-setup`, que lo publica. Con el archivo ahí,
     * la prueba del diagnóstico que comprueba qué se dice cuando la configuración **no** está
     * publicada falla — y no falla en su corrida, falla en la **siguiente**. Se clasifica como base
     * sucia (R81) y se cierra en el único sitio por el que pasan todas.
     *
     * En un skeleton limpio este archivo no existe: solo aparece si alguien lo publica. Por eso
     * borrarlo siempre es correcto, y no hay nada que restaurar.
     */
    private function retirarConfigPublicada(): void
    {
        $publicada = config_path('make-module.php');

        if (File::exists($publicada)) {
            File::delete($publicada);
        }
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

        // Y la carpeta `database/` también al temporal, por la misma razón y con una consecuencia
        // peor: desde la fase 3 el instalador escribe ahí los seeders de despliegue del proyecto y
        // engancha el `DatabaseSeeder`. Sin esto, cada corrida de la suite modificaría el skeleton de
        // Testbench dentro de `vendor/` — un archivo que sobrevive a la prueba, no aparece en el
        // repositorio y, como el generador no sobreescribe, dejaría a las corridas siguientes
        // midiendo el archivo de la primera.
        $app->useDatabasePath($this->tempBase . '/database');

        // El modo se declara aquí porque sin modo el paquete se niega a generar, y eso es
        // deliberado. Se fija `multitenant` porque es el escenario que describe el contexts.json de
        // ejemplo —central e inquilino— y porque es el que tiene eje de contexto, que es lo que la
        // mayoría de estas pruebas mide. Las que comprueban aplicación única lo cambian con
        // withMode().
        $app['config']->set('make-module.mode', ModuleMode::Multitenant->value);
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
     * Genera un módulo de verdad y devuelve el arnés para contrastarlo.
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
