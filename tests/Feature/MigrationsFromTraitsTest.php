<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Services\MigrationPlanResolver;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * La fuente del orden de despliegue, ya sin manifiesto JSON (P2).
 *
 * El resolutor lee los traits `MigrationsList` que viajan dentro de cada módulo. Lo hace por texto y
 * no instanciándolos, a propósito: el paquete corre dentro de la aplicación de otro, donde el
 * autoload de `Modules\…` puede no estar registrado todavía — justo en el primer despliegue, que es
 * cuando más falta hace.
 */

it('encuentra las migraciones de los módulos generados, sin manifiesto de por medio', function () {
    $this->generateModule('Invoice', ModuleMode::SingleApp);

    $migraciones = (new MigrationPlanResolver())->migrationsFromTraits();

    expect($migraciones)->not->toBeEmpty(
        'Con un módulo generado y su trait escrito, el resolutor tiene que encontrar su migración.'
    );

    expect($migraciones[0])->toContain('Modules/Invoice/Database/Migrations/Invoice/');
    expect($migraciones[0])->toEndWith('_create_invoices_table_final.php');
});

it('reúne las migraciones de varios módulos, cada una en el orden de su trait', function () {
    $this->generateModule('Invoice', ModuleMode::SingleApp);
    $this->generateModule('Payment', ModuleMode::SingleApp);

    $migraciones = (new MigrationPlanResolver())->migrationsFromTraits();

    expect($migraciones)->toHaveCount(2);

    $modulos = array_map(
        static fn (string $ruta): string => explode('/', $ruta)[1],
        $migraciones,
    );

    sort($modulos);

    expect($modulos)->toBe(['Invoice', 'Payment']);
});

it('el filtro por contexto deja fuera lo que no es de ese contexto', function () {
    $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    $resolver = new MigrationPlanResolver();

    expect($resolver->migrationsFromTraits('Central'))->not->toBeEmpty(
        'El módulo se generó en el contexto central: su trait debe entrar con ese filtro.'
    );

    expect($resolver->migrationsFromTraits('Tenant'))->toBe(
        [],
        'Y no debe colarse en el despliegue del tenant: son bases de datos distintas.'
    );
});

it('sin módulos generados devuelve una lista vacía, no un error', function () {
    // Un proyecto recién instalado no tiene módulos. Que eso reviente sería la peor primera
    // impresión posible del paquete.
    expect((new MigrationPlanResolver())->migrationsFromTraits())->toBe([]);
});
