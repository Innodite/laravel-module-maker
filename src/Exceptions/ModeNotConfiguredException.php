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
            "FALLA: no has elegido el modo del proyecto, y de él depende la forma de todo lo que "
            . "se genera.\n"
            . "  · FIX: ejecuta php artisan innodite:module-setup\n"
            . "  O define 'mode' en config/make-module.php con uno de estos valores:\n\n"
            . "    single-app   Aplicación única, sin tenancy\n"
            . "    multitenant  Aplicación central e inquilinos\n"
        );
    }

    /**
     * The declared mode existed until 4.x and does not any more.
     *
     * Named on purpose instead of falling through to {@see self::invalid()}: the configuration says
     * something that was legitimate, so "el modo no existe" would read as a typo. And ⛔ it is not
     * translated silently either — a project that declared `multitenant-per-tenant` chose named
     * tenants with their own connection, and both halves of that choice are gone. It has to know.
     */
    public static function retired(string $given, string $reemplazo): self
    {
        return new self(
            "FALLA: el modo '{$given}' se retiró en la 5.0.\n"
            . "  · FIX: escribe '{$reemplazo}' en 'mode' de config/make-module.php "
            . "(o MODULE_MAKER_MODE en tu .env).\n"
            . "  Los dos modos multiinquilino se unieron en uno. Su única diferencia era nombrar a "
            . "cada\n  inquilino y declararle conexión, y las dos cosas resultaron equivocadas: "
            . "nombrarlo multiplica\n  lógica idéntica por cliente, y declarar la conexión ata el "
            . "modelo a una base cuando quien la\n  conmuta es el middleware de tenencia en cada "
            . "petición.\n"
            . "  Si tu proyecto necesita de verdad un contexto propio para un cliente, decláralo en "
            . "tu\n  module-maker-config/contexts.json: el paquete lo acepta, y ya no hace falta un "
            . "modo para eso.\n"
        );
    }

    /**
     * @param  array<int, string>  $valid
     */
    public static function invalid(string $given, array $valid): self
    {
        return new self(
            "FALLA: el modo '{$given}' no existe.\n"
            . "  · FIX: usa uno de estos en 'mode' de config/make-module.php:\n"
            . '    ' . implode("\n    ", $valid) . "\n"
        );
    }
}
