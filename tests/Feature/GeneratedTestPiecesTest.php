<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Tests\Support\GeneratedModule;

/**
 * Las piezas del contrato, con contenido de verdad — el fin de `assertTrue(true)`.
 *
 * **Lo que se vigila aquí es el techo tanto como el contenido.** El contrato fija 2 pruebas para el
 * tema 0, 2 para los temas 1-2, 3 para los temas 3-5 y 4 para el tema 8, y ese número **no crece**: una tabla nueva,
 * una columna nueva o una ruta nueva entran en el recorrido de una prueba que ya existe. Si alguien añade un método de test
 * al stub, esta suite lo dice — porque el día que cada campo sume una prueba, la suite del proyecto
 * empieza a frenar al equipo que debía proteger.
 *
 * Y se comprueba que **ninguna pieza enumera**: todas recorren el manifiesto. Una lista literal
 * dentro de un test es una segunda verdad que solo se actualiza la mitad de las veces.
 */

/** Cuántos métodos de prueba declara un archivo generado. */
function metodosDePrueba(string $php): int
{
    preg_match_all('/public function test_\w+\(/', $php, $encontrados);

    return count($encontrados[0]);
}

it('el módulo generado trae las piezas del contrato, con su techo de pruebas', function (
    ModuleMode $modo,
    ?string $contexto,
    string $carpeta,
    string $prefijo,
) {
    $modulo = $this->generateModule('Invoice', $modo, $contexto);

    $modulo->assertTreeHas([
        "{$carpeta}/{$prefijo}ScaffoldTest.php",
        "{$carpeta}/{$prefijo}SchemaTest.php",
        "{$carpeta}/{$prefijo}PermissionsTest.php",
        "{$carpeta}/{$prefijo}DeploymentTest.php",
        "{$carpeta}/{$prefijo}HttpTest.php",
    ], 'R32: el grupo de la subfuncionalidad son 6 piezas fijas; estas son las de los temas 0, 1-2, 3-5, 8 y 7.');

    expect(metodosDePrueba($modulo->contents("{$carpeta}/{$prefijo}ScaffoldTest.php")))->toBe(
        2,
        'FALLA: el tema 0 no tiene exactamente 2 pruebas. · FIX: son fijas —las piezas existen, y su '
        . 'contenido cumple—; lo que se añada entra dentro de una de las dos, no como una tercera (R33).'
    );

    expect(metodosDePrueba($modulo->contents("{$carpeta}/{$prefijo}SchemaTest.php")))->toBe(
        2,
        'FALLA: los temas 1-2 no tienen exactamente 2 pruebas. · FIX: una recorre las tablas y otra '
        . 'las columnas; una tabla nueva no suma una prueba, entra en el recorrido (R33).'
    );

    expect(metodosDePrueba($modulo->contents("{$carpeta}/{$prefijo}DeploymentTest.php")))->toBe(
        4,
        'FALLA: el tema 8 no tiene exactamente 4 pruebas. · FIX: levanta, es idempotente, no '
        . 'destruye, y producción levanta. Son las cuatro garantías del despliegue (R33).'
    );

    expect(metodosDePrueba($modulo->contents("{$carpeta}/{$prefijo}PermissionsTest.php")))->toBe(
        3,
        'FALLA: los temas 3-5 no tienen exactamente 3 pruebas. · FIX: permiso único por ruta, '
        . 'permisos en la base, y la puerta en los dos sentidos. Una ruta nueva entra en el '
        . 'recorrido de las tres, no como una cuarta (R33).'
    );
})->with([
    'single-app'  => [ModuleMode::SingleApp, null, 'Tests/Feature/Invoice', 'Invoice'],
    'multitenant' => [ModuleMode::MultitenantPerTenant, 'central', 'Tests/Feature/Central/Invoice', 'CentralInvoice'],
]);

it('ninguna pieza generada es un placebo', function () {
    // B4 en una línea: lo que el paquete emitía era `assertTrue(true)` — verde sin probar nada.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    foreach (['InvoiceScaffoldTest', 'InvoiceSchemaTest', 'InvoicePermissionsTest', 'InvoiceDeploymentTest', 'InvoiceHttpTest'] as $pieza) {
        $contenido = $modulo->contents("Tests/Feature/Invoice/{$pieza}.php");

        // Sobre el código sin comentarios: el docblock del tema 7 explica por qué un assertTrue(true)
        // sería cobertura fingida, y nombrarlo no es escribirlo. Es la tercera vez en esta fase que
        // aparece la misma confusión — se prohíbe la operación, nunca la palabra.
        expect(str_contains(soloCodigo($contenido), 'assertTrue(true)'))->toBeFalse(
            "FALLA: {$pieza} contiene assertTrue(true). · FIX: una prueba que pasa siempre es peor "
            . 'que ninguna, porque parece cobertura.'
        );

        expect(str_contains($contenido, 'FALLA:'))->toBeTrue(
            "FALLA: {$pieza} tiene aserciones sin mensaje FALLA/FIX. · FIX: el mensaje dice el error "
            . 'y el paso exacto para arreglarlo; quien lo lee no debería tener que investigar (R30).'
        );
    }
});

