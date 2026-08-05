<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Exceptions;

use RuntimeException;

/**
 * Thrown when the project has not declared which kind of application it is.
 *
 * There is no sensible default here. The mode decides the shape of every file the
 * package writes — whether the context axis exists, whether class names carry a
 * prefix, whether a tenant declares a connection, which middleware guards a route.
 * Picking one silently would generate a module that looks right and is wrong in the
 * one place nobody checks, and the mistake would multiply across every module of
 * every project. Refusing to run is the cheaper failure.
 */
final class ModeNotConfiguredException extends RuntimeException
{
    public static function missing(): self
    {
        return new self(
            "No has elegido el modo del proyecto, y de él depende la forma de todo lo que se genera.\n\n"
            . "  Ejecuta:  php artisan innodite:module-setup\n"
            . "  O define 'mode' en config/make-module.php con uno de estos valores:\n\n"
            . "    single-app              Aplicación única, sin tenancy\n"
            . "    multitenant-shared      Todos los tenants comparten funcionalidad\n"
            . "    multitenant-per-tenant  Cada tenant con lógica de negocio propia\n"
        );
    }

    /**
     * @param  array<int, string>  $valid
     */
    public static function invalid(string $given, array $valid): self
    {
        return new self(
            "El modo '{$given}' no existe.\n\n"
            . "  Valores válidos para 'mode' en config/make-module.php:\n"
            . '    ' . implode("\n    ", $valid) . "\n"
        );
    }
}
