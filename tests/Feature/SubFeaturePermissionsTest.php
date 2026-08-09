<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SubFeaturePermissions;

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
    $rutas    = SubFeaturePermissions::routes('central', 'invoices');
    $seeder   = SubFeaturePermissions::permissions('central', 'invoices', 'Billing', 'Invoices');

    $exigidos  = array_unique(array_column($rutas, 'permission'));
    $sembrados = array_column(
        // Solo los de ruta: los de vista no los exige ningún middleware, los consume el `can()` de
        // la pantalla. Que sean dos conjuntos distintos es la regla, no un descuadre (R20).
        array_filter($seeder, fn (array $p): bool => $p['kind'] === 'ruta'),
        'name'
    );

    sort($exigidos);
    sort($sembrados);

    expect($sembrados)->toBe($exigidos,
        'Los permisos que siembra el seeder y los que exigen las rutas tienen que ser el MISMO '
        . 'conjunto. Si esta prueba falla, una de las dos mitades cambió sin la otra.'
    );
});

it('un permiso por ruta, sin reutilizar ninguno (R16)', function () {
    // La norma no admite compartir: seis rutas, seis permisos. Ni siquiera index y list, aunque una
    // alimente a la otra — protegen cosas distintas (la pantalla y sus datos) y cada permiso tiene
    // que poder decir en su descripción QUÉ habilita. Compartido, deja de ser explicable.
    $rutas    = SubFeaturePermissions::routes('central', 'invoices');
    $permisos = SubFeaturePermissions::permissions('central', 'invoices', 'Billing', 'Invoices');

    expect($rutas)->toHaveCount(6);
    // 6 de ruta + 4 de vista: las dos protecciones son independientes (R20).
    expect($permisos)->toHaveCount(10);

    $nombres = array_column($permisos, 'name');

    expect($nombres)->toBe(array_unique($nombres),
        'Hay un permiso repetido. R16: nunca se reutiliza un permiso en dos rutas o acciones.'
    );
});

it('la pantalla y su listado son permisos distintos, y cada uno dice qué habilita', function () {
    // index protege que la opción salga en el menú y la pantalla abra; list protege que dentro se
    // vean los registros. Son las dos descripciones que el usuario lee al asignar el rol.
    $permisos = collect(SubFeaturePermissions::permissions('', 'invoices', 'Billing', 'Invoices'))
        ->keyBy('name');

    expect($permisos->has('invoices_index'))->toBeTrue();
    expect($permisos->has('invoices_list'))->toBeTrue();

    expect($permisos['invoices_index']['description'])->toContain('menú');
    expect($permisos['invoices_list']['description'])->toContain('listado de registros');
});

it('la ruta destroy exige un permiso que termina en _destroy', function () {
    // Norma (SA §7: roles_destroy) y framework coinciden: Laravel nombra la acción `destroy`; DELETE
    // es el verbo HTTP, no el nombre de la acción. El paquete escribía _delete y estaba solo.
    $rutas = SubFeaturePermissions::routes('central', 'invoices');
    $destroy = current(array_filter($rutas, fn (array $r): bool => $r['route'] === 'destroy'));

    expect($destroy['permission'])->toBe('central_invoices_destroy');
});

it('en single-app el permiso no arrastra un guion bajo suelto delante', function () {
    // Sin contexto, el prefijo llega vacío. Concatenar sin comprobar produciría '_invoices_index',
    // que es un permiso distinto del que se busca y no lo tiene nadie.
    expect(SubFeaturePermissions::permissionName('', 'invoices', 'index'))->toBe('invoices_index');
    expect(SubFeaturePermissions::permissionName('central', 'invoices', 'index'))->toBe('central_invoices_index');
});

it('el nombre del permiso va en snake_case aunque la funcionalidad venga en kebab', function () {
    expect(SubFeaturePermissions::key('user-management'))->toBe('user_management');
    expect(SubFeaturePermissions::permissionName('tenant', 'user-management', 'store'))
        ->toBe('tenant_user_management_store');
});

