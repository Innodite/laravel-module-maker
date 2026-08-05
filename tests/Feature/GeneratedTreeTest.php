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
