<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * `add-entity` — el otro camino por el que nace una subfuncionalidad, y el que se olvidaba.
 *
 * Un módulo se crea una vez y crece muchas: la segunda subfuncionalidad, y la tercera, entran por
 * `innodite:add-entity`. Si ese camino emite menos piezas que `make-module`, el módulo termina con
 * subfuncionalidades de primera y de segunda clase — unas con su grupo de pruebas y otras sin él —
 * y nadie lo nota, porque lo que falta no da error: simplemente no está.
 *
 * Es lo que ya pasó en la fase 3 con los seeders (B25). Aquí se cierra por el otro lado: **las seis
 * piezas del contrato** también nacen por este camino.
 */

/** Ejecuta `innodite:add-entity` y devuelve [código de salida, salida]. */
function agregarEntidad(string $modulo, string $entidad, ?string $contexto = null): array
{
    $opciones = [
        'module'           => $modulo,
        'entity'           => $entidad,
        '--no-routes'      => true,
        '--no-interaction' => true,
    ];

    if ($contexto !== null) {
        $opciones['--context'] = $contexto;
    }

    return [Artisan::call('innodite:add-entity', $opciones), Artisan::output()];
}

it('agrega una entidad a un módulo existente sin romperse', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    [$codigo, $salida] = agregarEntidad($modulo->name, 'Payment', 'central');

    expect($codigo)->toBe(
        0,
        "FALLA: `innodite:add-entity` terminó con código {$codigo}. · FIX: es el camino por el que "
        . "crece un módulo ya creado; si se cae, la única forma de añadir una subfuncionalidad es a "
        . "mano.\n\nSalida del comando:\n{$salida}"
    );
});

it('la entidad agregada nace con las seis piezas del contrato de pruebas', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    [$codigo, $salida] = agregarEntidad($modulo->name, 'Payment', 'central');

    expect($codigo)->toBe(0, "El comando falló antes de poder mirar las pruebas.\n\n{$salida}");

    $grupo = "{$modulo->path}/Tests/Feature/Central/Payment";

    $piezas = [
        'CentralPaymentContract.php'    => 'el manifiesto: sin él las otras cinco no tienen de dónde derivar',
        'CentralPaymentTestCase.php'    => 'la base del grupo, donde vive la derivación',
        'CentralPaymentScaffoldTest.php' => 'tema 0 — el andamiaje existe y su contenido cumple',
        'CentralPaymentSchemaTest.php'  => 'temas 1-2 — las tablas y sus columnas',
        'CentralPaymentPermissionsTest.php' => 'temas 3-5 — los permisos, únicos y aplicados',
        'CentralPaymentDeploymentTest.php'  => 'tema 8 — el despliegue, ejecutado',
        'CentralPaymentHttpTest.php'    => 'tema 7 — el comportamiento por HTTP',
    ];

    foreach ($piezas as $archivo => $porque) {
        expect(File::exists("{$grupo}/{$archivo}"))->toBeTrue(
            "FALLA: `add-entity` no escribió '{$archivo}' ({$porque}). · FIX: `make-module` sí la "
            . 'escribe. Las dos puertas por las que nace una subfuncionalidad tienen que emitir el '
            . 'mismo grupo, o el módulo acaba con subfuncionalidades sin pruebas y nada lo avisa.'
        );
    }

    $vue = "{$modulo->path}/resources/js/__tests__/Central/Payment";

    expect(File::exists("{$vue}/CentralPaymentIndex.test.js"))->toBeTrue(
        'FALLA: `add-entity` no escribió la prueba de la vista (tema 6). · FIX: es la que comprueba '
        . 'que una acción sin permiso no se dibuja; sin ella el permiso se aplica en el backend y '
        . 'el botón sigue ahí.'
    );
});

it('ni make-module ni add-entity vuelven a emitir las piezas que no están en el contrato', function () {
    // B4 por el lado de la retirada: los tres archivos que el paquete escribía con `assertTrue(true)`
    // dentro. Ninguno está en los 9 temas — el Support lo sustituye la base del grupo, y un
    // `ServiceTest` por clase infla la suite sin afirmar nada. Mientras el stub siga en el árbol,
    // vuelven en cuanto alguien reconecte el generador «porque estaba ahí».
    foreach (['test.stub', 'test-unit.stub', 'test-support.stub'] as $stub) {
        expect(File::exists(dirname(__DIR__, 2) . "/stubs/contextual/{$stub}"))->toBeFalse(
            "FALLA: '{$stub}' sigue en el paquete. · FIX: se retira con el generador que lo leía; "
            . 'un stub sin lector es una invitación a volver a emitirlo.'
        );
    }

    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    [$codigo, $salida] = agregarEntidad($modulo->name, 'Payment', 'central');

    expect($codigo)->toBe(0, "El comando falló antes de poder mirar el árbol.\n\n{$salida}");

    foreach ($modulo->tree() as $archivo) {
        expect($archivo)->not->toMatch(
            '#(ServiceTest|Support)\.php$#',
            "FALLA: el módulo generado trae '{$archivo}', que no es ninguna de las 6 piezas del "
            . 'contrato (R32). · FIX: el grupo son manifiesto + base + los 9 temas repartidos en '
            . 'Scaffold, Schema, Permissions, Deployment, Http y Vitest. Nada más.'
        );
    }
});

it('los permisos de la entidad agregada son los suyos, no los del módulo', function () {
    // El defecto que esconde este camino: `functionality` se deriva del nombre del MÓDULO. Para la
    // entidad principal da igual —módulo y subfuncionalidad son lo mismo—, pero al agregar la
    // segunda, sus permisos y sus rutas saldrían con el nombre de la primera. Dos subfuncionalidades
    // pidiendo el mismo permiso: quien abra una, abre la otra.
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    [$codigo, $salida] = agregarEntidad($modulo->name, 'Payment', 'central');

    expect($codigo)->toBe(0, "El comando falló antes de poder mirar el contrato.\n\n{$salida}");

    $contrato = File::get("{$modulo->path}/Tests/Feature/Central/Payment/CentralPaymentContract.php");

    // `toContain()` no acepta mensaje: cada argumento suyo es otra aguja. Y el mensaje es justo lo
    // que hace útil un fallo, así que la comprobación se hace fuera y se afirma sobre el booleano.
    expect(str_contains($contrato, "ROUTE_PREFIX = 'central.payments.'"))->toBeTrue(
        'FALLA: las rutas de la entidad se filtran por el prefijo de OTRA subfuncionalidad. · FIX: '
        . 'la funcionalidad se deriva de la ENTIDAD cuando se agrega una, no del módulo. Con el '
        . 'prefijo del módulo, los temas 3-5 recorren un conjunto vacío — y una prueba que recorre '
        . 'la nada pasa en verde.'
    );

    expect(str_contains($contrato, 'central_payments_view_store'))->toBeTrue(
        'FALLA: el contrato de la entidad declara un permiso que no es el suyo. · FIX: misma causa '
        . 'que el prefijo de ruta; si no, la segunda subfuncionalidad hereda los permisos de la '
        . 'primera y ambas abren con la misma llave.'
    );

    expect(str_contains($contrato, "'payments' => ["))->toBeTrue(
        'FALLA: el contrato no apunta a la tabla de la entidad. · FIX: misma causa que el permiso — '
        . 'la tabla sale de la subfuncionalidad, no del módulo.'
    );
});
