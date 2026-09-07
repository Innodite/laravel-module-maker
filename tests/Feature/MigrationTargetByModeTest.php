<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Services\MigrationTargetService;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El modo de tenants iguales, por fin capaz de migrar.
 *
 * El hallazgo salió construyendo la fase 1 y quedó anotado para esta: la validación exigía
 * `connection_key` a **todos** los contextos de tenant. Pero en `multitenant-shared` ningún tenant
 * declara conexión —la conmuta la tenancy al identificar al inquilino, y nombrarla ataría el módulo
 * a uno solo—, así que ese modo entero **no podía desplegar**: la funcionalidad existía y no había
 * manera de llevarla a la base de datos.
 *
 * No era una validación de más: era una validación que **preguntaba lo que no correspondía**.
 */

it('en tenants iguales no se exige connection_key: migra sobre la conexión activa', function () {
    // `Tenant` es el contexto que fallaba: en el contexts.json NO declara
    // `connection_key` ni `tenancy_strategy`, porque en ese modo no le corresponde declararlos.
    $this->withMode(ModuleMode::Multitenant);

    $conexion = (new MigrationTargetService())->resolveExecutionConnection(
        'Tenant',
        dryRun: true
    );

    expect($conexion)->toBe(
        (string) config('database.default'),
        'Con la conexión conmutada por la tenancy, la ejecución va sobre la activa. Antes de esta '
        . 'corrección aquí saltaba «no tiene un connection_key definido» y el modo entero se '
        . 'quedaba sin poder desplegar.'
    );
});

it('un contexto propio del proyecto sí tiene que declarar su conexión', function () {
    // La excepción es para el eje del inquilino, no para cualquier contexto: uno que el proyecto
    // declare por su cuenta —una segunda central, un almacén aparte— no lo conmuta nadie, así que
    // sin `connection_key` la migración iría a la base por defecto.
    $this->withMode(ModuleMode::Multitenant);

    expect(fn () => (new MigrationTargetService())->resolveExecutionConnection('Reporting', true))
        ->toThrow(InvalidArgumentException::class);
});

it('la app central pasa por la validación de siempre', function () {
    // La excepción es solo para el tenant del modo compartido. La central declara su conexión
    // siempre: si aquí se relajara, el despliegue central acabaría en la base equivocada.
    $this->withMode(ModuleMode::Multitenant);

    $conexion = (new MigrationTargetService())->resolveExecutionConnection('central', true);

    expect($conexion)->toBe('central');
});

it('el inquilino no declara conexión en ningún modo', function () {
    expect(ModuleMode::Multitenant->declaresModelConnection('tenant'))->toBeFalse(
        'La conmuta el paquete de tenencia al identificar la ruta. Nombrarla ata el modelo a UN '
        . 'cliente, que es lo contrario de lo que el eje protege.'
    );
    expect(ModuleMode::Multitenant->declaresModelConnection('central'))->toBeTrue(
        'La central sí: tiene su propia base y no la conmuta nadie.'
    );
    expect(ModuleMode::SingleApp->declaresModelConnection())->toBeFalse(
        'Y en una aplicación única no hay nada que conmutar ni que nombrar.'
    );
});
