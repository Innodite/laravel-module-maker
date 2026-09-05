<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * Levantar **un módulo suelto**, en stage o en producción.
 *
 * **La unidad es el módulo y no la subfuncionalidad**, porque el maestro `Application` es lo que la
 * norma define como punto de entrada único: reparte hacia sus subfuncionalidades en el orden
 * declarado. Sirve para desarrollo, para reparar un módulo concreto y para el alta de un tenant.
 *
 * Y es una opción de `innodite:deploy`, no un comando aparte: lo que cambia al acotar por módulo es
 * **qué clases se resuelven**, no cómo se ejecutan. Dos comandos habrían sido dos sitios donde
 * arreglar el mismo fallo.
 */
beforeEach(function (): void {
    config()->set('make-module.mode', ModuleMode::SingleApp->value);
    config()->set('make-module.deploy', [
        'Invoice/Invoice',
        'Invoice/Payment',
        'Catalog/Product',
    ]);
});

/** Lanza el despliegue en ensayo y devuelve lo que dice que haría. */
function ensayoDeDespliegue(array $opciones): string
{
    Artisan::call('innodite:deploy', array_merge(
        ['environment' => 'stage', '--dry-run' => true, '--no-interaction' => true],
        $opciones
    ));

    return Artisan::output();
}

it('despliega solo el módulo pedido, con su pieza y sus permisos', function () {
    $salida = ensayoDeDespliegue(['--module' => 'Invoice']);

    expect($salida)->toContain('InvoiceApplicationStageSeeder')
        ->and($salida)->toContain('InvoiceApplicationPermissionsSeeder');

    // Y nada del otro módulo: acotar que no acota es peor que no acotar, porque se confía en ello.
    expect($salida)->not->toContain('CatalogApplication');
});

it('dos subfuncionalidades del mismo módulo no repiten su maestro', function () {
    // `Invoice/Invoice` e `Invoice/Payment` comparten maestro: es del módulo, no de cada una.
    // Ejecutarlo dos veces no rompería nada —son idempotentes—, pero sembraría dos veces lo mismo y
    // el informe del despliegue contaría el doble de pasos de los que hubo.
    $salida = ensayoDeDespliegue(['--module' => 'Invoice']);

    expect(substr_count($salida, 'InvoiceApplicationStageSeeder'))->toBe(1);
});

it('production despliega la pieza de producción, no la de stage', function () {
    $salida = ensayoDeDespliegue(['environment' => 'production', '--module' => 'Invoice']);

    expect($salida)->toContain('InvoiceApplicationProductionSeeder')
        ->and($salida)->not->toContain('InvoiceApplicationStageSeeder');
});

it('si el módulo no está en el orden de despliegue, lo dice y no despliega nada', function () {
    // El nombre mal escrito es el caso frecuente, y el silencio sería lo peor: un despliegue que
    // termina en verde sin haber tocado nada se da por hecho.
    $salida = ensayoDeDespliegue(['--module' => 'Fantasma']);

    expect($salida)->toContain('FALLA:')
        ->and($salida)->toContain('Fantasma')
        ->and($salida)->toContain('config/make-module.php');
});
