<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use InvalidArgumentException;

/**
 * StubPlaceholder — the one place that knows what a placeholder looks like.
 *
 * The package writes files for three different languages at once, and two of them
 * already use curly braces for something else. Three token shapes coexist:
 *
 *   {{{ key }}}                 a PLACEHOLDER — the package resolves it while generating
 *   {{ item.name }}             a Vue INTERPOLATION — belongs to the user, never touched
 *   // {{CENTRAL_ROUTES_END}}   an INJECTION MARKER — survives in the project so later
 *                               runs know where to add routes
 *
 * Triple braces, not double, and that is the whole point. The package used to resolve
 * `{{ key }}` — the exact syntax Vue interpolates with. It survived on a detail: only
 * known keys were replaced, so everything else passed through. The day a placeholder was
 * named like a view variable, the generator would rewrite the user's own code, silently,
 * in their project. Triple braces collide with nothing: not Vue, not Blade (`{{ }}` and
 * `{!! !!}`), not Twig.
 *
 * Markers keep double braces without inner spaces, and that difference is load-bearing:
 * it is what lets the output check tell "a placeholder nobody resolved" from "a marker
 * that is supposed to still be there". Both patterns live here so the generator and the
 * check cannot drift apart — which is precisely how the route marker of B14 ended up
 * declared in one place and searched for in another.
 */
final class StubPlaceholder
{
    /** Opening and closing delimiters of a package placeholder. */
    public const OPEN  = '{{{';
    public const CLOSE = '}}}';

    /**
     * Wraps a bare key into the token the stubs carry.
     *
     * The key must arrive bare. A caller that wraps it first produces a double wrap
     * (`{{{ {{{ key }}} }}}`), which matches nothing and replaces nothing — the whole
     * file then ships with its placeholders intact. That is not a hypothesis: it is what
     * B15 was, and it shipped four broken Vue views per module because the failure is
     * silent. Refusing a wrapped key turns it into a loud one.
     *
     * @throws InvalidArgumentException When the key already carries delimiters
     */
    public static function wrap(string $key): string
    {
        if (str_contains($key, '{') || str_contains($key, '}')) {
            throw new InvalidArgumentException(
                "El placeholder '{$key}' llega ya envuelto en llaves.\n" .
                "Las claves se pasan desnudas: ['modelName' => 'Role'], no ['{{{ modelName }}}' => 'Role'].\n" .
                'Quien envuelve es ' . self::class . '::wrap(), y lo hace una sola vez.'
            );
        }

        return self::OPEN . ' ' . $key . ' ' . self::CLOSE;
    }

    /**
     * Matches a package placeholder nobody resolved.
     *
     * What the output check looks for in every generated file, `.vue` included: there,
     * this is the only shape that means "unresolved", because double braces are Vue's.
     */
    public static function unresolvedPattern(): string
    {
        return '/\{\{\{\s*[A-Za-z_][A-Za-z0-9_]*\s*\}\}\}/';
    }

    /**
     * Matches a placeholder still written in the pre-v4 double-brace form.
     *
     * Worth its own pattern for one reason: a project that published stubs under v3 has
     * them written this way, and after the delimiter change nothing would resolve them.
     * Silence there would ship a broken module; this pattern is what turns it into an
     * error that names the stub. Not applied to `.vue` files, where double braces are
     * legitimate Vue.
     *
     * Requires the inner spaces, which is what keeps injection markers out: they are
     * `{{MARKER}}`, never `{{ marker }}`. The lookarounds keep the inside of a valid
     * triple-brace token from matching.
     */
    public static function legacyPattern(): string
    {
        return '/(?<!\{)\{\{ [A-Za-z_][A-Za-z0-9_]* \}\}(?!\})/';
    }

    /**
     * Matches an injection marker, which is meant to survive in the generated file.
     *
     * `// {{CENTRAL_ROUTES_END}}` is how a later run finds where to add routes. The
     * output check must not read it as an unresolved placeholder.
     */
    public static function injectionMarkerPattern(): string
    {
        return '/\{\{[A-Z][A-Z0-9_]*\}\}/';
    }
}
