<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Innodite\LaravelModuleMaker\Support\PackageVersion;

/**
 * Los diez comandos como **un** conjunto, no como diez scripts con el mismo prefijo.
 *
 * Lo que se fija aquí no es estilo. Una opción que se llama `--yes` en un comando y `--force` en los
 * otros dos obliga a mirar la ayuda antes de cada uso, y quien no la mira escribe la que recuerda y
 * recibe «opción desconocida» en mitad de un despliegue. Una cabecera que falta es una salida que no
 * dice qué versión la produjo. Y un comando que no devuelve código de salida no se puede encadenar.
 */

/** Los comandos del paquete, por nombre. */
function comandosDelPaquete(): array
{
    return array_filter(
        Artisan::all(),
        fn ($nombre) => str_starts_with($nombre, 'innodite:'),
        ARRAY_FILTER_USE_KEY
    );
}

it('los diez se presentan igual, y con la versión que corre', function () {
    // Se ejecutan de verdad —con parámetros que fallan pronto, o en ensayo— porque una cabecera
    // comprobada leyendo el código se cumple en el archivo y se incumple en pantalla.
    $invocaciones = [
        'innodite:make-module'      => ['name' => 'Invoice', '--context' => 'central', '--dry-run' => true],
        'innodite:add-entity'       => ['module' => 'Fantasma', 'entity' => 'Cosa', '--context' => 'central'],
        'innodite:deploy'           => ['environment' => 'preprod'],
        'innodite:doctor'           => [],
        'innodite:crear-bd-test'    => ['--connection' => 'fantasma'],
        'innodite:migrate-one'      => ['coordinate' => 'Fantasma:Central/no-existe.php'],
        'innodite:migrate-plan'     => ['--context' => 'central'],
        'innodite:publish-frontend' => ['--dry-run' => true],
        'innodite:module-setup'     => ['--dry-run' => true],
        'innodite:test'             => ['module' => 'Fantasma', 'subfeature' => 'Cosa'],
    ];

    expect(array_keys($invocaciones))
        ->toEqualCanonicalizing(array_keys(comandosDelPaquete()), 'FALLA: la lista de comandos cambió.');

    foreach ($invocaciones as $nombre => $parametros) {
        Artisan::call($nombre, array_merge($parametros, ['--no-interaction' => true]));
        $salida = Artisan::output();

        expect(str_contains($salida, 'Innodite ModuleMaker —'))->toBeTrue(
            "FALLA: {$nombre} no se presenta. · FIX: usa el trait PrintsHeader y llama a "
            . "cabecera('<qué hace esta corrida>') al entrar.\n{$salida}"
        );

        expect(str_contains($salida, PackageVersion::current()))->toBeTrue(
            "FALLA: la cabecera de {$nombre} no dice la versión instalada.\n{$salida}"
        );

        expect(str_contains($salida, 'vv'))->toBeFalse(
            "FALLA: la cabecera de {$nombre} dice la v dos veces — Composer ya devuelve la etiqueta "
            . "con su v.\n{$salida}"
        );
    }
});

it('una sola forma de decir «no me preguntes»', function () {
    // Eran dos: `--force` en deploy y publish-frontend, `--yes` en migrate-one. Dos nombres para la
    // misma orden significan que hay que recordar cuál va con cuál, y quien no lo recuerda escribe la
    // que usó ayer.
    foreach (comandosDelPaquete() as $nombre => $comando) {
        expect($comando->getDefinition()->hasOption('yes'))->toBeFalse(
            "FALLA: {$nombre} usa --yes. · FIX: la forma del conjunto es --force."
        );
    }
});

it('el ensayo se describe igual en los ocho que lo tienen', function () {
    foreach (comandosDelPaquete() as $nombre => $comando) {
        $definicion = $comando->getDefinition();

        if (! $definicion->hasOption('dry-run')) {
            continue;
        }

        expect(str_starts_with($definicion->getOption('dry-run')->getDescription(), 'Ensayo: '))
            ->toBeTrue(
                "FALLA: {$nombre} describe su --dry-run de otra manera. · FIX: «Ensayo: enseña "
                . "<qué haría>, sin <qué toca>»."
            );
    }
});

it('los argumentos se nombran en inglés, como el resto del código', function () {
    // R51. Y no es solo la norma: `entorno` era el único en español entre nueve comandos, así que
    // Artisan::call(['entorno' => …]) fallaba para quien hubiera leído cualquiera de los otros ocho.
    $conocidos = ['module', 'entity', 'name', 'subfeature', 'environment', 'coordinate'];

    foreach (comandosDelPaquete() as $nombre => $comando) {
        foreach (array_keys($comando->getDefinition()->getArguments()) as $argumento) {
            if ($argumento === 'command') {
                continue;
            }

            expect(in_array($argumento, $conocidos, true))->toBeTrue(
                "FALLA: {$nombre} declara el argumento '{$argumento}'. · FIX: nómbralo en inglés y "
                . 'añádelo a la lista de esta prueba, para que el conjunto siga siendo uno.'
            );
        }
    }
});

it('los diez devuelven código de salida', function () {
    // `innodite:module-setup` devolvía void, y en consola eso es «éxito» siempre: encadenaba lo
    // siguiente aunque la instalación se hubiera detenido por falta de modo.
    foreach (comandosDelPaquete() as $nombre => $comando) {
        $tipo = (new ReflectionMethod($comando, 'handle'))->getReturnType();

        expect($tipo?->getName())->toBe(
            'int',
            "FALLA: handle() de {$nombre} no devuelve int. · FIX: devuelve self::SUCCESS o "
            . 'self::FAILURE — un comando que no puede fallar no se puede encadenar en un script.'
        );
    }
});

it('el instalador que no elige modo termina en fallo, no en éxito', function () {
    $codigo = Artisan::call('innodite:module-setup', ['--no-interaction' => true]);

    expect($codigo)->toBe(1, 'FALLA: sin modo elegido la instalación se detiene y aun así decía que sí.');
});
