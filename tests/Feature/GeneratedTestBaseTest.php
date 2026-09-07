<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Tests\Support\GeneratedModule;

/**
 * La base del grupo de pruebas: la derivación, escrita una sola vez.
 *
 * **La prueba que de verdad importa está aquí, y es la tercera.** El grupo entero se apoya en que el
 * `ROUTE_PREFIX` del manifiesto encuentre las rutas de la subfuncionalidad. Si no las encuentra, los
 * temas 3, 4 y 5 recorren un conjunto **vacío** — y un `foreach` sobre la nada no falla: **pasa**. La
 * subfuncionalidad quedaría con tres pruebas en verde que no comprobaron ni una ruta, que es
 * exactamente la señal de cobertura sin cobertura que esta fase viene a cerrar.
 *
 * Y el desfase es fácil de producir: la URI de una ruta en multitenant es `central-billings`, el
 * nombre es `central.billings.` y la funcionalidad es `billings`. Tres cadenas parecidas, y solo una
 * es la correcta.
 */

/** Los archivos de rutas de un módulo generado. @return array<int, string> */
function archivosDeRutas(GeneratedModule $modulo): array
{
    return array_values(array_filter(
        $modulo->tree(),
        static fn (string $ruta): bool => str_contains($ruta, 'Routes/') && str_ends_with($ruta, '.php'),
    ));
}

it('el módulo generado trae la base de su grupo de pruebas', function (ModuleMode $modo, ?string $contexto, string $ruta) {
    $modulo = $this->generateModule('Invoice', $modo, $contexto);

    $modulo->assertTreeHas(
        [$ruta],
        'la derivación de rutas, permisos y usuarios vive en la base del grupo. Sin ella, las '
        . 'cinco piezas repiten el mismo cálculo cinco veces.'
    );
})->with([
    'single-app'  => [ModuleMode::SingleApp, null, 'Invoice/Tests/Feature/InvoiceTestCase.php'],
    'multitenant' => [ModuleMode::Multitenant, 'central', 'Invoice/Tests/Feature/Central/CentralInvoiceTestCase.php'],
]);

it('la base lee el manifiesto en vez de declarar nada por su cuenta', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $base = $modulo->contents('Invoice/Tests/Feature/InvoiceTestCase.php');

    // `toContain()` recibe VALORES, no un mensaje: pasarle el texto de ayuda como segundo argumento
    // le pide comprobar que el archivo también contiene esa frase — y una comprobación que nadie
    // puede cumplir, negada, pasa siempre. Por eso aquí se afirma sobre el booleano.
    expect(str_contains($base, 'InvoiceContract::ROUTE_PREFIX'))->toBeTrue(
        'FALLA: la base no filtra por el prefijo del manifiesto. · FIX: el manifiesto es la fuente; '
        . 'un prefijo escrito aquí sería una segunda verdad.'
    );

    expect(str_contains($base, 'InvoiceContract::PERMISSION_SEEDER'))->toBeTrue(
        'FALLA: la base no pregunta los permisos al seeder que los crea. · FIX: derivarlos de ahí es '
        . 'lo que impide que una lista de permisos se quede atrás.'
    );

    foreach (['invoices_index', 'invoices_store', 'invoices_view_store'] as $permiso) {
        expect(str_contains($base, "'{$permiso}'"))->toBeFalse(
            "FALLA: la base enumera el permiso '{$permiso}'. · FIX: los permisos se derivan del "
            . 'seeder; enumerarlos aquí los convierte en una lista que mantener.'
        );
    }
});

it('el prefijo del manifiesto encuentra de verdad las rutas generadas', function (ModuleMode $modo, ?string $contexto, string $carpeta, string $contrato) {
    // El cruce que evita el peor fallo de este grupo: tres temas en verde sobre un conjunto vacío.
    // Se contrasta el prefijo declarado contra el `->name(…)` con el que el generador de rutas abre
    // el grupo — las dos mitades del par, puestas una frente a la otra.
    $modulo = $this->generateModule('Invoice', $modo, $contexto);

    cargarPiezas($modulo, $carpeta, [basename(str_replace('\\', '/', $contrato))]);

    $prefijo = $contrato::ROUTE_PREFIX;
    $rutas   = archivosDeRutas($modulo);

    expect($rutas)->not->toBeEmpty('El módulo generado tiene que declarar sus rutas en alguna parte.');

    $declarado = array_values(array_filter(
        $rutas,
        fn (string $archivo): bool => str_contains($modulo->contents($archivo), "->name('{$prefijo}')"),
    ));

    expect($declarado)->not->toBe(
        [],
        "FALLA: ningún archivo de rutas abre su grupo con ->name('{$prefijo}'), que es el prefijo que "
        . "el manifiesto declara.\n"
        . "  · FIX: el contrato compone el prefijo con `route_name` del contexto + la funcionalidad + "
        . "'.', igual que el generador de rutas. Si divergen, los temas 3-5 no encuentran ninguna "
        . "ruta y pasan en verde sin comprobar nada.\n\n"
        . "Las rutas generadas dicen:\n" . implode("\n", array_map(
            fn (string $archivo): string => "── {$archivo}\n" . $modulo->contents($archivo),
            $rutas,
        ))
    );
})->with([
    'single-app'  => [ModuleMode::SingleApp, null, 'Invoice/Tests/Feature', 'Modules\Invoice\Invoice\Tests\Feature\InvoiceContract'],
    'multitenant' => [ModuleMode::Multitenant, 'central', 'Invoice/Tests/Feature/Central', 'Modules\Invoice\Invoice\Tests\Feature\Central\CentralInvoiceContract'],
]);

