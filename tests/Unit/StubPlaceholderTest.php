<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\StubPlaceholder;

/**
 * El delimitador del paquete es `{{{ clave }}}` y no `{{ clave }}` por una razón concreta:
 * Vue interpola con doble llave. Mientras el paquete usó la misma sintaxis que el lenguaje
 * al que escribe, un placeholder llamado como una variable de la vista habría reescrito el
 * código del usuario en silencio. Estas pruebas fijan la frontera entre los tres tokens que
 * conviven —placeholder, interpolación de Vue y marcador de inyección— porque confundirlos
 * es exactamente lo que rompió las cuatro vistas de cada módulo (B15).
 */

it('envuelve una clave desnuda en triple llave', function () {
    expect(StubPlaceholder::wrap('modelName'))->toBe(
        '{{{ modelName }}}',
        'El token del paquete es triple llave con espacios. Revisa StubPlaceholder::wrap().'
    );
});

it('rechaza una clave que ya llega envuelta, en vez de no sustituir nada', function () {
    expect(fn () => StubPlaceholder::wrap('{{ modelName }}'))
        ->toThrow(InvalidArgumentException::class, 'llega ya envuelto en llaves');
});

it('el mensaje de la clave envuelta dice cómo se pasa bien', function () {
    try {
        StubPlaceholder::wrap('{{{ modelName }}}');
        $this->fail('Una clave envuelta debe lanzar: envolver dos veces no sustituye nada y el fallo es silencioso.');
    } catch (InvalidArgumentException $e) {
        expect(str_contains($e->getMessage(), "['modelName' => 'Role']"))->toBeTrue(
            'R30: el error dice qué hacer, no solo qué pasó. Debe mostrar el formato correcto de la clave.'
        );
    }
});

it('detecta un placeholder del paquete sin resolver', function () {
    expect(preg_match(StubPlaceholder::unresolvedPattern(), 'use {{{ modelNamespace }}};'))->toBe(
        1,
        'Un placeholder en triple llave que llega al archivo generado es un error de generación. '
        . 'Revisa StubPlaceholder::unresolvedPattern().'
    );
});

it('no confunde una interpolación de Vue con un placeholder sin resolver', function () {
    $vue = '<td>{{ item.name }}</td><span>{{ meta.total }}</span>{{ p }}';

    expect(preg_match(StubPlaceholder::unresolvedPattern(), $vue))->toBe(
        0,
        'La doble llave es de Vue y es código del usuario. Si el patrón la marca, el chequeo de '
        . 'salida rechazaría vistas correctas.'
    );
});

it('no marca el interior de un token válido como placeholder en formato viejo', function () {
    expect(preg_match(StubPlaceholder::legacyPattern(), '{{{ modelName }}}'))->toBe(
        0,
        'Dentro de {{{ x }}} hay un {{ x }} literal. Sin los lookarounds, el patrón del formato '
        . 'viejo marcaría todos los stubs nuevos. Revisa StubPlaceholder::legacyPattern().'
    );
});

it('reconoce el formato viejo, que tras el cambio ya no se resuelve', function () {
    expect(preg_match(StubPlaceholder::legacyPattern(), 'class {{ seederName }} extends Seeder'))->toBe(
        1,
        'Un stub publicado con la v3 quedaría sin resolver y el módulo saldría roto. '
        . 'El patrón existe para convertir ese silencio en un error con nombre.'
    );
});

it('no confunde un marcador de inyección con un placeholder', function () {
    $marker = '    // {{CENTRAL_ROUTES_END}}';

    expect(preg_match(StubPlaceholder::legacyPattern(), $marker))->toBe(
        0,
        'El marcador debe SOBREVIVIR en el archivo del proyecto: es como la siguiente ejecución '
        . 'encuentra dónde añadir rutas. Lo que lo distingue es que no lleva espacios interiores.'
    );
    expect(preg_match(StubPlaceholder::injectionMarkerPattern(), $marker))->toBe(
        1,
        'Revisa StubPlaceholder::injectionMarkerPattern(): debe reconocer los marcadores en MAYÚSCULAS.'
    );
});
