<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\SeederNames;

/**
 * Los nombres de las piezas de seeder, fijados aquí antes de que existiera su contenido.
 *
 * Se fijan ahora porque **varios los van a necesitar**: el generador que escribe las seis piezas, el
 * maestro que las llama por su nombre de clase, el trait de migraciones y las pruebas de despliegue.
 * Cada uno recalculándolo por su cuenta es la receta exacta de B13, B15 y B17 — dos mitades que
 * dejan de coincidir y un archivo que no carga.
 */

it('una subfuncionalidad tiene seis piezas: tres seeders y tres traits', function () {
    $piezas = SeederNames::subFeaturePieces('Central', 'UserManagement', 'Role');

    expect($piezas)->toBe([
        'CentralUserManagementRoleStageSeeder',
        'CentralUserManagementRoleProductionSeeder',
        'CentralUserManagementRolePermissionsSeeder',
        'CentralUserManagementRoleMigrationsList',
        'CentralUserManagementRoleInlineAlters',
        'CentralUserManagementRoleData',
    ], 'Son seis por subfuncionalidad: reconstruir, publicar, permisos, la lista ordenada '
     . 'de migraciones, los deltas con guardia y los datos canónicos.');
});

it('en single-app las mismas seis piezas van sin prefijo', function () {
    $piezas = SeederNames::subFeaturePieces('', 'UserManagement', 'Role');

    expect($piezas[0])->toBe(
        'UserManagementRoleStageSeeder',
        'Sin eje de contexto no hay prefijo que anteponer — el mismo criterio que las demás capas.'
    );
});

it('los maestros del módulo son tres, no seis', function () {
    $maestros = SeederNames::masterPieces('Central', 'UserManagement');

    expect($maestros)->toBe([
        'CentralUserManagementApplicationStageSeeder',
        'CentralUserManagementApplicationProductionSeeder',
        'CentralUserManagementApplicationPermissionsSeeder',
    ], 'Son tres porque no tienen esquema ni datos propios: solo ordenan y propagan. Los tres traits '
     . 'de una subfuncionalidad no les corresponden.');
});

it('la cadena no se cruza: cada maestro llama a su misma pieza', function () {
    expect(SeederNames::masterFor('Central', 'UserManagement', 'Production'))->toBe(
        'CentralUserManagementApplicationProductionSeeder',
        'El maestro de producción llama a los Production de sus subfuncionalidades. Cruzarlo haría '
        . 'que un despliegue de producción invocara el seeder que reconstruye desde cero — que es la '
        . 'diferencia entre publicar y borrar los datos del cliente.'
    );
});

it('los maestros viven en su propia carpeta, al nivel del módulo', function () {
    expect(SeederNames::MASTER_FOLDER)->toBe(
        'Application',
        'Tienen carpeta propia porque son el punto de entrada único del módulo en su contexto: '
        . 'deploy-{contexto} los llama, y ellos hacen fan-out a las seis piezas de cada '
        . 'subfuncionalidad. No son «una subfuncionalidad más», y por eso no bajan a ninguna.'
    );
});

it('la carpeta declarada resuelve también al maestro que la despliega', function () {
    // Un escalón por encima de classFromPath(): el seeder de despliegue del proyecto tampoco nombra a
    // sus hijos. La subfuncionalidad de la ruta se descarta porque el maestro es del MÓDULO, no de
    // ella — y eso es justo lo que permite deduplicar dos rutas del mismo módulo sin razonar sobre
    // módulos.
    expect(SeederNames::masterFromPath('UserManagement/Central/Role', 'Stage'))->toBe(
        'Modules\UserManagement\Database\Seeders\Application\Central\CentralUserManagementApplicationStageSeeder'
    );

    expect(SeederNames::masterFromPath('Invoice/Tenant/Invoice', 'Permissions'))->toBe(
        'Modules\Invoice\Database\Seeders\Application\Tenant\TenantInvoiceApplicationPermissionsSeeder'
    );

    // Sin eje de contexto: dos segmentos, y el nombre sin prefijo.
    expect(SeederNames::masterFromPath('Invoice/Invoice', 'Production'))->toBe(
        'Modules\Invoice\Database\Seeders\Application\InvoiceApplicationProductionSeeder'
    );
});

it('dos subfuncionalidades del mismo módulo y contexto dan el mismo maestro', function () {
    expect(SeederNames::masterFromPath('Invoice/Central/Invoice', 'Stage'))
        ->toBe(SeederNames::masterFromPath('Invoice/Central/Payment', 'Stage'));
});

it('el seeder de despliegue del proyecto se llama igual lo pida quien lo pida', function () {
    // Su nombre lo necesitan el instalador que lo escribe, el comando que lo invoca y el
    // `DatabaseSeeder` que lo engancha. Tres cálculos del mismo nombre son tres sitios donde el día
    // que cambie solo cambiarán dos.
    expect(SeederNames::projectDeploySeeder())->toBe('InnoditeDeploySeeder')
        ->and(SeederNames::projectDeploySeeder('central'))->toBe('InnoditeCentralDeploySeeder')
        ->and(SeederNames::projectDeploySeeder('tenant'))->toBe('InnoditeTenantDeploySeeder')
        ->and(SeederNames::projectDeploySeeder('tenant_shared'))->toBe('InnoditeTenantSharedDeploySeeder');
});
