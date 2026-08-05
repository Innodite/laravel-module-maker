<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Exceptions;

use RuntimeException;

/**
 * Thrown when a generated file fails the output check and is therefore not written.
 *
 * The point of the exception is *where* it fires. Before it existed, a stub asking for a
 * placeholder nobody delivered produced a file with `{{{ key }}}` inside — valid enough to
 * write, broken enough to fail later, in someone else's project, months on. B13 shipped that
 * way: the factory's `use {{ modelNamespace }};` was invalid PHP in every module ever
 * generated with `-F`, and the package announced success each time.
 *
 * So the error names the file and the placeholder, and it fires at generation time.
 */
final class GeneratedFileRejectedException extends RuntimeException
{
    /**
     * @param  array<int, string>  $placeholders  The unresolved tokens, verbatim
     */
    public static function unresolvedPlaceholders(string $path, array $placeholders): self
    {
        $list = implode(', ', array_unique($placeholders));

        return new self(
            "No se escribió '" . basename($path) . "': lleva placeholders sin resolver → {$list}\n" .
            "Ruta: {$path}\n" .
            "Cada uno es una clave que el stub pide y su generador no entrega. Añádela al mapa de\n" .
            'placeholders del generador, o quítala del stub si ya no hace falta.'
        );
    }

    /**
     * @param  array<int, string>  $placeholders  Tokens still in the pre-v4 `{{ key }}` form
     */
    public static function legacyPlaceholders(string $path, array $placeholders): self
    {
        $list = implode(', ', array_unique($placeholders));

        return new self(
            "No se escribió '" . basename($path) . "': lleva placeholders en el formato de la v3 → {$list}\n" .
            "Ruta: {$path}\n" .
            "Desde la v4 el delimitador es {{{ clave }}} — la doble llave colisiona con Vue.\n" .
            'Si el stub es tuyo (publicado con `innodite:stubs publish`), pásalo a triple llave.'
        );
    }

    public static function invalidPhp(string $path, string $error): self
    {
        return new self(
            "No se escribió '" . basename($path) . "': el PHP generado no es válido.\n" .
            "Ruta: {$path}\n" .
            "Error del parser: {$error}\n" .
            'Revisa el stub y las claves que le entrega su generador: un placeholder sin resolver ' .
            'dentro de un `use` o una firma deja el archivo sin cargar.'
        );
    }
}
