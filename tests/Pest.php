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
