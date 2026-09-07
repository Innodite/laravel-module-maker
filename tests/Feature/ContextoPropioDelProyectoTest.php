<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ContextOption;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * Un proyecto puede declarar un contexto suyo, y el paquete genera en él.
 *
 * ⭐ **Es una capacidad prometida por escrito en tres sitios y negada por el código.** El catálogo de
 * fábrica lo dice —«un proyecto que necesite otro lo declara aquí»—, el docblock de
 * `ModuleMode::supportedContextKeys()` lo dice, y el README lo dice. Pero `ContextOption::check()`
 * rechazaba toda clave que el MODO no conociera, y el modo solo conoce las dos de fábrica.
 *
 * Lo que hace grave al defecto es que **todo lo demás ya estaba listo**: el generador lee
 * `permission_prefix`, `permission_middleware` y `route_middleware` del catálogo y solo cae en el
 * modo cuando vienen vacíos; el archivo de rutas sale de `route_file`; la conexión, de
 * `connection_key`; y `is_tenant` dice si es un inquilino. Nueve piezas correctas y una guarda que
 * las contradecía — la forma de defecto que este paquete repite: dos mitades que cada una es correcta
 * y ya no coinciden.
 *
 * ⚠️ Nota de uso: aquí se comprueba con `str_contains(...)->toBeTrue($mensaje)` y no con
 * `toContain($aguja, $mensaje)`, porque el segundo argumento de `toContain` es **otro valor a
 * buscar**, no el mensaje de fallo — el mensaje acaba dentro de la aserción y el rojo no dice nada
 * útil. Se documenta porque ya se cayó dos veces en la misma sesión.
 */

/** El catálogo de un proyecto que declaró un contexto propio junto a los dos de fábrica. */
function catalogoConContextoPropio(): array
{
    return [
        'central' => [
            'id' => 'central', 'is_tenant' => false, 'folder' => 'Central',
            'route_file' => 'web.php', 'connection_key' => 'central',
        ],
        'tenant' => [
            'id' => 'tenant', 'is_tenant' => true, 'folder' => 'Tenant',
            'route_file' => 'tenant.php',
        ],
        'reporting' => [
            'id' => 'reporting', 'is_tenant' => false, 'folder' => 'Reporting',
            'route_file' => 'web.php', 'connection_key' => 'reporting',
            'permission_prefix' => 'reporting_', 'permission_middleware' => 'reporting-permission',
        ],
    ];
}

it('genera en un contexto que declaró el proyecto, aunque no sea de fábrica', function () {
    ContextOption::check(ModuleMode::Multitenant, 'reporting', catalogoConContextoPropio(), false);
})->throwsNoExceptions();

it('rechaza un contexto que no está en el catálogo, y enseña los que sí están', function () {
    $fallo = null;

    try {
        ContextOption::check(ModuleMode::Multitenant, 'fantasma', catalogoConContextoPropio(), false);
    } catch (InvalidArgumentException $e) {
        $fallo = $e->getMessage();
    }

    expect($fallo)->not->toBeNull(
        'FALLA: un contexto que nadie declaró se aceptó. · FIX: ContextOption::check() debe exigir '
        . 'que la clave exista en contexts.json — generarla igualmente escribe el módulo en una '
        . 'carpeta que no existe en el catálogo, y nadie lo ve hasta el despliegue.'
    );

    expect(str_contains((string) $fallo, 'central, tenant, reporting'))->toBeTrue(
        'FALLA: el error no dice qué contextos hay. · FIX: lista el catálogo en el mensaje; sin eso, '
        . "quien se equivocó de nombre tiene que ir a buscar el archivo.\nDijo: " . (string) $fallo
    );

    expect(str_contains((string) $fallo, 'declara el tuyo'))->toBeTrue(
        'FALLA: el error no menciona que se pueda declarar un contexto propio, que es justo la salida '
        . 'de quien llegó ahí queriendo uno.'
    );
});

it('los dos contextos de fábrica siguen siendo los que el catálogo tiene que declarar', function () {
    // El contexto propio es ADICIONAL, nunca sustituto: el diagnóstico sigue exigiendo los dos.
    expect(ModuleMode::Multitenant->requiredContextKeys())->toBe(
        ['central', 'tenant'],
        'FALLA: cambió lo que el catálogo debe declarar. · FIX: un proyecto puede AÑADIR contextos, '
        . 'no quitar los dos que el modo necesita; si esto cambia, el doctor deja de reclamarlos.'
    );
});

it('en aplicación única no se pasa contexto, ni siquiera uno declarado', function () {
    $fallo = null;

    try {
        ContextOption::check(ModuleMode::SingleApp, 'reporting', catalogoConContextoPropio(), false);
    } catch (InvalidArgumentException $e) {
        $fallo = $e->getMessage();
    }

    expect($fallo)->not->toBeNull(
        'FALLA: la aplicación única aceptó un contexto. · FIX: sin eje de contexto no hay dónde '
        . 'ponerlo; la subfuncionalidad va directa bajo la capa.'
    );

    expect(str_contains((string) $fallo, 'no tiene contextos'))->toBeTrue(
        "FALLA: el error no explica que ese modo no tiene eje de contexto.\nDijo: " . (string) $fallo
    );
});
