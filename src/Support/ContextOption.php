<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use InvalidArgumentException;

/**
 * What the mode demands of `--context`, before anyone resolves anything.
 *
 * Three rules, and each one exists because its absence produced a wrong module that nobody
 * noticed:
 *
 *   single-app + --context   Error. The mode has no context axis, so the option cannot be
 *                            honoured. Accepting it and ignoring it makes the developer
 *                            believe the module came out contextualised when it did not.
 *
 *   multitenant, no context  Error, listing the catalogue — when there is nobody to ask.
 *                            Falling back to the first context in contexts.json is guessing,
 *                            and it guesses `central`: routes in web.php, guarded by
 *                            `central-permission`, for a subfeature that was meant for
 *                            tenants. The file is perfectly written and completely wrong.
 *
 *   context not in the       Error, listing what the catalogue does have. Generating into the wrong
 *   catalogue                axis produces a module that reads as correct and is not: routes in the
 *                            wrong file, guarded by the other context's permission.
 *
 * ⭐ **The catalogue decides, not a list of names.** This guard used to reject any key the MODE did
 * not know, which meant it rejected the very thing the package promises: that a project may declare
 * a context of its own. Everything downstream was already ready for one — the generator reads
 * `permission_prefix`, `permission_middleware` and `route_middleware` from the catalogue and only
 * falls back to the mode when they are empty; the route file comes from `route_file`; the connection
 * from `connection_key`; `is_tenant` says whether it is a tenant. Every half was right except this
 * one, which is this package's recurring defect shape.
 *
 * It lives here and not inside a command because BOTH commands that generate — `make-module`
 * and `add-entity` — need the same three answers. They used to each carry their own copy of
 * the resolution ("lógica idéntica a MakeModuleCommand::resolveContext()" said the docblock),
 * and the guards had only ever been added to one of them. That is this package's recurring
 * defect shape: two halves that are each correct and no longer agree.
 */
final class ContextOption
{
    /**
     * @param  string                $option       Whatever came in `--context`, already trimmed
     * @param  array<string, mixed>  $allContexts  The catalogue, as contexts.json declares it
     * @param  bool                  $canAsk       Whether the command can still ask the user
     *
     * @throws InvalidArgumentException When the mode cannot honour what was (or was not) passed
     */
    public static function check(ModuleMode $mode, string $option, array $allContexts, bool $canAsk): void
    {
        if (! $mode->hasContextAxis()) {
            if ($option !== '') {
                throw new InvalidArgumentException(
                    "FALLA: el modo '{$mode->value}' no tiene contextos: no pases "
                    . "--context={$option}.\n"
                    . "  · FIX: lánzalo sin --context; y si este proyecto sí tiene tenants, cambia "
                    . "make-module.mode en la configuración.\n"
                    . '  En una aplicación única la subfuncionalidad va directa bajo la capa '
                    . '(Models/Role/), sin Central/ ni Tenant/ y sin prefijo de clase.'
                );
            }

            return;
        }

        if ($option === '' && ! $canAsk) {
            throw new InvalidArgumentException(
                "FALLA: el modo '{$mode->value}' exige --context y no se pasó ninguno.\n"
                . '  · FIX: pásalo — ' . self::catalogo($allContexts) . "\n"
                . '  Asumir uno escribe el módulo entero en el eje equivocado: las rutas en el archivo'
                . " que no es\n  y protegidas con el permiso de otro contexto. El archivo sale"
                . ' perfecto y completamente mal.'
            );
        }

        if ($option !== '' && ! isset($allContexts[$option])) {
            throw new InvalidArgumentException(
                "FALLA: el contexto '{$option}' no está en contexts.json.\n"
                . '  · FIX: escribe uno de los que hay — ' . self::catalogo($allContexts) . ", o "
                . "declara el tuyo en module-maker-config/contexts.json.\n"
                . '  Generar en el eje equivocado produce un módulo que parece correcto y no lo es:'
                . " las rutas en el archivo que no es\n  y protegidas con el permiso de otro contexto."
            );
        }
    }

    /**
     * The catalogue as the error message lists it: the context keys, and the tenants by id.
     *
     * @param  array<string, mixed>  $allContexts
     */
    private static function catalogo(array $allContexts): string
    {
        $claves = implode(', ', array_keys($allContexts));

        $tenants = array_map(
            static fn (array $t): string => (string) ($t['id'] ?? 'sin-id'),
            array_filter((array) ($allContexts['tenant'] ?? []), 'is_array')
        );

        return $tenants === []
            ? "contextos: {$claves}"
            : "contextos: {$claves} · tenants: " . implode(', ', $tenants);
    }
}
