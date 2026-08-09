<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Composer\InstalledVersions;
use Throwable;

/**
 * The version of this package, asked instead of declared.
 *
 * The diagnostic used to print `v3.0.0` in its header — a literal, written three times inside one
 * command, while the package shipped 3.6 and this branch builds the 4. A version string that is
 * typed by hand is wrong from the second release onwards, and it is wrong in the worst possible
 * place: the first line a developer reads when something already broke. They report the version the
 * screen showed, and everyone spends the afternoon looking at the wrong tag.
 *
 * Composer already knows the answer, because Composer is what installed the package. `InstalledVersions`
 * is generated at install time and answers for the root project too, so this works both inside a host
 * project and inside this repository's own test suite.
 *
 * The fallback is not a second source of truth — it is what to say when there is no Composer autoloader
 * at all (a package copied by hand, an installation being repaired). It reads as an estimate on purpose:
 * whoever sees it should distrust it, which is exactly the reaction a hardcoded `v3.0.0` never provoked.
 */
final class PackageVersion
{
    /** The Composer name this package is installed under. */
    public const PACKAGE = 'innodite/laravel-module-maker';

    /** What to answer when Composer cannot be asked. Deliberately not a clean release number. */
    public const UNKNOWN = 'sin determinar';

    /**
     * The installed version — `4.0.0`, `dev-feat/v4`, or {@see self::UNKNOWN}.
     */
    public static function current(): string
    {
        if (! class_exists(InstalledVersions::class)) {
            return self::UNKNOWN;
        }

        try {
            $version = InstalledVersions::getPrettyVersion(self::PACKAGE);
        } catch (Throwable) {
            // `getPrettyVersion()` throws when the package is not among the installed ones. That is
            // not an error worth propagating: a diagnostic that dies while printing its own header
            // is worse than one that admits it does not know its version.
            return self::UNKNOWN;
        }

        return is_string($version) && $version !== '' ? $version : self::UNKNOWN;
    }
}
