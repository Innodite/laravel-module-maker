<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Exceptions\ModeNotConfiguredException;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El modo decide la FORMA de todo lo que se genera. Estas pruebas fijan esas decisiones
 * una por una, porque cada una se propaga a cada archivo de cada módulo de cada proyecto:
 * si el eje de contexto aparece donde no toca, o un tenant declara una conexión que no
 * debería, el error no se ve al generar — se ve meses después, en producción.
 */

it('en aplicación única no existe el eje de contexto ni el prefijo de clase', function () {
    $mode = ModuleMode::SingleApp;

    expect($mode->hasContextAxis())->toBeFalse(
        'Una aplicación única no tiene Central/Tenant que distinguir. '
        . 'Revisa ModuleMode::hasContextAxis(): debe devolver false para SingleApp.'
    );
    expect($mode->usesClassPrefix())->toBeFalse(
        'Sin eje de contexto no hay nada que desambiguar, así que CentralRoleController sobra. '
        . 'Revisa ModuleMode::usesClassPrefix().'
    );
    expect($mode->requiredContextKeys())->toBe(
        [],
        'Una aplicación única no tiene tenants que declarar. Exigirle claves de contexto la '
        . 'obliga a inventar un contexto falso para pasar un diagnóstico que no le aplica.'
    );
});

it('el middleware de permiso es el que corresponde al modo', function () {
    expect(ModuleMode::SingleApp->permissionMiddleware())->toBe(
        'permission',
        'En aplicación única el alias es "permission" a secas. Revisa ModuleMode::permissionMiddleware().'
    );
    expect(ModuleMode::Multitenant->permissionMiddleware('central'))->toBe(
        'central-permission',
        'La app central de un multitenant protege con "central-permission".'
    );
    expect(ModuleMode::Multitenant->permissionMiddleware('tenant'))->toBe(
        'tenant-permission',
        'Todo lo que no es central protege con "tenant-permission".'
    );
});

it('el prefijo del permiso sale de la misma lógica que el nombre del archivo', function () {
    expect(ModuleMode::SingleApp->permissionPrefix())->toBe(
        '',
        'Single-app: invoices_index, sin prefijo. Revisa ModuleMode::permissionPrefix().'
    );
    expect(ModuleMode::Multitenant->permissionPrefix('tenant'))->toBe(
        'tenant_',
        'El prefijo es el CONTEXTO — tenant_roles_index.'
    );
    expect(ModuleMode::Multitenant->permissionPrefix('central'))->toBe(
        'central_',
        'El contexto central siempre lleva prefijo central_.'
    );
});

it('el permiso del inquilino NUNCA lleva el nombre de un cliente', function () {
    // Un permiso `energy_spain_settlements_index` hay que sembrarlo por cliente y renombrarlo el día
    // que el cliente se renombre. `tenant_settlements_index` se siembra una vez, dentro de la base de
    // cada inquilino, donde ya es inequívoco: el aislamiento lo da la base, no el nombre.
    expect(ModuleMode::Multitenant->permissionPrefix('tenant'))->toBe('tenant_');

    $firma = new ReflectionMethod(ModuleMode::class, 'permissionPrefix');

    expect($firma->getNumberOfParameters())->toBe(
        1,
        'permissionPrefix() recibe SOLO el contexto. Un segundo parámetro con el id del inquilino '
        . 'es la puerta por la que vuelve el permiso con nombre de cliente.'
    );
});

it('el modelo del inquilino no declara conexión, y el central sí', function () {
    expect(ModuleMode::Multitenant->declaresModelConnection('central'))->toBeTrue(
        'La aplicación central tiene su propia base y su modelo la nombra.'
    );
    expect(ModuleMode::Multitenant->declaresModelConnection('tenant'))->toBeFalse(
        'El paquete de tenencia conmuta la conexión al identificar al inquilino, y el aislamiento lo '
        . 'garantiza la ruta. Nombrarla aquí ata el modelo a UN cliente y rompe lo que protege.'
    );
    expect(ModuleMode::SingleApp->declaresModelConnection())->toBeFalse(
        'Una sola base: no hay nada que conmutar ni que nombrar.'
    );
});

it('ningún contexto nombra a un inquilino', function () {
    // El modo `multitenant-per-tenant` existía para eso y se retiró: nombrar a un cliente multiplica
    // lógica idéntica por cliente, y declararle conexión ata el modelo a su base.
    expect(array_column(ModuleMode::cases(), 'value'))->toBe(
        ['single-app', 'multitenant'],
        'Dos modos, y solo dos.'
    );
    expect(ModuleMode::Multitenant->supportedContextKeys())->toBe(
        ['central', 'tenant'],
        'Dos contextos de fábrica. Un contexto propio lo declara el proyecto en SU catálogo.'
    );
});

