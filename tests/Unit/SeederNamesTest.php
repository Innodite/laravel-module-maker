<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\SeederNames;

/**
 * Los nombres de las piezas de seeder, fijados aquí antes de que FEAT-003 escriba su contenido.
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
    ], 'R23 exige las seis por subfuncionalidad: reconstruir, publicar, permisos, la lista ordenada '
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

it('los maestros viven en su propia carpeta dentro del contexto', function () {
    expect(SeederNames::MASTER_FOLDER)->toBe(
        'Application',
        'Tienen carpeta propia porque son el punto de entrada único del módulo en su contexto: '
        . 'deploy-{contexto} los llama, y ellos hacen fan-out a las seis piezas de cada '
        . 'subfuncionalidad. No son «una subfuncionalidad más».'
    );
});
