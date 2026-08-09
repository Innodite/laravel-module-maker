<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * El entorno con el que se lanza la suite — el que decide contra qué base de datos corre.
 *
 * **Aquí se cerró un fallo que la propia prueba generada tuvo que detener.** Un `<env>` del
 * `phpunit.xml` sin `force="true"` respeta lo que ya exista en el entorno, y en un contenedor
 * siempre existe: la imagen exporta `DB_DATABASE` con la base real. Un proyecto que declaraba su
 * base de pruebas exactamente como debe terminaba corriendo la suite **contra los datos de verdad**,
 * y solo se enteró porque la guarda de la prueba generada se negó a seguir.
 *
 * Pedirle a cada proyecto que repita `force="true"` en cada línea es trasladar el problema; el
 * paquete es quien lanza el proceso, así que es quien entrega el entorno.
 */
it('entrega al subproceso las variables que declara el phpunit.xml', function () {
    $runner = File::get(dirname(__DIR__, 2) . '/src/Services/PhpunitRunner.php');

    expect($runner)->toContain('TestDatabase::variablesDeLaSuite()');

    // Y van como tercer argumento del proceso, que es lo que hace que ganen sobre las heredadas.
    expect($runner)->toContain('new Process($comando, base_path(), TestDatabase::variablesDeLaSuite()');
});

it('la base de pruebas se lee del phpunit.xml del proyecto', function () {
    // La fuente es el archivo del proyecto, no una convención del paquete: quien sabe cuál es el
    // entorno de pruebas es quien lo declaró.
    $soporte = File::get(dirname(__DIR__, 2) . '/src/Support/TestDatabase.php');

    expect($soporte)->toContain('public static function variablesDeLaSuite(): array');
});
