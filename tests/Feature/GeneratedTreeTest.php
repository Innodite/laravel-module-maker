<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SubFeaturePermissions;

/**
 * El árbol que queda en el disco, comparado con el que fija el patrón.
 *
 * Las demás pruebas miran una capa cada una. Estas generan un módulo entero y comprueban la forma
 * completa, que es donde se ve lo que ninguna capa por separado enseña: que en single-app no
 * aparezca `Central/` en ninguna parte, que en multitenant aparezca en todas, y que las piezas
 * que se nombran entre sí —el provider y sus bindings, las rutas y su controlador— apunten a
 * archivos que existen.
 *
 * Todas pasan por el arnés (`$this->generateModule(...)`): generar, y contrastar lo escrito.
 * Antes cada una llamaba a `Artisan::call` y recorría el árbol por su cuenta, y el resultado era
 * que cada prueba tenía que acordarse por su lado de mirar el contenido — o no lo miraba.
 */

it('en single-app el árbol no lleva contexto en ningún sitio', function () {
    $this->generateModule('Invoice', ModuleMode::SingleApp)->assertNoContextAxis();
});

it('en multitenant el contexto entra en la carpeta y en el nombre', function () {
    $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')
        ->assertContextAxis('Central', 'Central');
});

it('la carpeta de los tres maestros Application existe en cada contexto', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    expect(is_dir($modulo->path('Database/Seeders/Central/Application')))->toBeTrue(
        'Los 3 maestros del módulo son el punto de entrada único de su contexto, así que tienen '
        . 'carpeta propia: deploy-central los llama, y ellos hacen fan-out a las 6 piezas de cada '
        . 'subfuncionalidad. Existe uno por contexto — el central no arrastra al del tenant.'
    );
    expect(is_dir($modulo->path('Database/Seeders/Tenant/Shared/Application')))->toBeTrue();
});

it('en single-app los maestros van directos bajo Seeders, sin contexto', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    expect(is_dir($modulo->path('Database/Seeders/Application')))->toBeTrue(
        'Sin eje de contexto hay un solo juego de maestros, y un solo seeder de despliegue que los llama.'
    );
});

it('la migración lleva el sufijo _final, que dice que trae el esquema completo', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $migraciones = glob($modulo->path('Database/Migrations/Invoice/*.php')) ?: [];

    expect($migraciones)->toHaveCount(1);

    expect(basename($migraciones[0]))->toEndWith(
        '_create_invoices_table_final.php',
        'R22b: el sufijo distingue la migración que trae el esquema completo de las que aplican un '
        . 'delta. Sin él, el orden de la carpeta —que es la unidad de despliegue— deja de significar '
        . 'nada. Se generó: ' . basename($migraciones[0])
    );
});

it('en single-app cada capa cuelga de la carpeta de su subfuncionalidad', function () {
    $this->generateModule('Invoice', ModuleMode::SingleApp)->assertTreeHas([
        'Models/Invoice/Invoice.php',
        'Http/Controllers/Invoice/InvoiceController.php',
        'Services/Invoice/InvoiceService.php',
        'Services/Contracts/Invoice/InvoiceServiceInterface.php',
        'Repositories/Invoice/InvoiceRepository.php',
        'Repositories/Contracts/Invoice/InvoiceRepositoryInterface.php',
        'Http/Requests/Invoice/InvoiceStoreRequest.php',
        'Database/Factories/Invoice/InvoiceFactory.php',
        'resources/js/Pages/Invoice/InvoiceIndex.vue',
        'Tests/Feature/Invoice/InvoiceScaffoldTest.php',
    ], 'R5 · R36: la subfuncionalidad es carpeta en TODAS las capas, tests y vistas incluidos.');
});

it('en multitenant el contexto aparece en la carpeta y en el nombre de cada capa', function () {
    $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')->assertTreeHas([
        'Models/Central/Invoice/CentralInvoice.php',
        'Http/Controllers/Central/Invoice/CentralInvoiceController.php',
        'Services/Central/Invoice/CentralInvoiceService.php',
        'Repositories/Central/Invoice/CentralInvoiceRepository.php',
        'Database/Factories/Central/Invoice/CentralInvoiceFactory.php',
        'resources/js/Pages/Central/Invoice/CentralInvoiceIndex.vue',
        'Tests/Feature/Central/Invoice/CentralInvoiceScaffoldTest.php',
    ], 'En multitenant sí hay contextos que separar, así que entran en la carpeta Y en el nombre.');
});

