<?php

declare(strict_types=1);

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

it('un modo desconocido no rompe la generación: cae al modo con el que nació el paquete', function () {
    config()->set('make-module.mode', 'lo-que-sea');

    expect(ModuleMode::current())->toBe(
        ModuleMode::MultitenantPerTenant,
        'Un valor inválido en configuración no debe reventar un comando de generación. '
        . 'Revisa el tryFrom() de ModuleMode::current().'
    );
});
