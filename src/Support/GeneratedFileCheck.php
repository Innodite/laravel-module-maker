<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Innodite\LaravelModuleMaker\Exceptions\GeneratedFileRejectedException;
use ParseError;

/**
 * GeneratedFileCheck — nothing leaves the package without being looked at.
 *
 * This is the net under every other phase. A generator writes a file out of a stub plus a
 * map of keys, and the two drift: the stub asks for `attributes`, the generator delivers
 * `definitionAttributes`, and what lands in the user's project is `{{{ attributes }}}`
 * inside a `return [ … ]`. That was B13, and it had been shipping in every module generated
 * with `-F`, announced as a success each time, because nothing between the stub and the disk
 * ever asked whether the result made sense.
 *
 * Two questions, asked of every file, right before writing:
 *
 *   1. Is there a placeholder nobody resolved?
 *   2. If it claims to be PHP, does PHP parse it?
 *
 * Whatever fails, fails here — with the file name and the orphan token — instead of six
 * months later in someone else's project. That turns a whole family of bugs (B13, the seven
 * dangling keys of A11, and every future stub/generator drift) into one immediate, named
 * generation error.
 *
 * Syntax is checked in-process with `token_get_all(..., TOKEN_PARSE)`, not by shelling out to
 * `php -l`: same parser, no temp file, no dependency on a `php` binary being on PATH — which
 * a package that runs inside someone else's app cannot assume.
 */
final class GeneratedFileCheck
{
    /**
     * @throws GeneratedFileRejectedException When the content must not be written
     */
    public static function assertWritable(string $path, string $content): void
    {
        self::assertNoUnresolvedPlaceholders($path, $content);
        self::assertValidPhp($path, $content);
        self::assertNamespaceMatchesPath($path, $content);
    }

    /**
     * Does the declared namespace mirror the folder the file is being written to?
     *
     * The third question, and it exists because the first two both said yes to a broken file.
     * A seeder was landing in `Database/Seeders/Central/Permission/` while declaring
     * `namespace Modules\Permission\Database\Seeders` — valid PHP, no placeholders left, and
     * unloadable: PSR-4 looks for the class where the namespace says it is, finds nothing, and
     * the seeder cannot be run at all.
     *
     * That is the shape of every silent failure found in this package so far — B13, B15, the
     * marker of B14: not wrong logic, but two halves that stopped agreeing. Path and namespace
     * are exactly such a pair, so they get checked against each other.
     *
     * **Case is not part of the comparison, and that is deliberate.** A Laravel project's own
     * seeders live in `database/seeders/` and declare `namespace Database\Seeders` — the
     * framework's classmap convention, not PSR-4. Comparing case-sensitively would reject a file
     * that is not only correct but written exactly as Laravel writes its own. What this question
     * exists to catch survives the relaxation untouched: B17 declared
     * `Modules\Permission\Database\Seeders` while landing in `Database/Seeders/Central/Permission`
     * — a different path, not the same one in another case.
     */
    private static function assertNamespaceMatchesPath(string $path, string $content): void
    {
        if (! str_ends_with(strtolower($path), '.php')) {
            return;
        }

        if (preg_match('/^\s*namespace\s+([A-Za-z_][A-Za-z0-9_\\\\]*)\s*;/m', $content, $found) !== 1) {
            return;   // un archivo sin namespace no tiene nada que contradecir
        }

        $expectedTail = strtolower(str_replace('\\', '/', $found[1]));
        $actualDir    = strtolower(str_replace('\\', '/', dirname($path)));

        if (! str_ends_with($actualDir, $expectedTail)) {
            throw GeneratedFileRejectedException::namespacePathMismatch(
                $path,
                $found[1],
                str_replace('\\', '/', dirname($path))
            );
        }
    }

    /**
     * A placeholder that survived until now is a key the stub asked for and nobody delivered.
     *
     * The `.vue` distinction is the whole reason the delimiter changed. There, double braces
     * are Vue's own interpolation — legitimate, and the user's code. So in a `.vue` only the
     * package's `{{{ }}}` counts as unresolved; everywhere else, both forms do, because a
     * `{{ key }}` left in a PHP file after v4 is a stub that nobody migrated and that now
     * resolves to nothing.
     */
    private static function assertNoUnresolvedPlaceholders(string $path, string $content): void
    {
        preg_match_all(StubPlaceholder::unresolvedPattern(), $content, $unresolved);

        if ($unresolved[0] !== []) {
            throw GeneratedFileRejectedException::unresolvedPlaceholders($path, $unresolved[0]);
        }

        if (self::isVue($path)) {
            return;
        }

        preg_match_all(StubPlaceholder::legacyPattern(), $content, $legacy);

        if ($legacy[0] !== []) {
            throw GeneratedFileRejectedException::legacyPlaceholders($path, $legacy[0]);
        }
    }

    /**
     * Parses the content when the file claims to be PHP.
     *
     * Only syntax, deliberately: this runs on files whose classes are not autoloadable yet
     * —they are being created— so anything beyond parsing would need the module to already
     * exist. Syntax is where the failures of this kind actually live: an unresolved
     * placeholder inside a `use` or a signature.
     */
    private static function assertValidPhp(string $path, string $content): void
    {
        if (! str_ends_with(strtolower($path), '.php')) {
            return;
        }

        try {
            token_get_all($content, TOKEN_PARSE);
        } catch (ParseError $e) {
            throw GeneratedFileRejectedException::invalidPhp($path, $e->getMessage());
        }
    }

    private static function isVue(string $path): bool
    {
        return str_ends_with(strtolower($path), '.vue');
    }
}