it('cada permiso trae su description en espanol diciendo las tres cosas', function () {
    // Esa descripción es lo ÚNICO que ve quien asigna permisos a un rol desde la interfaz. Vacía, o
    // repitiendo el nombre del permiso, convierte esa pantalla en una lista de claves indescifrables.
    $permisos = SubFeaturePermissions::permissions('central', 'invoices', 'Billing', 'Invoices');

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
    $permisos = SubFeaturePermissions::permissions('central', 'invoices', 'Billing', 'Invoices');

    foreach ($permisos as $permiso) {
        expect($permiso['module'])->toBe('Billing - Invoices');
    }
});

it('el prefijo se normaliza venga como venga, porque llega escrito de dos formas', function () {
    // contexts.json entrega 'central'; ModuleMode::permissionPrefix() entrega 'central_'. Sin
    // normalizar, el segundo produce 'central__invoices_index' —guion doble—, que es un permiso
    // distinto del que exige la ruta: nadie lo tiene y la pantalla no se abre para nadie.
    $conGuion = SubFeaturePermissions::permissionName('central_', 'invoices', 'index');
    $sinGuion = SubFeaturePermissions::permissionName('central', 'invoices', 'index');

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

    foreach (SubFeaturePermissions::routes('central', 'invoices') as $ruta) {
        // `toContain` es variádico en Pest: un segundo argumento sería otro valor a buscar, no un
        // mensaje. El porqué del fallo va en `toBeTrue`, que sí lo acepta.
        expect(str_contains($contenido, $ruta['permission']))->toBeTrue(
            "El archivo de rutas generado no exige el permiso '{$ruta['permission']}'. "
            . 'La ruta y el seeder dejarían de coincidir.'
        );
    }
});

it('los permisos de vista existen y son independientes de los de ruta (R20)', function () {
    // «Ocultar el botón no protege el endpoint, y proteger el endpoint no limpia la pantalla.
    //  Las dos, siempre.» — R20.
    $permisos = collect(SubFeaturePermissions::permissions('', 'invoices', 'Billing', 'Invoices'))
        ->keyBy('name');

    // El servicio y su botón: dos permisos distintos, nunca el mismo.
    expect($permisos->has('invoices_store'))->toBeTrue();
    expect($permisos->has('invoices_view_store'))->toBeTrue();

    expect($permisos->where('kind', 'ruta'))->toHaveCount(6);
    expect($permisos->where('kind', 'vista'))->toHaveCount(4);
});

it('ningun permiso se repite entre ruta y vista', function () {
    $nombres = array_column(
        SubFeaturePermissions::permissions('central', 'invoices', 'Billing', 'Invoices'),
        'name'
    );

    expect($nombres)->toBe(array_unique($nombres),
        'R16: nunca se reutiliza un permiso en dos rutas o acciones distintas.'
    );
});

it('las vistas generadas piden permisos que el seeder va a crear', function () {
    // La prueba que caza B24. Los stubs Vue pedían `can('invoices.create')` —con punto y verbo
    // `create`— mientras el seeder creaba `invoices_store`. Ninguno de los de la vista existía, así
    // que la pantalla cargaba perfecta y SIN UN SOLO BOTÓN, para todo el mundo.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $sembrados = array_column(
        SubFeaturePermissions::permissions('', 'invoices', 'Invoice', 'Invoices'),
        'name'
    );

    $vistas = array_filter($modulo->tree(), fn (string $f): bool => str_ends_with($f, '.vue'));
    expect($vistas)->not->toBeEmpty('El módulo generado tiene que traer sus vistas.');

    $pedidos = [];

    foreach ($vistas as $vista) {
        preg_match_all("/can\('([^']+)'\)/", $modulo->contents($vista), $m);
        $pedidos = [...$pedidos, ...$m[1]];
    }

    expect($pedidos)->not->toBeEmpty('Las vistas generadas tienen que proteger sus acciones (R20).');

    foreach (array_unique($pedidos) as $pedido) {
        expect(in_array($pedido, $sembrados, true))->toBeTrue(
            "La vista pide el permiso '{$pedido}' y el seeder no lo crea. "
            . 'El botón quedaría oculto para todo el mundo, incluido el webmaster.'
        );
    }
});
