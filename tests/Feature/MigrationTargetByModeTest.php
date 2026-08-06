<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Services\MigrationTargetService;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El modo de tenants iguales, por fin capaz de migrar (R7).
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
    // `Tenant/Shared` es el contexto que fallaba: en el contexts.json de ejemplo NO declara
    // `connection_key` ni `tenancy_strategy`, porque en ese modo no le corresponde declararlos.
    $this->withMode(ModuleMode::MultitenantShared);

    $conexion = (new MigrationTargetService())->resolveExecutionConnection(
        'Tenant/Shared',
        dryRun: true
    );

    expect($conexion)->toBe(
        (string) config('database.default'),
        'Con la conexión conmutada por la tenancy, la ejecución va sobre la activa. Antes de esta '
        . 'corrección aquí saltaba «no tiene un connection_key definido» y el modo entero se '
        . 'quedaba sin poder desplegar.'
    );
});

it('el mismo contexto, en el modo de lógica propia, sí exige la conexión', function () {
    // La corrección no relaja la validación: la condiciona al modo. Con lógica propia por tenant,
    // la conexión es parte de su identidad y no declararla es un error de configuración de verdad.
    $this->withMode(ModuleMode::MultitenantPerTenant);

    expect(fn () => (new MigrationTargetService())->resolveExecutionConnection('Tenant/Shared', true))
        ->toThrow(InvalidArgumentException::class);
});

it('la app central pasa por la validación de siempre', function () {
    // La excepción es solo para el tenant del modo compartido. La central declara su conexión
    // siempre (R7): si aquí se relajara, el despliegue central acabaría en la base equivocada.
    $this->withMode(ModuleMode::MultitenantShared);

    $conexion = (new MigrationTargetService())->resolveExecutionConnection('central', true);

    expect($conexion)->toBe('central');
});

it('el modo con lógica propia por tenant sigue exigiendo la conexión', function () {
    expect(ModuleMode::MultitenantPerTenant->requiresTenantConnectionKey())->toBeTrue(
        'Un tenant con lógica propia sí declara la suya: ahí la conexión es parte de su identidad.'
    );
    expect(ModuleMode::MultitenantShared->requiresTenantConnectionKey())->toBeFalse();
    expect(ModuleMode::SingleApp->requiresTenantConnectionKey())->toBeFalse(
        'Y en una aplicación única no hay tenant al que exigirle nada.'
    );
});
