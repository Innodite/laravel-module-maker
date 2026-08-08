<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest Configuration — Innodite Laravel Module Maker
|--------------------------------------------------------------------------
|
| Aplica el TestCase base de Orchestra Testbench a todos los tests
| en las carpetas Feature/ y Unit/.
|
*/

use Innodite\LaravelModuleMaker\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * El contenido de un archivo PHP **sin sus comentarios** — lo que de verdad se ejecuta.
 *
 * Nace de un fallo concreto: una prueba comprobaba que el seeder de producción no borra, y hacía
 * fallar al docblock que explica **por qué** no lleva el modo destructivo. Prohibir la operación es
 * prohibirla en el código; prohibir nombrarla obliga al archivo generado a callar justo lo que más
 * falta hace entender.
 */
function soloCodigo(string $php): string
{
    $codigo = '';

    foreach (token_get_all($php) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $codigo .= is_array($token) ? $token[1] : $token;
    }

    return $codigo;
}

/**
 * Lo mismo que `soloCodigo()`, para un archivo Vue o JavaScript.
 *
 * Misma lección y mismo motivo: una prueba que prohíbe `alert()` en las pantallas generadas tiene que
 * dejar que el archivo **explique por qué** no lo usa, y una que prohíbe armar un nombre de clase de
 * Tailwind concatenando tiene que dejar que el componente cuente qué pasa si lo haces. Prohibir la
 * llamada es prohibirla en el código; prohibir nombrarla obliga al archivo a callar justo lo que más
 * falta hace entender.
 */
function sinComentarios(string $codigo): string
{
    return preg_replace(
        ['#/\*.*?\*/#s', '#//[^\n]*#', '#<!--.*?-->#s'],
        '',
        $codigo
    ) ?? $codigo;
}

/**
 * Carga en memoria las piezas de seeder de un módulo generado, en el orden en que PHP las admite.
 *
 * Dos detalles, y los dos son de PHP y no del paquete:
 *
 *   - **Los traits primero.** Una clase que usa un trait todavía sin cargar no se puede declarar, y
 *     el árbol generado no está en el autoloader del proyecto de pruebas.
 *   - **Lo ya declarado se salta.** Cada prueba genera su módulo en un directorio temporal distinto,
 *     pero las clases se llaman igual: volver a declararlas es un fatal. Es el mismo archivo escrito
 *     por el mismo generador, así que reutilizar el ya cargado es lo correcto.
 *
 * Vive aquí porque lo necesita cualquier prueba que quiera *ejecutar* lo generado en vez de leerlo —
 * que es la única forma de cazar un `use TraitQueNoExiste;`, que pasa el parser y revienta al
 * instanciar.
 *
 * @param  array<int, string>  $piezas  Nombres de clase/trait, tal como los da SeederNames
 */
function cargarPiezas(object $modulo, string $carpeta, array $piezas): void
{
    usort(
        $piezas,
        static fn (string $a, string $b): int => (int) str_ends_with($a, 'Seeder') <=> (int) str_ends_with($b, 'Seeder')
    );

    $namespace = 'Modules\\' . $modulo->name . '\\' . str_replace('/', '\\', $carpeta);

    foreach ($piezas as $pieza) {
        $fqcn = "{$namespace}\\{$pieza}";

        if (class_exists($fqcn, false) || trait_exists($fqcn, false)) {
            continue;
        }

        require_once $modulo->path("{$carpeta}/{$pieza}.php");
    }
}

/**
 * Carga un seeder del PROYECTO —los que escribe el instalador en `database/seeders/`—, si no lo
 * cargó ya otra prueba.
 *
 * Cada prueba genera en un directorio temporal distinto, pero la clase se llama igual: `require_once`
 * no lo ve —son rutas distintas— y declararla dos veces es un fatal. Es lo mismo que documenta
 * `cargarPiezas()` para las piezas de un módulo.
 */
function cargarSeederDelProyecto(string $clase): void
{
    if (class_exists("Database\\Seeders\\{$clase}", false)) {
        return;
    }

    require_once database_path("seeders/{$clase}.php");
}

/**
 * Salta la prueba si esta máquina no puede abrir la base de datos de las pruebas.
 *
 * El `phpunit.xml` del paquete declara sqlite en memoria desde siempre, pero hasta esta tarea ninguna
 * prueba abría una conexión, así que una máquina sin `pdo_sqlite` pasaba la suite entera sin enterarse.
 * Se salta **diciéndolo**, que es lo contrario de no escribir la prueba: en cuanto la extensión esté,
 * corre sola.
 */
function requiereBaseDeDatos(): void
{
    if (! extension_loaded('pdo_sqlite')) {
        test()->markTestSkipped(
            'Falta la extensión pdo_sqlite, que es la base de datos de las pruebas (phpunit.xml). '
            . 'Instálala con: sudo apt install php8.3-sqlite3'
        );
    }
}
