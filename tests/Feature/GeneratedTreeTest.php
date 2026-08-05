<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El árbol que queda en el disco, comparado con el que fija el patrón.
 *
 * Las demás pruebas miran una capa cada una. Esta genera un módulo entero y comprueba la forma
 * completa, que es donde se ve lo que ninguna capa por separado enseña: que en single-app no
 * aparezca `Central/` en ninguna parte, y que en multitenant aparezca en todas.
 *
 * Hasta ahora esto no se podía escribir, porque single-app **no arrancaba**: el comando pedía un
 * contexto y una aplicación sin tenants no tenía ninguno que dar (C2).
 */

/** @return array<int, string> Rutas relativas de los archivos generados, ordenadas. */
function arbolDe(string $modulePath): array
{
    if (! File::isDirectory($modulePath)) {
        return [];
    }

    $rutas = [];

    foreach (File::allFiles($modulePath) as $archivo) {
        $rutas[] = str_replace('\\', '/', $archivo->getRelativePathname());
    }

    sort($rutas);

    return $rutas;
}

it('en single-app el árbol no lleva contexto en ningún sitio', function () {
    $this->withMode(ModuleMode::SingleApp);

    Artisan::call('innodite:make-module', [
        'name'             => 'Invoice',
        '--no-routes'      => true,
        '--no-interaction' => true,
    ]);

    $arbol = arbolDe($this->tempPath('Modules/Invoice'));

    expect($arbol)->not->toBeEmpty(
        'Si el árbol está vacío, la generación falló: lee la salida del comando. Single-app estuvo '
        . 'bloqueado por el diagnóstico durante toda la v3 (C2).'
    );

    $conContexto = array_values(array_filter(
        $arbol,
        fn (string $ruta) => str_contains($ruta, 'Central/')
            || str_contains($ruta, 'Tenant/')
            || str_contains($ruta, 'Shared/')
    ));

    expect($conContexto)->toBe(
        [],
        "R5: en single-app no existe el eje de contexto, y aquí aparece:\n  - "
        . implode("\n  - ", $conContexto)
    );

    $conPrefijo = array_values(array_filter(
        $arbol,
        fn (string $ruta) => str_contains(basename($ruta), 'Central')
            || str_contains(basename($ruta), 'TenantShared')
    ));

    expect($conPrefijo)->toBe(
        [],
        "R6: sin contextos no hay nada que desambiguar, así que ningún nombre lleva prefijo:\n  - "
        . implode("\n  - ", $conPrefijo)
    );
});

it('en single-app no se siembra ni una carpeta de contexto, aunque quede vacía', function () {
    // La prueba anterior mira archivos, y una carpeta vacía no tiene ninguno: `Central/` se estaba
    // creando en las doce capas de un proyecto sin tenants y pasaba invisible. Una carpeta vacía no
    // rompe nada, pero sugiere una estructura que el modo dice que no existe — y alguien la usará.
    $this->withMode(ModuleMode::SingleApp);

    Artisan::call('innodite:make-module', [
        'name' => 'Invoice', '--no-routes' => true, '--no-interaction' => true,
    ]);

    $base = $this->tempPath('Modules/Invoice');

    $sembradas = [];

    foreach (['Models', 'Services', 'Repositories', 'Http/Controllers', 'Http/Requests',
              'Database/Migrations', 'Database/Seeders', 'Database/Factories',
              'Tests/Feature', 'Tests/Unit', 'Tests/Support', 'Exceptions',
              'Jobs', 'Notifications', 'Console/Commands', 'resources/js/Pages'] as $capa) {
        foreach (['Central', 'Shared', 'Tenant'] as $ctx) {
            if (File::isDirectory("{$base}/{$capa}/{$ctx}")) {
                $sembradas[] = "{$capa}/{$ctx}";
            }
        }
    }

    expect($sembradas)->toBe(
        [],
        "R5: en single-app el eje de contexto no existe, y estas carpetas se crearon igual:\n  - "
        . implode("\n  - ", $sembradas)
    );
});

it('la carpeta de los tres maestros Application existe en cada contexto', function () {
    $this->withMode(ModuleMode::MultitenantPerTenant);

    Artisan::call('innodite:make-module', [
        'name' => 'Invoice', '--context' => 'central', '--no-routes' => true, '--no-interaction' => true,
    ]);

    $seeders = $this->tempPath('Modules/Invoice/Database/Seeders');

    expect(File::isDirectory("{$seeders}/Central/Application"))->toBeTrue(
        'Los 3 maestros del módulo son el punto de entrada único de su contexto, así que tienen '
        . 'carpeta propia: deploy-central los llama, y ellos hacen fan-out a las 6 piezas de cada '
        . 'subfuncionalidad. Existe uno por contexto — el central no arrastra al del tenant.'
    );
    expect(File::isDirectory("{$seeders}/Tenant/Shared/Application"))->toBeTrue();
});

