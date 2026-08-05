<?php

declare(strict_types=1);

/**
 * El cruce que encontró B13 y A11, hecho prueba.
 *
 * La auditoría lo hizo a mano una vez: comparar los placeholders que cada stub pide con las
 * claves que su generador entrega. Encontró un crítico —el factory pedía dos claves que nadie
 * entregaba y no compilaba— y siete valores que se calculaban para nada. Un cruce que solo se
 * hace cuando alguien se acuerda vuelve a fallar en cuanto nadie se acuerda; por eso vive aquí.
 *
 * Las dos direcciones son asimétricas y las dos importan:
 *
 *   stub pide → generador no entrega   el archivo sale con el placeholder dentro (B13)
 *   generador entrega → stub no pide   trabajo que se calcula y se tira (A11)
 *
 * La primera la ataja además el chequeo de salida, en ejecución. La segunda solo se ve así,
 * porque no rompe nada: es la señal de que stub y generador evolucionaron por separado — que es
 * exactamente la causa raíz de la primera.
 */

/**
 * Lee los mapas de placeholders que cada generador pasa a getStubContent().
 *
 * @return array<int, array{generador: string, stub: string, claves: array<int, string>}>
 */
function mapasDePlaceholders(): array
{
    $mapas = [];

    foreach (glob(dirname(__DIR__, 2) . '/src/Generators/Components/**/*.php') ?: [] as $file) {
        $archivos[] = $file;
    }
    foreach (glob(dirname(__DIR__, 2) . '/src/Generators/Components/*.php') ?: [] as $file) {
        $archivos[] = $file;
    }

    foreach (array_unique($archivos ?? []) as $file) {
        $src = file_get_contents($file);

        preg_match_all(
            "/getStubContent\(\s*'?([\w.-]+\.stub)'?[^\[]*\[(.*?)\]\s*[,)]/s",
            $src,
            $llamadas,
            PREG_SET_ORDER
        );

        foreach ($llamadas as $llamada) {
            preg_match_all("/'(\w+)'\s*=>/", $llamada[2], $claves);

            $mapas[] = [
                'generador' => basename($file),
                'stub'      => $llamada[1],
                'claves'    => $claves[1],
            ];
        }
    }

    return $mapas;
}

it('encuentra los mapas de placeholders de los generadores', function () {
    expect(mapasDePlaceholders())->not->toBeEmpty(
        'Si esto sale vacío, el patrón que lee los mapas dejó de encajar con el código y las dos '
        . 'pruebas siguientes pasan sin comprobar nada — que es peor que fallar.'
    );
});

it('ningún generador entrega una clave que su stub no usa', function () {
    $sobrantes = [];

    foreach (mapasDePlaceholders() as $mapa) {
        $stubPath = dirname(__DIR__, 2) . "/stubs/contextual/{$mapa['stub']}";

        if (! file_exists($stubPath)) {
            continue;   // stub huérfano: lo cubre la prueba siguiente
        }

        $contenido = file_get_contents($stubPath);

        foreach ($mapa['claves'] as $clave) {
            if (! str_contains($contenido, '{{{ ' . $clave . ' }}}')) {
                $sobrantes[] = "{$mapa['generador']} entrega '{$clave}' y {$mapa['stub']} no la usa";
            }
        }
    }

    expect($sobrantes)->toBe(
        [],
        "Trabajo que se calcula y se tira (A11):\n  - " . implode("\n  - ", $sobrantes)
        . "\n\nO el stub debería usarla, o el generador debería dejar de calcularla. Las dos son "
        . 'arreglos; dejarla es la señal de que stub y generador van por separado.'
    );
});

it('cada stub que un generador nombra existe en el paquete', function () {
    $huerfanos = [];

    foreach (mapasDePlaceholders() as $mapa) {
        if (! file_exists(dirname(__DIR__, 2) . "/stubs/contextual/{$mapa['stub']}")) {
            $huerfanos[] = "{$mapa['generador']} lee {$mapa['stub']}, que no existe";
        }
    }

    expect($huerfanos)->toBe(
        [],
        "Un generador que nombra un stub inexistente falla al ejecutarse:\n  - "
        . implode("\n  - ", $huerfanos)
    );
});
