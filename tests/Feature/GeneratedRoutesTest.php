<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Generators\Components\RouteGenerator;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * Las rutas generadas: qué forma tienen, y **quién decide** esa forma.
 *
 * La respuesta correcta es el modo, y durante mucho tiempo fue la ausencia de una clave: sin
 * `context` en la configuración, las rutas salían por el camino simple. Eso tenía dos caras.
 *
 * Hacia un lado dejaba la aplicación única como un caso degradado —ahí el contexto está vacío
 * siempre, así que un proyecto sin tenants entraba por el «fallback» de un modo que es de primera
 * clase—. Hacia el otro, y peor, un proyecto multitenant cuyo componente no declarase contexto
 * recibía un archivo **plausible y equivocado**: sin envoltorio de dominios, en el archivo que no
 * era, y exigiendo el permiso de tenant, porque eso es lo que responde el modo cuando no se le dice
 * el contexto. Ninguna señal de que algo hubiera ido mal.
 *
 * Lo que estas pruebas fijan es que la forma la decide el modo, y que cuando falta el dato que el
 * modo necesita **no se escribe nada** en vez de escribirse algo verosímil.
 */

it('en single-app las rutas van sin contexto y con el permiso desnudo', function () {
    $rutas = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Routes/web.php');

    expect($rutas)->toContain("Route::prefix('invoices')");

    // Ninguna de las envolturas que solo tienen sentido habiendo tenants.
    expect($rutas)->not->toContain('central_domains');
    expect($rutas)->not->toContain('tenant-permission');
    expect($rutas)->not->toContain('central-permission');
});

it('las seis rutas se generan, cada una con su permiso propio', function () {
    // Ninguna comparte permiso con otra, ni siquiera index y list, que alimentan la misma pantalla:
    // protegen cosas distintas —abrirla, y ver sus datos— y cada permiso tiene que poder explicarse
    // solo en la pantalla donde se asignan a un rol.
    $rutas = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Routes/web.php');

    foreach (['index', 'list', 'store', 'show', 'update', 'destroy'] as $accion) {
        expect(str_contains($rutas, "->middleware('permission:invoices_{$accion}')"))->toBeTrue(
            "FALLA: la ruta '{$accion}' no exige su permiso propio, o lo exige con otro nombre. "
            . '· FIX: sale de SubFeaturePermissions, el mismo sitio del que los crea el seeder; si '
            . "aquí dice otra cosa, la pantalla dará 403 a todo el mundo.\nEl archivo dice:\n" . $rutas
        );
    }
});

it('en single-app, pedir un contexto es un error y el comando lo dice', function () {
    // El otro lado de «manda el modo». Si la forma la decidiera la clave, bastaría con que un
    // contexts.json heredado de otro proyecto declarase algo para que una aplicación sin tenants
    // empezara a generar con eje de contexto. El comando se niega antes de escribir nada.
    $modulo = null;

    try {
        $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp, 'central');
    } catch (Throwable $e) {
        expect($e->getMessage())->toContain('no tiene contextos');

        return;
    }

    expect($modulo)->toBeNull(
        'FALLA: se generó un módulo single-app con contexto. · FIX: el comando tiene que negarse; '
        . 'en una aplicación única no hay contextos que separar y el prefijo sería ruido.'
    );
});

it('en un modo con eje de contexto, sin contexto no se escribe ninguna ruta', function () {
    // Se prueba sobre el generador y no sobre el comando porque el comando **rellena el contexto
    // por su cuenta** cuando no se le pasa (ver la nota de la fase: asume `central`). Esta guarda
    // es la de más abajo, la que protege a los otros llamadores — `add-entity` y la generación por
    // configuración dinámica— de escribir un archivo verosímil y equivocado.
    config()->set('make-module.mode', ModuleMode::MultitenantPerTenant->value);

    $rutasDir = $this->tempPath('Modules/Sin/Routes');

    $generador = new RouteGenerator('Sin', $this->tempPath('Modules/Sin'), true, 'Sin', [
        'name'          => 'Sin',
        'subFeature'    => 'Sin',
        'functionality' => 'sins',
        // sin 'context', que es justo el caso
    ]);

    $generador->generate();

    expect(file_exists("{$rutasDir}/web.php"))->toBeFalse(
        'FALLA: se escribieron rutas sin saber el contexto. · FIX: en un modo con eje de contexto, '
        . 'sin contexto no se puede saber qué dominio sirve la ruta ni qué permiso la protege. Lo '
        . 'que se escriba será plausible y equivocado, y nadie volverá a mirarlo porque el comando '
        . 'habrá terminado en verde.'
    );
});

it('en multitenant las rutas llevan el prefijo y el middleware de su contexto', function () {
    $rutas = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')
        ->contents('Routes/web.php');

    expect(str_contains($rutas, "Route::prefix('central-invoices')"))->toBeTrue(
        'FALLA: la URL no lleva el prefijo del contexto. · FIX: en multitenant el contexto entra '
        . 'en la URL, o dos contextos con la misma subfuncionalidad chocan.'
    );

    expect(str_contains($rutas, "->middleware('central-permission:central_invoices_index')"))->toBeTrue(
        'FALLA: el permiso no lleva el prefijo de su contexto, o el middleware no es el central. '
        . '· FIX: los dos los responde el modo; si aquí sale el de tenant, es que la forma la '
        . "decidió otra cosa.\nEl archivo dice:\n" . $rutas
    );
});