it('lo que el catálogo debe declarar es lo mismo que el modo acepta', function () {
    // Eran dos preguntas distintas mientras un modo exigía `tenant_shared` y el otro `tenant`. Con un
    // solo modo multiinquilino y un solo contexto de inquilino, la respuesta es la misma lista — y
    // mantener dos formas de calcularla es como se separan las mitades de este paquete.
    expect(ModuleMode::Multitenant->requiredContextKeys())->toBe(
        ['central', 'tenant'],
        'La aplicación central y el inquilino. Nada más.'
    );
    expect(ModuleMode::Multitenant->requiredContextKeys())->toBe(
        ModuleMode::Multitenant->supportedContextKeys(),
        'Un solo cálculo para las dos preguntas.'
    );
    expect(ModuleMode::SingleApp->requiredContextKeys())->toBe(
        [],
        'Una aplicación única no tiene inquilinos que declarar: exigirle una clave la obligaría a '
        . 'inventarse un contexto para pasar un diagnóstico que no le aplica.'
    );
});

it('lee el modo de la configuración', function () {
    config()->set('make-module.mode', 'single-app');

    expect(ModuleMode::current())->toBe(
        ModuleMode::SingleApp,
        'ModuleMode::current() debe leer make-module.mode de la configuración.'
    );
});

it('sin modo elegido se niega a generar, y el error dice cómo elegirlo', function () {
    config()->set('make-module.mode', null);

    $message = null;

    try {
        ModuleMode::current();
    } catch (ModeNotConfiguredException $e) {
        $message = $e->getMessage();
    }

    expect($message)->not->toBeNull(
        'Sin modo elegido, generar debe fallar. Un valor por defecto silencioso produce una '
        . 'estructura equivocada multiplicada por cada módulo de cada proyecto.'
    );

    // el mensaje trae la instrucción, no solo el diagnóstico.
    expect($message)->toContain('innodite:module-setup');
    expect($message)->toContain('single-app');
    expect($message)->toContain('multitenant');
});

it('un modo RETIRADO se rechaza diciendo qué escribir, no como si fuera una errata', function () {
    // La configuración dice algo que era legítimo la semana pasada. «El modo no existe» se leería
    // como un typo, y traducirlo en silencio a `multitenant` sería peor: ese proyecto eligió
    // inquilinos nombrados con conexión propia, y las dos cosas ya no existen.
    foreach (['multitenant-shared', 'multitenant-per-tenant'] as $retirado) {
        config()->set('make-module.mode', $retirado);

        $message = null;

        try {
            ModuleMode::current();
        } catch (ModeNotConfiguredException $e) {
            $message = $e->getMessage();
        }

        expect($message)->not->toBeNull("El modo retirado '{$retirado}' debe rechazarse.");
        // El mensaje nombra el modo que el proyecto declara, dice exactamente qué escribir, y da la
        // salida para quien sí necesita un contexto propio.
        expect($message)->toContain($retirado);
        expect($message)->toContain("escribe 'multitenant'");
        expect($message)->toContain('contexts.json');
    }
});

it('un modo desconocido falla nombrando el valor mal escrito', function () {
    config()->set('make-module.mode', 'lo-que-sea');

    $message = null;

    try {
        ModuleMode::current();
    } catch (ModeNotConfiguredException $e) {
        $message = $e->getMessage();
    }

    expect($message)->not->toBeNull('Un modo inválido no puede resolverse inventando otro.');
    expect($message)->toContain("el modo 'lo-que-sea' no existe");

    // nombrar el valor mal escrito no basta — el mensaje trae también qué escribir en su lugar.
    expect(str_contains($message, 'FIX:'))->toBeTrue(
        "FALLA: el error nombra el valor y no dice cómo corregirlo.\nDice:\n{$message}"
    );
});

// ── El aviso de primera instalación ────────────────────────────────────────────────────────────

it('sin modo elegido, el paquete no está configurado', function () {
    config()->set('make-module.mode', null);

    expect(ModuleMode::isConfigured())->toBeFalse();
});

it('con el modo elegido sí lo está, y por eso el aviso de instalación se calla', function () {
    // Medido en un proyecto real: el aviso «Primera instalación detectada» seguía saliendo en cada
    // comando DESPUÉS de instalar con éxito, porque miraba si existía una carpeta en vez de mirar
    // si el paquete estaba configurado. La carpeta puede existir sin instalar nada —un
    // `vendor:publish` suelto la crea— y puede faltar con la instalación hecha.
    config()->set('make-module.mode', ModuleMode::SingleApp->value);

    expect(ModuleMode::isConfigured())->toBeTrue();
});