it('la misma subfuncionalidad genera dos árboles distintos según el modo', function () {
    // La prueba que resume la fase: lo que cambia no es un detalle de nombres, es la forma.
    $single = $this->generateModule('Invoice', ModuleMode::SingleApp)->tree();

    File::deleteDirectory($this->tempPath('Modules/Invoice'));

    $multi = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')->tree();

    expect($single)->not->toBe(
        $multi,
        'Si los dos modos producen el mismo árbol, el modo no está decidiendo nada y C5 sigue vivo.'
    );

    expect(count($single))->toBeGreaterThan(10);
    expect(count($multi))->toBeGreaterThan(10);
});

it('el módulo generado se sostiene entero: nada sin resolver, nada apuntando al vacío', function (ModuleMode $modo, ?string $contexto) {
    // La regresión de B18. El provider importaba `…\Services\InvoiceService` mientras el archivo
    // estaba en `…\Services\Invoice\InvoiceService`, y las cinco rutas del módulo apuntaban a un
    // controlador con el mismo desfase: **el módulo generado no arrancaba**. Los archivos existían
    // todos, cada uno parseaba y ninguno llevaba placeholders, así que ninguna prueba lo vio.
    // Solo aparece poniendo cada `use` contra el árbol.
    $this->generateModule('Invoice', $modo, $contexto)->assertCoherent();
})->with([
    'single-app'  => [ModuleMode::SingleApp, null],
    'multitenant' => [ModuleMode::MultitenantPerTenant, 'central'],
]);

it('el provider registra clases que existen, con la carpeta de la subfuncionalidad', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $provider = $modulo->contents('Providers/InvoiceServiceProvider.php');

    expect(str_contains($provider, 'use Modules\Invoice\Services\Invoice\InvoiceService;'))->toBeTrue(
        'El import del provider tiene que coincidir con el namespace del archivo que el generador '
        . 'del servicio escribió — que lleva la subfuncionalidad como última carpeta desde que la '
        . "estructura es {Capa}/{SubFuncionalidad}/. El provider dice:\n" . $provider
    );
    expect(str_contains($provider, 'use Modules\Invoice\Services\Contracts\Invoice\InvoiceServiceInterface;'))
        ->toBeTrue('Y el contrato, que vive en {Capa}/Contracts/{SubFuncionalidad}/.');
    expect(str_contains($provider, '$this->app->bind(InvoiceServiceInterface::class, InvoiceService::class);'))
        ->toBeTrue('El binding usa el nombre corto de las dos clases importadas.');
});

it('las rutas apuntan al controlador que se generó, no a uno que no existe', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $contenido = $modulo->contents('Routes/web.php');

    expect(str_contains($contenido, 'use Modules\Invoice\Http\Controllers\Invoice\InvoiceController;'))
        ->toBeTrue(
            "El import se armaba dentro del stub, sin la carpeta de la subfuncionalidad. Rutas "
            . "apuntando a una clase inexistente son errores 500 en el módulo recién generado. "
            . "El archivo dice:\n" . $contenido
        );
});

it('en single-app no se genera Routes/api.php: todo se declara en web', function () {
    // `api.php` solo tiene sentido cuando se expone un servicio a un cliente externo, y eso trae su
    // propia autenticación por token. Mientras tanto era un segundo archivo de rutas duplicando
    // store/show/update/destroy con otro prefijo — y, como el web de entonces, sin un solo permiso.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    expect($modulo->has('Routes/api.php'))->toBeFalse(
        'Todo se declara en web.php. Un api.php generado por defecto es superficie sin dueño.'
    );
    expect($modulo->has('Routes/web.php'))->toBeTrue();
});

it('en single-app cada ruta lleva su permiso', function () {
    // B23: este camino escribía desde dos stubs propios que no ponían ni un `->middleware()`. En el
    // modo que más se usa, el módulo generado nacía entero sin protección.
    $modulo    = $this->generateModule('Invoice', ModuleMode::SingleApp);
    $contenido = $modulo->contents('Routes/web.php');

    foreach (SubFeaturePermissions::routes('', 'invoices') as $ruta) {
        expect(str_contains($contenido, "permission:{$ruta['permission']}"))->toBeTrue(
            "La ruta '{$ruta['route']}' no exige el permiso '{$ruta['permission']}'. "
            . "El archivo dice:\n" . $contenido
        );
    }
});