it('en single-app los maestros van directos bajo Seeders, sin contexto', function () {
    $this->withMode(ModuleMode::SingleApp);

    Artisan::call('innodite:make-module', [
        'name' => 'Invoice', '--no-routes' => true, '--no-interaction' => true,
    ]);

    expect(File::isDirectory($this->tempPath('Modules/Invoice/Database/Seeders/Application')))->toBeTrue(
        'Sin eje de contexto hay un solo juego de maestros, y un solo seeder de despliegue que los llama.'
    );
});

it('la migración lleva el sufijo _final, que dice que trae el esquema completo', function () {
    $this->withMode(ModuleMode::SingleApp);

    Artisan::call('innodite:make-module', [
        'name' => 'Invoice', '--no-routes' => true, '--no-interaction' => true,
    ]);

    $migraciones = glob($this->tempPath('Modules/Invoice/Database/Migrations/Invoice/*.php')) ?: [];

    expect($migraciones)->toHaveCount(1);

    expect(basename($migraciones[0]))->toEndWith(
        '_create_invoices_table_final.php',
        'R22b: el sufijo distingue la migración que trae el esquema completo de las que aplican un '
        . 'delta. Sin él, el orden de la carpeta —que es la unidad de despliegue— deja de significar '
        . 'nada. Se generó: ' . basename($migraciones[0])
    );
});

it('en single-app cada capa cuelga de la carpeta de su subfuncionalidad', function () {
    $this->withMode(ModuleMode::SingleApp);

    Artisan::call('innodite:make-module', [
        'name'             => 'Invoice',
        '--no-routes'      => true,
        '--no-interaction' => true,
    ]);

    $arbol = arbolDe($this->tempPath('Modules/Invoice'));

    $esperados = [
        'Models/Invoice/Invoice.php',
        'Http/Controllers/Invoice/InvoiceController.php',
        'Services/Invoice/InvoiceService.php',
        'Services/Contracts/Invoice/InvoiceServiceInterface.php',
        'Repositories/Invoice/InvoiceRepository.php',
        'Repositories/Contracts/Invoice/InvoiceRepositoryInterface.php',
        'Http/Requests/Invoice/InvoiceStoreRequest.php',
        'Database/Factories/Invoice/InvoiceFactory.php',
        'resources/js/Pages/Invoice/InvoiceIndex.vue',
        'Tests/Feature/Invoice/InvoiceTest.php',
    ];

    $faltan = array_values(array_diff($esperados, $arbol));

    expect($faltan)->toBe(
        [],
        "R5 · R36: la subfuncionalidad es carpeta en TODAS las capas, tests y vistas incluidos.\n"
        . "Faltan:\n  - " . implode("\n  - ", $faltan)
        . "\n\nLo generado fue:\n  - " . implode("\n  - ", $arbol)
    );
});

it('en multitenant el contexto aparece en la carpeta y en el nombre de cada capa', function () {
    $this->withMode(ModuleMode::MultitenantPerTenant);

    Artisan::call('innodite:make-module', [
        'name'             => 'Invoice',
        '--context'        => 'central',
        '--no-routes'      => true,
        '--no-interaction' => true,
    ]);

    $arbol = arbolDe($this->tempPath('Modules/Invoice'));

    $esperados = [
        'Models/Central/Invoice/CentralInvoice.php',
        'Http/Controllers/Central/Invoice/CentralInvoiceController.php',
        'Services/Central/Invoice/CentralInvoiceService.php',
        'Repositories/Central/Invoice/CentralInvoiceRepository.php',
        'Database/Factories/Central/Invoice/CentralInvoiceFactory.php',
        'resources/js/Pages/Central/Invoice/CentralInvoiceIndex.vue',
        'Tests/Feature/Central/Invoice/CentralInvoiceTest.php',
    ];

    $faltan = array_values(array_diff($esperados, $arbol));

    expect($faltan)->toBe(
        [],
        "En multitenant sí hay contextos que separar, así que entran en la carpeta Y en el nombre.\n"
        . "Faltan:\n  - " . implode("\n  - ", $faltan)
        . "\n\nLo generado fue:\n  - " . implode("\n  - ", $arbol)
    );
});

it('la misma subfuncionalidad genera dos árboles distintos según el modo', function () {
    // La prueba que resume la fase: lo que cambia no es un detalle de nombres, es la forma.
    $this->withMode(ModuleMode::SingleApp);
    Artisan::call('innodite:make-module', [
        'name' => 'Invoice', '--no-routes' => true, '--no-interaction' => true,
    ]);
    $single = arbolDe($this->tempPath('Modules/Invoice'));

    File::deleteDirectory($this->tempPath('Modules/Invoice'));

    $this->withMode(ModuleMode::MultitenantPerTenant);
    Artisan::call('innodite:make-module', [
        'name' => 'Invoice', '--context' => 'central', '--no-routes' => true, '--no-interaction' => true,
    ]);
    $multi = arbolDe($this->tempPath('Modules/Invoice'));

    expect($single)->not->toBe(
        $multi,
        'Si los dos modos producen el mismo árbol, el modo no está decidiendo nada y C5 sigue vivo.'
    );

    expect(count($single))->toBeGreaterThan(10);
    expect(count($multi))->toBeGreaterThan(10);
});
