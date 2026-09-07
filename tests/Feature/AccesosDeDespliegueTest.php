<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * Los dos accesos con nombre propio para levantar el proyecto.
 *
 * ⭐ **Qué problema resuelven.** El motor del despliegue recibe la pieza por parámetro, lo cual está
 * bien para el comando —que la pide explícitamente— y mal para `db:seed` y para el `DatabaseSeeder`:
 * hay que acordarse de escribirla, y una llamada sin ella despliega **producción** creyendo que se
 * pidió otra cosa. Con dos archivos, el nombre es la respuesta.
 *
 * ⛔ **Y lo que NO pueden hacer, que es lo que de verdad se vigila aquí:** repetir la lista de
 * módulos. El orden vive en `config/make-module.php` y en ningún sitio más. Escribirlo también aquí
 * daría el mismo resultado hoy y dos listas mañana, de las que el día del módulo siguiente solo se
 * actualizaría una.
 */
function instalar(string $modo = 'single-app'): string
{
    $raiz = test()->tempPath('proy');
    File::deleteDirectory($raiz);
    foreach (['config', 'database/seeders'] as $d) {
        File::ensureDirectoryExists("{$raiz}/{$d}");
    }

    app()->setBasePath($raiz);
    app()->useDatabasePath($raiz . '/database');
    config([
        'make-module.config_path'   => $raiz . '/module-maker-config',
        'make-module.contexts_path' => $raiz . '/module-maker-config/contexts.json',
        'make-module.stubs.path'    => $raiz . '/module-maker-config/stubs',
    ]);

    Artisan::call('innodite:module-setup', ['--mode' => $modo, '--frontend' => 'default', '--no-interaction' => true]);

    return $raiz . '/database/seeders';
}

it('el setup deja los dos accesos, uno por entorno', function () {
    $seeders = instalar();

    expect(File::exists("{$seeders}/InnoditeStageSeeder.php"))->toBeTrue(
        'FALLA: no se escribió InnoditeStageSeeder. · FIX: lo genera writeEntryPoints() del '
        . 'generador de seeders del proyecto.'
    );
    expect(File::exists("{$seeders}/InnoditeProductionSeeder.php"))->toBeTrue(
        'FALLA: no se escribió InnoditeProductionSeeder.'
    );
});

it('cada acceso pide su pieza, y no la del otro', function () {
    // Es la razón de que existan: que el nombre del archivo sea la respuesta, y no un parámetro que
    // hay que recordar al llamarlo.
    $seeders = instalar();

    $stage = File::get("{$seeders}/InnoditeStageSeeder.php");
    $prod  = File::get("{$seeders}/InnoditeProductionSeeder.php");

    expect($stage)->toContain("'piece' => 'Stage'")->not->toContain("'Production'");
    expect($prod)->toContain("'piece' => 'Production'")->not->toContain("'Stage'");
});

it('⛔ ningún acceso repite la lista de módulos', function () {
    // La comprobación que da sentido a todo el diseño. Si algún día alguien escribe aquí las
    // llamadas a cada maestro, habrá dos sitios donde vive el orden — y el día que se genere un
    // módulo nuevo, solo se actualizará uno.
    $seeders = instalar();

    // Se genera un módulo: su maestro NO puede acabar nombrado en los accesos.
    config(['make-module.mode' => 'single-app']);
    \Innodite\LaravelModuleMaker\Support\ContextResolver::flush();
    Artisan::call('innodite:make-module', ['name' => 'Invoice', '--no-interaction' => true]);

    foreach (['InnoditeStageSeeder', 'InnoditeProductionSeeder'] as $acceso) {
        $contenido = File::get("{$seeders}/{$acceso}.php");

        expect(str_contains($contenido, 'Invoice'))->toBeFalse(
            "FALLA: {$acceso} nombra un maestro de módulo. · FIX: los accesos solo declaran su "
            . 'pieza; los módulos y su orden salen de config/make-module.php, que es el único sitio '
            . "donde se declaran.\n{$contenido}"
        );
    }
});

it('el módulo generado sí queda registrado, en el único sitio donde se declara', function () {
    // La otra mitad: que no esté en los accesos no significa que no esté en ninguna parte.
    instalar();
    config(['make-module.mode' => 'single-app']);
    \Innodite\LaravelModuleMaker\Support\ContextResolver::flush();

    Artisan::call('innodite:make-module', ['name' => 'Invoice', '--no-interaction' => true]);

    expect(Artisan::output())->toContain('Añadida al orden de despliegue');
});

it('no se sobreescribe un acceso que el proyecto ya tuviera', function () {
    $seeders = instalar();
    File::put("{$seeders}/InnoditeStageSeeder.php", '<?php // lo mío');

    // Reinstalar no puede llevarse por delante lo que el proyecto haya tocado.
    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--frontend' => 'default', '--no-interaction' => true]);

    expect(File::get("{$seeders}/InnoditeStageSeeder.php"))->toBe('<?php // lo mío');
});