it('las piezas recorren el manifiesto en vez de enumerar', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $scaffold = $modulo->contents('Tests/Feature/Invoice/InvoiceScaffoldTest.php');
    $schema   = $modulo->contents('Tests/Feature/Invoice/InvoiceSchemaTest.php');

    expect(str_contains($scaffold, 'InvoiceContract::SCAFFOLD'))->toBeTrue(
        'FALLA: el tema 0 no recorre el andamiaje del manifiesto. · FIX: recorrerlo es lo que hace '
        . 'que una pieza nueva quede cubierta sin tocar el test (R76).'
    );

    expect(str_contains($schema, 'InvoiceContract::TABLES'))->toBeTrue(
        'FALLA: los temas 1-2 no recorren las tablas del manifiesto. · FIX: igual que arriba; una '
        . 'columna nueva se declara en el contrato y esta prueba la comprueba sola.'
    );

    // Y ninguna nombra una tabla o una columna a mano: eso sería la segunda lista.
    expect(str_contains($schema, "'invoices'"))->toBeFalse(
        'FALLA: el test del esquema nombra la tabla literalmente. · FIX: la tabla la declara el '
        . 'manifiesto; escribirla también aquí obliga a cambiar dos sitios (R76).'
    );
});

it('el esquema se levanta con el seeder, no con RefreshDatabase', function () {
    // El defecto que se vio abriendo el archivo generado, no en la suite: `RefreshDatabase` recorre
    // `database/migrations`, y las migraciones de un módulo no viven ahí — las declara su trait
    // MigrationsList y las aplica su seeder. Con RefreshDatabase, los temas 1-2 fallarían SIEMPRE,
    // buscando una tabla que nadie creó, y el mensaje culparía a la subfuncionalidad.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    // Sobre el código SIN comentarios: el docblock del stub explica por qué no se usa
    // RefreshDatabase, y nombrarlo no es usarlo. Es la misma lección de la fase 3 — se prohíbe la
    // operación, no la palabra; si no, el archivo generado tiene que callar justo lo que más falta
    // hace entender.
    $schema = soloCodigo($modulo->contents('Tests/Feature/Invoice/InvoiceSchemaTest.php'));

    expect(str_contains($schema, 'RefreshDatabase'))->toBeFalse(
        'FALLA: el test del esquema usa RefreshDatabase. · FIX: levanta la subfuncionalidad con su '
        . 'seeder de stage, que es el vehículo del despliegue (R75); RefreshDatabase no ve las '
        . 'migraciones del módulo.'
    );

    expect(str_contains($schema, 'levantarSubfuncionalidad'))->toBeTrue(
        'FALLA: el test del esquema no levanta la subfuncionalidad antes de mirarla. · FIX: el '
        . 'setUp() la levanta con el mismo seeder que corre en el servidor.'
    );

    expect(str_contains($modulo->contents('Tests/Feature/Invoice/InvoiceTestCase.php'), "seed(InvoiceContract::SCAFFOLD['stage_seeder'])"))->toBeTrue(
        'FALLA: la base no ejecuta el seeder de stage de la subfuncionalidad. · FIX: es lo que crea '
        . 'sus tablas; sin eso no hay esquema que comprobar.'
    );
});

it('las piezas cuelgan de la base del grupo, no del TestCase del proyecto', function (ModuleMode $modo, ?string $contexto, string $carpeta, string $prefijo) {
    // Si una pieza extendiera directamente el TestCase del proyecto se quedaría sin la derivación, y
    // volvería a resolver por su cuenta lo que la base ya resuelve para todas.
    $modulo = $this->generateModule('Invoice', $modo, $contexto);

    foreach (['ScaffoldTest', 'SchemaTest', 'PermissionsTest', 'DeploymentTest', 'HttpTest'] as $pieza) {
        $contenido = $modulo->contents("{$carpeta}/{$prefijo}{$pieza}.php");

        expect(str_contains($contenido, "extends {$prefijo}TestCase"))->toBeTrue(
            "FALLA: {$prefijo}{$pieza} no extiende la base del grupo. · FIX: la derivación de rutas, "
            . 'permisos y usuarios vive ahí; sin ella la pieza tiene que reimplementarla.'
        );
    }
})->with([
    'single-app'  => [ModuleMode::SingleApp, null, 'Tests/Feature/Invoice', 'Invoice'],
    'multitenant' => [ModuleMode::MultitenantPerTenant, 'central', 'Tests/Feature/Central/Invoice', 'CentralInvoice'],
]);