it('el alias del permiso que escriben las rutas es el que la base sabe leer', function (ModuleMode $modo, ?string $contexto) {
    // La otra mitad del mismo par: la base reconoce cualquier alias que **termine** en 'permission',
    // porque el alias lo decide el proyecto. Si el generador escribiera otro formato —un middleware
    // sin parámetro, o con el permiso en otro sitio— `permisoDe()` devolvería cadena vacía y el tema
    // 3 diría que ninguna ruta exige permiso… teniéndolo todas.
    $modulo = $this->generateModule('Invoice', $modo, $contexto);

    $alias = [];

    foreach (archivosDeRutas($modulo) as $archivo) {
        preg_match_all("/->middleware\('([\w-]+):/", $modulo->contents($archivo), $encontrados);

        $alias = array_merge($alias, $encontrados[1]);
    }

    expect($alias)->not->toBeEmpty(
        'FALLA: las rutas generadas no llevan middleware de permiso con parámetro. · FIX: cada ruta '
        . 'lleva el suyo; sin él, el tema 5 no tiene nada que comprobar.'
    );

    foreach (array_unique($alias) as $uno) {
        expect(str_ends_with($uno, 'permission'))->toBeTrue(
            "FALLA: la ruta usa el alias '{$uno}', y la base solo reconoce los que terminan en "
            . "'permission'. · FIX: o el generador escribe el alias de la convención, o la base "
            . 'aprende a leer este — pero los dos tienen que decir lo mismo.'
        );
    }
})->with([
    'single-app'  => [ModuleMode::SingleApp, null],
    'multitenant' => [ModuleMode::Multitenant, 'central'],
]);

it('el módulo entero sigue coherente con la base dentro', function (ModuleMode $modo, ?string $contexto) {
    $this->generateModule('Invoice', $modo, $contexto)->assertCoherent();
})->with([
    'single-app'  => [ModuleMode::SingleApp, null],
    'multitenant' => [ModuleMode::Multitenant, 'central'],
]);

it('la base de un tenant aparta la identificación por dominio', function () {
    // Sin esto el contrato entero se cae en la puerta de los permisos: la ruta de tenant lleva la
    // identificación por dominio —y debe llevarla—, pero en la suite no hay dominio de cliente, así
    // que cada petición muere con un 404 donde la prueba espera un 403. Lo que este contrato mide es
    // la puerta del permiso; quién es el tenant es infraestructura del proyecto, probada aparte.
    config()->set('make-module.tenancy.package', 'stancl');

    $modulo = $this->generateModule('Invoice', ModuleMode::Multitenant, 'tenant');

    $base = $modulo->contents('Invoice/Tests/Feature/Tenant/TenantInvoiceTestCase.php');

    expect($base)->toContain('protected function setUp(): void')
        ->and($base)->toContain('$this->withoutMiddleware([')
        ->and($base)->toContain('PreventAccessFromCentralDomains::class');
});

it('la base de la aplicación central no aparta nada', function () {
    // La central no tiene identificación de tenant que apartar, y un `withoutMiddleware` de más es
    // una puerta abierta en la prueba que nadie pidió.
    config()->set('make-module.tenancy.package', 'stancl');

    $modulo = $this->generateModule('Invoice', ModuleMode::Multitenant, 'central');

    $base = $modulo->contents('Invoice/Tests/Feature/Central/CentralInvoiceTestCase.php');

    expect(str_contains($base, 'withoutMiddleware'))->toBeFalse(
        'FALLA: la base de la central aparta middlewares que allí no estorban.'
    );
});
