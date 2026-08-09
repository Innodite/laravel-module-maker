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
    expect(ModuleMode::MultitenantShared->permissionMiddleware('central'))->toBe(
        'central-permission',
        'La app central de un multitenant protege con "central-permission".'
    );
    expect(ModuleMode::MultitenantShared->permissionMiddleware('tenant_shared'))->toBe(
        'tenant-permission',
        'Todo lo que no es central protege con "tenant-permission".'
    );
});

it('el prefijo del permiso sale de la misma lógica que el nombre del archivo', function () {
    expect(ModuleMode::SingleApp->permissionPrefix())->toBe(
        '',
        'Single-app: invoices_index, sin prefijo. Revisa ModuleMode::permissionPrefix().'
    );
    expect(ModuleMode::MultitenantShared->permissionPrefix('tenant_shared'))->toBe(
        'tenant_',
        'Tenants iguales: el prefijo es el CONTEXTO — tenant_roles_index.'
    );
    expect(ModuleMode::MultitenantShared->permissionPrefix('central'))->toBe(
        'central_',
        'El contexto central siempre lleva prefijo central_.'
    );
    expect(ModuleMode::MultitenantPerTenant->permissionPrefix('tenant', 'energy-spain'))->toBe(
        'energy_spain_',
        'Un tenant con lógica propia se nombra: energy_spain_settlements_index. '
        . 'Y el id llega con guiones, que hay que convertir a snake_case.'
    );
});

it('un tenant solo declara conexión propia cuando tiene lógica propia', function () {
    expect(ModuleMode::MultitenantShared->requiresTenantConnectionKey())->toBeFalse(
        'Si todos los tenants hacen lo mismo, el paquete de tenancy conmuta la conexión al '
        . 'inicializar el contexto y el aislamiento lo garantiza la ruta. Exigir connection_key '
        . 'ahí ata el modelo a un solo tenant.'
    );
    expect(ModuleMode::MultitenantPerTenant->requiresTenantConnectionKey())->toBeTrue(
        'Un tenant con lógica propia sí declara la suya.'
    );
});

it('un tenant solo se nombra si tiene lógica propia', function () {
    expect(ModuleMode::MultitenantShared->namesTenant())->toBeFalse(
        'Nombrar un tenant que hace lo mismo que los demás produce código duplicado con etiqueta.'
    );
    expect(ModuleMode::MultitenantPerTenant->namesTenant())->toBeTrue();
});

it('las claves de contexto exigidas dependen del modo, no de una lista fija', function () {
    expect(ModuleMode::MultitenantShared->requiredContextKeys())->toBe(
        ['central', 'shared', 'tenant_shared'],
        'Con tenants iguales no hay tenants NOMBRADOS que declarar: la clave "tenant" sobra. '
        . 'Revisa el match() de ModuleMode::requiredContextKeys().'
    );
    expect(ModuleMode::MultitenantPerTenant->requiredContextKeys())->toBe(
        ['central', 'shared', 'tenant_shared', 'tenant'],
        'Con lógica por tenant, cada tenant nombrado se declara en contexts.json.'
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

    // R30: el mensaje trae la instrucción, no solo el diagnóstico.
    expect($message)->toContain('innodite:module-setup');
    expect($message)->toContain('single-app');
    expect($message)->toContain('multitenant-shared');
    expect($message)->toContain('multitenant-per-tenant');
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

    // R30: nombrar el valor mal escrito no basta — el mensaje trae también qué escribir en su lugar.
    expect(str_contains($message, 'FIX:'))->toBeTrue(
        "FALLA: el error nombra el valor y no dice cómo corregirlo.\nDice:\n{$message}"
    );
});