it('el tema 7 no tiene techo, pero sí borde', function () {
    // El único sin número fijo — ahí entra lo que el negocio exija. Lo que no puede hacer es repetir
    // el 403 del tema 5: esa duplicación es la que llevó a un proyecto de la casa a 62 métodos
    // redundantes, y a que la suite dejara de correrse.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $http = soloCodigo($modulo->contents('Tests/Feature/Invoice/InvoiceHttpTest.php'));

    expect(metodosDePrueba($http))->toBeGreaterThan(
        3,
        'FALLA: el tema 7 trae menos comportamientos de los que el borde exige. · FIX: listado, '
        . 'entrada inválida, alta y lectura, identificador ajeno y borrado lógico (R34).'
    );

    expect(str_contains($http, '403'))->toBeFalse(
        'FALLA: el tema 7 comprueba un 403. · FIX: eso ya está probado en el tema 5; repetirlo es la '
        . 'duplicación que infla la suite hasta que deja de correrse (R34).'
    );

    // Y lo que el paquete no puede saber se salta DICIÉNDOLO, en vez de fingir que pasa.
    expect(str_contains($http, 'markTestSkipped'))->toBeTrue(
        'FALLA: las pruebas que dependen del negocio no dicen que están pendientes. · FIX: un skip '
        . 'que explica el paso siguiente es una tarea visible; un assertTrue(true) es cobertura fingida.'
    );
});

it('el aislamiento entre tenants solo se genera donde puede fallar', function () {
    // En una aplicación sin tenants no hay otro inquilino del que aislarse: esa prueba no podría
    // fallar nunca, y una prueba que no puede fallar es ruido que se acaba ignorando.
    $single = $this->generateModule('Invoice', ModuleMode::SingleApp);
    $multi  = $this->generateModule('Billing', ModuleMode::MultitenantPerTenant, 'central');

    expect(str_contains($single->contents('Tests/Feature/Invoice/InvoiceHttpTest.php'), 'otro_contexto'))->toBeFalse(
        'FALLA: single-app genera la prueba de aislamiento entre contextos. · FIX: la decide el '
        . 'modo; sin eje de contexto no hay de quién aislarse.'
    );

    expect(str_contains($multi->contents('Tests/Feature/Central/Billing/CentralBillingHttpTest.php'), 'otro_contexto'))->toBeTrue(
        'FALLA: multitenant NO genera la prueba de aislamiento. · FIX: sin ella el multitenant no '
        . 'está probado — todo puede estar en verde y un usuario leer los datos de otro inquilino.'
    );
});

it('la vista tiene su prueba de permisos, con su manifiesto propio', function (ModuleMode $modo, ?string $contexto, string $carpeta, string $componente) {
    // Tema 6. El manifiesto de PHP no se puede leer desde Vitest, así que el lado JS tiene el suyo —
    // uno, generado de la misma derivación—. La alternativa era que cada prueba de cada vista
    // repitiera la lista de acciones.
    $modulo = $this->generateModule('Invoice', $modo, $contexto);

    $modulo->assertTreeHas([
        "{$carpeta}/{$componente}Contract.js",
        "{$carpeta}/{$componente}Index.test.js",
    ], 'R32: el tema 6 vive en resources/js/__tests__, junto a su manifiesto del lado de la vista.');

    $prueba = $modulo->contents("{$carpeta}/{$componente}Index.test.js");

    expect(str_contains($prueba, 'VIEW_ACTIONS'))->toBeTrue(
        'FALLA: la prueba de la vista no recorre el manifiesto. · FIX: recorrerlo es lo que hace que '
        . 'una acción nueva quede cubierta sin tocar el test (R76).'
    );

    expect(str_contains($prueba, 'data-test'))->toBeTrue(
        'FALLA: la prueba busca los elementos por su texto. · FIX: usa el ancla data-test; cambiar '
        . '«Editar» por «Modificar» es un cambio de copy y no puede romper una prueba de permisos.'
    );
})->with([
    'single-app'  => [ModuleMode::SingleApp, null, 'resources/js/__tests__/Invoice', 'Invoice'],
    'multitenant' => [ModuleMode::MultitenantPerTenant, 'central', 'resources/js/__tests__/Central/Invoice', 'CentralInvoice'],
]);

