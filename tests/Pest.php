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
