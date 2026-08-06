<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\RoutePermissions;

/**
 * La novena pareja de este paquete, cortada antes de nacer.
 *
 * El generador de rutas escribe `middleware('...:central_invoices_index')` y el PermissionsSeeder
 * tiene que crear **ese** permiso. Si cada uno lo calcula por su cuenta, el día que cambie una acción
 * el seeder creará un permiso que ninguna ruta pide —o la ruta pedirá uno que nadie creó, y la
 * pantalla dará 403 para todo el mundo, incluido el administrador—.
 *
 * Van ocho defectos con esa forma exacta en las fases 1 y 2. Estas pruebas existen para que no haya
 * un noveno: no comprueban que la clase «funcione», comprueban que **las dos mitades no puedan
 * separarse**.
 */

it('las rutas generadas exigen exactamente los permisos que siembra el seeder', function () {
    // El contraste en los DOS sentidos, que es el único que sirve: un permiso sembrado que ninguna
    // ruta pide es basura en la tabla; una ruta que pide un permiso no sembrado es una pantalla
    // inaccesible.
    $rutas    = RoutePermissions::routes('central', 'invoices');
    $seeder   = RoutePermissions::permissions('central', 'invoices', 'Billing', 'Invoices');

    $exigidos  = array_unique(array_column($rutas, 'permission'));
    $sembrados = array_column($seeder, 'name');

    sort($exigidos);
    sort($sembrados);

    expect($sembrados)->toBe($exigidos,
        'Los permisos que siembra el seeder y los que exigen las rutas tienen que ser el MISMO '
        . 'conjunto. Si esta prueba falla, una de las dos mitades cambió sin la otra.'
    );
});

it('son seis rutas pero cinco permisos: index y list comparten', function () {
    // Es el detalle que un segundo cálculo independiente erraría: la pantalla y el endpoint que la
    // alimenta son la misma autorización — quien puede ver el listado puede pedir sus datos.
    $rutas   = RoutePermissions::routes('central', 'invoices');
    $permisos = RoutePermissions::permissions('central', 'invoices', 'Billing', 'Invoices');

    expect($rutas)->toHaveCount(6);
    expect($permisos)->toHaveCount(5);

    $deIndex = array_values(array_filter($rutas, fn (array $r): bool => in_array($r['route'], ['index', 'list'], true)));

    expect($deIndex[0]['permission'])->toBe($deIndex[1]['permission'],
        'index y list comparten permiso. Separarlos dejaría la pantalla visible y su listado vacío.'
    );
});

it('la ruta destroy exige un permiso que termina en _delete, no en _destroy', function () {
    // El nombre del método del controlador y el verbo del permiso nunca han coincidido. Quien lo
    // dedujera «a ojo» escribiría _destroy y rompería la autorización de esa ruta.
    $rutas = RoutePermissions::routes('central', 'invoices');
    $destroy = current(array_filter($rutas, fn (array $r): bool => $r['route'] === 'destroy'));

    expect($destroy['permission'])->toBe('central_invoices_delete');
});

it('en single-app el permiso no arrastra un guion bajo suelto delante', function () {
    // Sin contexto, el prefijo llega vacío. Concatenar sin comprobar produciría '_invoices_index',
    // que es un permiso distinto del que se busca y no lo tiene nadie.
    expect(RoutePermissions::permissionName('', 'invoices', 'index'))->toBe('invoices_index');
    expect(RoutePermissions::permissionName('central', 'invoices', 'index'))->toBe('central_invoices_index');
});

it('el nombre del permiso va en snake_case aunque la funcionalidad venga en kebab', function () {
    expect(RoutePermissions::key('user-management'))->toBe('user_management');
    expect(RoutePermissions::permissionName('tenant', 'user-management', 'store'))
        ->toBe('tenant_user_management_store');
});

it('cada permiso trae su description en espanol diciendo las tres cosas', function () {
    // Esa descripción es lo ÚNICO que ve quien asigna permisos a un rol desde la interfaz. Vacía, o
    // repitiendo el nombre del permiso, convierte esa pantalla en una lista de claves indescifrables.
    $permisos = RoutePermissions::permissions('central', 'invoices', 'Billing', 'Invoices');

    foreach ($permisos as $permiso) {
        expect($permiso['description'])
            ->not->toBeEmpty()
            ->toContain('Permite ')
            ->toContain('Sin este permiso,')
            ->not->toBe($permiso['name']);
    }
});

it('el module agrupa la interfaz como Modulo - SubFuncionalidad', function () {
    // Es lo que produce una pestaña por subfuncionalidad en la pantalla de roles, en vez de una
    // lista plana de doscientos permisos sueltos.
    $permisos = RoutePermissions::permissions('central', 'invoices', 'Billing', 'Invoices');

    foreach ($permisos as $permiso) {
        expect($permiso['module'])->toBe('Billing - Invoices');
    }
});

it('el prefijo se normaliza venga como venga, porque llega escrito de dos formas', function () {
    // contexts.json entrega 'central'; ModuleMode::permissionPrefix() entrega 'central_'. Sin
    // normalizar, el segundo produce 'central__invoices_index' —guion doble—, que es un permiso
    // distinto del que exige la ruta: nadie lo tiene y la pantalla no se abre para nadie.
    $conGuion = RoutePermissions::permissionName('central_', 'invoices', 'index');
    $sinGuion = RoutePermissions::permissionName('central', 'invoices', 'index');

    expect($conGuion)->toBe($sinGuion)->toBe('central_invoices_index');
});

it('el generador de rutas escribe los permisos que dice RoutePermissions, no los suyos', function () {
    // La prueba que cierra el par de verdad: no compara dos funciones de la misma clase, compara la
    // clase contra el ARCHIVO que el paquete acaba de escribir.
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantShared, 'central');

    $rutas = collect($modulo->phpFiles())
        ->first(fn (string $f): bool => str_contains($f, 'Routes/'));

    expect($rutas)->not->toBeNull('El módulo generado tiene que traer su archivo de rutas.');

    $contenido = $modulo->contents($rutas);

    foreach (RoutePermissions::routes('central', 'invoices') as $ruta) {
        // `toContain` es variádico en Pest: un segundo argumento sería otro valor a buscar, no un
        // mensaje. El porqué del fallo va en `toBeTrue`, que sí lo acepta.
        expect(str_contains($contenido, $ruta['permission']))->toBeTrue(
            "El archivo de rutas generado no exige el permiso '{$ruta['permission']}'. "
            . 'La ruta y el seeder dejarían de coincidir.'
        );
    }
});