it('el import de la prueba de vista apunta al componente que existe', function (ModuleMode $modo, ?string $contexto, string $carpeta, string $componente, string $vista) {
    // El cruce que ningún chequeo de PHP puede hacer: un import roto en JavaScript no lo ve `php -l`,
    // y en multitenant el contexto puede tener dos segmentos —Tenant/Shared—, así que un `../..`
    // fijo dejaría el import apuntando al vacío.
    $modulo = $this->generateModule('Invoice', $modo, $contexto);

    preg_match("/from '([^']+\.vue)'/", $modulo->contents("{$carpeta}/{$componente}Index.test.js"), $encontrado);

    expect($encontrado)->not->toBeEmpty('La prueba de la vista tiene que importar el componente.');

    $resuelto = realpath($modulo->path($carpeta) . '/' . $encontrado[1]);

    expect($resuelto)->toBe(
        realpath($modulo->path($vista)),
        "FALLA: el import '{$encontrado[1]}' no resuelve al componente generado. · FIX: los saltos "
        . 'hacia arriba se cuentan desde la carpeta de la prueba; en multitenant el contexto puede '
        . "tener dos segmentos.\n\nLo generado fue:\n  - " . implode("\n  - ", $modulo->tree())
    );
})->with([
    'single-app'  => [ModuleMode::SingleApp, null, 'resources/js/__tests__/Invoice', 'Invoice', 'resources/js/Pages/Invoice/InvoiceIndex.vue'],
    'multitenant' => [ModuleMode::MultitenantPerTenant, 'central', 'resources/js/__tests__/Central/Invoice', 'CentralInvoice', 'resources/js/Pages/Central/Invoice/CentralInvoiceIndex.vue'],
]);

it('la vista lleva el ancla que la prueba busca', function () {
    // Las dos mitades del par: el `data-test` que emite la vista y el que la prueba consulta. Si
    // divergen, la prueba dice que el botón no se ve —y pasa la mitad que no debía pasar.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $vista     = $modulo->contents('resources/js/Pages/Invoice/InvoiceIndex.vue');
    $manifiesto = $modulo->contents('resources/js/__tests__/Invoice/InvoiceContract.js');

    preg_match_all("/ancla: '([^']+)'/", $manifiesto, $anclas);

    expect($anclas[1])->toHaveCount(4, 'Las cuatro acciones de la vista, ni una menos.');

    foreach ($anclas[1] as $ancla) {
        expect(str_contains($vista, "data-test=\"{$ancla}\""))->toBeTrue(
            "FALLA: la vista no lleva el ancla '{$ancla}' que su prueba busca. · FIX: el atributo "
            . 'data-test va junto al v-if del permiso; sin él la prueba del tema 6 no encuentra nada '
            . 'y pasa por la razón equivocada.'
        );
    }
});

it('la prueba de la vista monta el listado con datos, no vacío', function () {
    // Tres de las cuatro acciones —ver, editar y eliminar— viven dentro de la fila de la tabla. Una
    // prueba que monte el listado sin datos no las encuentra **aunque el permiso esté concedido**, y
    // falla diciendo «el permiso no llega a la vista»: señala al sitio equivocado y manda a buscar un
    // typo que no existe.
    //
    // Para que haya filas hacen falta tres cosas, y las tres se comprueban aquí: el doble de `axios`
    // —que en el proyecto es global, no un import—, el de `route`, y la espera a que la petición de
    // `onMounted` se resuelva antes de mirar el DOM.
    $prueba = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('resources/js/__tests__/Invoice/InvoiceIndex.test.js');

    expect(str_contains($prueba, 'globalThis.axios'))->toBeTrue(
        'FALLA: la prueba de la vista no declara el doble de axios. · FIX: en el proyecto es una '
        . 'global que pone Laravel; sin ella la petición revienta dentro del try y la tabla se queda '
        . 'vacía — sin filas, tres de las cuatro acciones no existen.'
    );

    expect(str_contains($prueba, 'globalThis.route'))->toBeTrue(
        'FALLA: la prueba de la vista no declara el doble de route(). · FIX: mismo caso que axios; '
        . 'es una global del proyecto, no un import del componente.'
    );

    expect(str_contains($prueba, 'flushPromises'))->toBeTrue(
        'FALLA: la prueba mira el DOM sin esperar a la primera página. · FIX: el listado pide sus '
        . 'datos en onMounted; sin la espera se comprueba el DOM del «Cargando...», donde no hay tabla.'
    );
});

it('el módulo entero sigue coherente con las piezas dentro', function (ModuleMode $modo, ?string $contexto) {
    $this->generateModule('Invoice', $modo, $contexto)->assertCoherent();
})->with([
    'single-app'  => [ModuleMode::SingleApp, null],
    'multitenant' => [ModuleMode::MultitenantPerTenant, 'central'],
]);
