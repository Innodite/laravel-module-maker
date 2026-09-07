<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * La ayuda ofrece los contextos que el paquete admite — y ninguno más.
 *
 * ⭐ **Esto no es una prueba de redacción.** Cuando la v5 redujo los contextos de cuatro a dos, los
 * cuatro comandos con `--context` siguieron anunciando los cuatro durante toda una versión mayor. El
 * daño no es cosmético: quien pedía ayuda leía `tenant_shared`, lo escribía, y el comando lo
 * rechazaba. Había elegido mal **porque la ayuda se lo ofreció**, que es la peor forma de equivocarse
 * — la que no se puede achacar a no haber leído.
 *
 * Y no lo cazó nadie porque no había nada que mirase la ayuda: las pruebas comprobaban lo que los
 * comandos HACEN, y una promesa que nadie cumple no rompe ninguna aserción. La red va aquí, en el
 * único sitio donde las dos mitades —lo ofrecido y lo admitido— se pueden comparar.
 *
 * ⛔ La lista buena no se escribe a mano: sale de {@see ModuleMode::supportedContextKeys()}, que es
 * quien decide de verdad. Una lista copiada aquí envejecería por su cuenta, que es exactamente el
 * defecto que esta prueba existe para impedir.
 */

/** Los comandos del paquete, por nombre. */
function comandosConAyuda(): array
{
    return array_filter(
        Artisan::all(),
        fn ($nombre) => str_starts_with($nombre, 'innodite:'),
        ARRAY_FILTER_USE_KEY
    );
}

/**
 * Los contextos que una descripción de `--context` enumera.
 *
 * La convención de la ayuda es `… en multitenant: central | tenant. <resto>`, así que se lee justo
 * ese tramo: desde los dos puntos hasta el primer punto y aparte, separando por `|`. Un formato
 * distinto devuelve lista vacía y lo denuncia la última prueba del archivo, que exige que al menos
 * los comandos que sabemos que tienen la opción hayan sido leídos de verdad.
 *
 * @return array<int, string>
 */
function contextosOfrecidos(string $descripcion): array
{
    if (preg_match('/multitenant:\s*([^.]+)/u', $descripcion, $coincidencia) !== 1) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn (string $trozo): string => trim($trozo),
        explode('|', $coincidencia[1])
    )));
}

/**
 * Claves y modos que el paquete retiró, con el motivo de que estén aquí.
 *
 * Se listan a propósito, y es la única lista escrita a mano del archivo: un nombre retirado no
 * aparece en ninguna estructura viva —para eso se retiró—, así que no hay de dónde deducirlo. Que
 * reaparezca en un texto de ayuda significa que alguien lo copió de un archivo antiguo.
 */
const NOMBRES_RETIRADOS = [
    'tenant_shared'          => 'contexto retirado en la v5: el eje del inquilino es uno solo, `tenant`',
    'multitenant-shared'     => 'modo retirado en la v5: hay un único modo multiinquilino, `multitenant`',
    'multitenant-per-tenant' => 'modo retirado en la v5: nombrar al cliente multiplicaba lógica idéntica',
];

it('todo contexto que la ayuda ofrece es uno que el paquete admite', function () {
    $vivos  = ModuleMode::Multitenant->supportedContextKeys();
    $leidos = 0;

    foreach (comandosConAyuda() as $nombre => $comando) {
        $definicion = $comando->getDefinition();

        if (! $definicion->hasOption('context')) {
            continue;
        }

        $ofrecidos = contextosOfrecidos($definicion->getOption('context')->getDescription());

        if ($ofrecidos === []) {
            continue;
        }

        $leidos++;

        foreach ($ofrecidos as $contexto) {
            // in_array() y no toContain(): el segundo argumento de toContain es otro valor a
            // buscar, no el mensaje de fallo, así que el mensaje acababa dentro de la aserción.
            expect(in_array($contexto, $vivos, true))->toBeTrue(
                "FALLA: la ayuda de {$nombre} ofrece el contexto '{$contexto}', que el modo "
                . 'multitenant no admite: quien lo escriba recibirá un rechazo.'
                . "\n  · FIX: deja en la descripción de --context solo "
                . implode(' | ', $vivos)
                . ', que es lo que devuelve ModuleMode::supportedContextKeys().'
            );
        }
    }

    expect($leidos)->toBeGreaterThan(
        0,
        'FALLA: no se pudo leer la lista de contextos de ninguna ayuda, así que esta prueba no está '
        . "comprobando nada.\n  · FIX: la descripción de --context sigue el formato "
        . '«…, en multitenant: central | tenant. …»; si cambió, ajusta contextosOfrecidos().'
    );
});

it('ningún texto de ayuda nombra un contexto o un modo que se retiró', function () {
    foreach (comandosConAyuda() as $nombre => $comando) {
        $textos = [$comando->getDescription()];

        foreach ($comando->getDefinition()->getOptions() as $opcion) {
            $textos[] = $opcion->getDescription();
        }

        foreach ($comando->getDefinition()->getArguments() as $argumento) {
            $textos[] = $argumento->getDescription();
        }

        $ayuda = implode("\n", $textos);

        foreach (NOMBRES_RETIRADOS as $retirado => $motivo) {
            expect(str_contains($ayuda, $retirado))->toBeFalse(
                "FALLA: la ayuda de {$nombre} todavía nombra '{$retirado}' — {$motivo}."
                . "\n  · FIX: quítalo del texto. Si lo que quieres es explicar POR QUÉ se retiró, "
                . 'eso va en un docblock marcado como historia, no en lo que el usuario lee al '
                . "pedir ayuda.\n  Ayuda actual:\n{$ayuda}"
            );
        }
    }
});

it('la aplicación única no tiene contextos que ofrecer, y la ayuda lo dice', function () {
    expect(ModuleMode::SingleApp->supportedContextKeys())->toBe(
        [],
        'FALLA: la aplicación única declara contextos. · FIX: sin eje de contexto no hay nada que '
        . 'elegir; supportedContextKeys() debe devolver [] para ese modo.'
    );

    foreach (comandosConAyuda() as $nombre => $comando) {
        $definicion = $comando->getDefinition();

        if (! $definicion->hasOption('context')) {
            continue;
        }

        $descripcion = $definicion->getOption('context')->getDescription();

        if (contextosOfrecidos($descripcion) === []) {
            continue;
        }

        expect(str_contains($descripcion, 'aplicación única'))->toBeTrue(
            "FALLA: la ayuda de {$nombre} enumera los contextos del multiinquilino y no dice qué "
            . 'hacer en aplicación única, que es la mitad de los proyectos.'
            . "\n  · FIX: añade a la descripción «En aplicación única no se pasa» — sin eso, quien "
            . "tiene ese modo no sabe si omitir la opción o inventarse un valor.\n  Dice: "
            . $descripcion
        );
    }
});
