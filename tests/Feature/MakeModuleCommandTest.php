<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\PackageVersion;

// ─────────────────────────────────────────────────────────────────────────────
// Feature: innodite:make-module
// ─────────────────────────────────────────────────────────────────────────────

it('genera un módulo con contexto central y crea la estructura de directorios', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'User',
        '--context'   => 'central',
    ])->assertSuccessful();

    $modulePath = $this->tempPath('Modules/User');

    expect(File::isDirectory($modulePath))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Http/Controllers"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Models"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Services"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Repositories"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Providers"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Database/Migrations"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Routes"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Docs"))->toBeTrue();
});

it('escribe una entrada en el log de auditoría tras la generación exitosa', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'Product',
        '--context'   => 'central',
    ])->assertSuccessful();

    $logPath = storage_path('logs/module_maker.log');
    expect(File::exists($logPath))->toBeTrue();

    $lastEntry = json_decode(trim(collect(explode(PHP_EOL, File::get($logPath)))->filter()->last()), true);

    expect($lastEntry)->toBeArray()
        ->and($lastEntry['event'])->toBe('module.created')
        ->and($lastEntry['module'])->toBe('Product')
        ->and($lastEntry['context_key'])->toBe('central')
        // La versión que quedó grabada es la instalada, no un literal. Esta línea decía '3.0.0' y
        // era la segunda de las dos que sostenían el número escrito a mano: la prueba comparaba
        // contra la misma constante equivocada, así que el defecto pasaba en verde por partida doble.
        ->and($lastEntry['version'])->toBe(PackageVersion::current());
});

it('rechaza nombres que son palabras reservadas de PHP', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'class',
        '--context'   => 'central',
    ])->assertFailed();
});

it('rechaza nombres de módulo inválidos (no PascalCase)', function () {
    $this->artisan('innodite:make-module', [
        'name'        => '123invalid',
        '--context'   => 'central',
    ])->assertFailed();
});

it('impide la creación de un módulo duplicado', function () {
    $args = ['name' => 'Invoice', '--context' => 'central'];

    $this->artisan('innodite:make-module', $args)->assertSuccessful();
    $this->artisan('innodite:make-module', $args)->assertFailed();
});

it('crea los archivos de documentación Docs/', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'Role',
        '--context'   => 'central',
    ])->assertSuccessful();

    $docsPath = $this->tempPath('Modules/Role/Docs');

    expect(File::exists("{$docsPath}/history.md"))->toBeTrue()
        ->and(File::exists("{$docsPath}/architecture.md"))->toBeTrue()
        ->and(File::exists("{$docsPath}/schema.md"))->toBeTrue();
});

it('genera el ServiceProvider del módulo con namespace correcto', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'Permission',
        '--context'   => 'central',
    ])->assertSuccessful();

    // Uno por contexto: `Providers/{Ctx}/{Ctx}{Módulo}ServiceProvider.php`. Con uno solo para los
    // dos, ese archivo era el único sitio del módulo donde los contextos se mezclaban — y es el que
    // decide qué implementación se inyecta.
    $providerFile = $this->tempPath('Modules/Permission/Providers/Central/CentralPermissionServiceProvider.php');

    expect(File::exists($providerFile))->toBeTrue();

    $content = File::get($providerFile);
    expect($content)->toContain('namespace Modules\\Permission\\Providers\\Central');
});

it('lee correctamente el contexts.json y valida el contexto', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'Tenant',
        '--context'   => 'invalid-context-xyz',
    ])->assertFailed();
});

// ─── D9 · Sin contexto no se genera, y se dice cuáles hay ────────────────────────────────────

it('en multitenant sin --context no genera nada: lo exige y lista el catálogo', function () {
    // Antes caía en la selección interactiva, y sin nadie a quien preguntar esa selección devuelve
    // **el primero del catálogo**: `central`. El módulo salía entero en el eje equivocado — rutas en
    // web.php, protegidas con `central-permission`— para una subfuncionalidad pensada para tenants.
    // Perfectamente escrito y completamente mal, que es la forma de defecto que no da la cara.
    config()->set('make-module.mode', 'multitenant');

    $codigo = Artisan::call('innodite:make-module', [
        'name'             => 'Invoice',
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect($codigo)->not->toBe(
        0,
        "FALLA: generó un módulo sin saber su contexto.\n{$salida}"
    );

    expect($salida)->toContain('--context');
    expect(File::isDirectory($this->tempPath('Modules/Invoice')))->toBeFalse(
        'FALLA: dejó archivos escritos pese a no poder decidir el contexto. · FIX: se comprueba '
        . 'ANTES de escribir nada; un módulo a medias hay que borrarlo a mano.'
    );
});

// ── Que el módulo cargue de verdad ─────────────────────────────────────────────────────────────

it('avisa cuando el módulo generado todavía no va a cargar', function () {
    // ⚠️ El fallo que esto evita no da ningún error: el proveedor del paquete registra el del módulo
    // dentro de un `class_exists()`, así que si la clase no resuelve —autoload sin `Modules\`, o sin
    // `dump-autoload` después de generar— no se registra, sus rutas no se cargan y la aplicación
    // sigue respondiendo 200. Se persigue durante horas una ruta que «no existe».
    config()->set('make-module.mode', ModuleMode::SingleApp->value);

    Artisan::call('innodite:make-module', ['name' => 'Invoice', '--no-interaction' => true]);

    $salida = Artisan::output();

    expect($salida)->toContain('todavía NO carga')
        ->and($salida)->toContain('FIX:')
        ->and($salida)->toContain('composer dump-autoload')
        ->and($salida)->toContain('sus rutas sencillamente no existen');
});
