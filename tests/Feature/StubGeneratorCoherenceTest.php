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
 *   stub pide → generador no entrega   el archivo sale con el placeholder dentro
 *   generador entrega → stub no pide   trabajo que se calcula y se tira
 *
 * La primera la ataja además el chequeo de salida, en ejecución. La segunda solo se ve así,
 * porque no rompe nada: es la señal de que stub y generador evolucionaron por separado — que es
 * exactamente la causa raíz de la primera.
 */

/**
 * Lee los mapas de placeholders que cada generador pasa a getStubContent().
 *
 * **Una llamada sin mapa es legítima**, y leerla mal costó un falso positivo: un stub cuyo contenido
 * es fijo se pide con `getStubContent('x.stub', false)` y ya está. El lector anterior buscaba el
 * siguiente `[` **fuera de la llamada**, así que le atribuía a ese stub el array de otra que venía
 * más abajo en el mismo archivo, y acusaba de sobrantes tres claves que sí se usaban donde tocaba.
 *
 * Por eso el mapa se busca **dentro** de la llamada: solo si el `[` aparece antes del `;` que la
 * cierra, y recortado por balance de corchetes en vez de por el primer `]`, que se quedaría a medias
 * en cuanto un valor lleve un índice dentro.
 *
 * @return array<int, array{generador: string, stub: string, claves: array<int, string>}>
 */
function mapasDePlaceholders(): array
{
    $mapas    = [];
    $archivos = [];

    foreach (glob(dirname(__DIR__, 2) . '/src/Generators/Components/**/*.php') ?: [] as $file) {
        $archivos[] = $file;
    }
    foreach (glob(dirname(__DIR__, 2) . '/src/Generators/Components/*.php') ?: [] as $file) {
        $archivos[] = $file;
    }

    foreach (array_unique($archivos) as $file) {
        $src = file_get_contents($file);

        preg_match_all(
            "/getStubContent\(\s*'([\w.-]+\.stub)'/",
            $src,
            $llamadas,
            PREG_OFFSET_CAPTURE
        );

        foreach ($llamadas[1] as [$stub, $offset]) {
            preg_match_all("/'(\w+)'\s*=>/", mapaDeLaLlamada($src, (int) $offset), $claves);

            $mapas[] = [
                'generador' => basename($file),
                'stub'      => $stub,
                'claves'    => $claves[1],
            ];
        }
    }

    return $mapas;
}

/** El array de placeholders de **esta** llamada, o cadena vacía si no lleva ninguno. */
function mapaDeLaLlamada(string $src, int $offset): string
{
    $corchete = strpos($src, '[', $offset);
    $cierre   = strpos($src, ';', $offset);

    if ($corchete === false || ($cierre !== false && $corchete > $cierre)) {
        return '';   // la llamada terminó antes de abrir ningún array
    }

    $profundidad = 0;

    for ($i = $corchete; $i < strlen($src); $i++) {
        $profundidad += (int) ($src[$i] === '[') - (int) ($src[$i] === ']');

        if ($profundidad === 0) {
            return substr($src, $corchete + 1, $i - $corchete - 1);
        }
    }

    return '';
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
        "Trabajo que se calcula y se tira:\n  - " . implode("\n  - ", $sobrantes)
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
