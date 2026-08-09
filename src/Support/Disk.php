<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;

/**
 * Every write this package performs, in one place — so a rehearsal can intercept all of them.
 *
 * The package writes from fourteen different files: generators, the two injection services that
 * edit the host project's own routes and deploy order, the event log, and the installer. Each one
 * used to call `File::` directly, which is fine until something has to be true of ALL writes at
 * once. {@see DryRun} is that something.
 *
 * It is the same move `putFile()` made in phase 1 for the output check: the value is not the
 * wrapper, it is that there is no second way through. A write that bypasses this class writes to
 * disk during a rehearsal, and that failure is silent — the command prints "would create" and the
 * file is already there.
 *
 * ⛔ Reads stay on `File::` deliberately. A rehearsal has to read the project exactly as a real run
 * would, or it rehearses a different scenario than the one it is previewing.
 */
final class Disk
{
    public static function put(string $path, string $content): void
    {
        if (DryRun::active()) {
            DryRun::record('escribiría  ' . self::short($path));

            return;
        }

        File::put($path, $content);
    }

    public static function append(string $path, string $content): void
    {
        if (DryRun::active()) {
            DryRun::record('añadiría a  ' . self::short($path));

            return;
        }

        File::append($path, $content);
    }

    public static function ensureDirectory(string $path): void
    {
        if (DryRun::active()) {
            // Solo se anota lo que aún no existe: en un módulo que ya está, la lista de carpetas
            // «creadas» sería ruido que tapa los archivos, que es lo que se viene a mirar.
            if (! File::isDirectory($path)) {
                DryRun::record('crearía     ' . self::short($path) . '/');
            }

            return;
        }

        File::ensureDirectoryExists($path);
    }

    public static function makeDirectory(string $path, int $mode = 0755, bool $recursive = true, bool $force = true): void
    {
        if (DryRun::active()) {
            if (! File::isDirectory($path)) {
                DryRun::record('crearía     ' . self::short($path) . '/');
            }

            return;
        }

        File::makeDirectory($path, $mode, $recursive, $force);
    }

    public static function copy(string $from, string $to): void
    {
        if (DryRun::active()) {
            DryRun::record('copiaría    ' . self::short($to));

            return;
        }

        File::copy($from, $to);
    }

    public static function copyDirectory(string $from, string $to): void
    {
        if (DryRun::active()) {
            DryRun::record('copiaría    ' . self::short($to) . '/ (carpeta entera)');

            return;
        }

        File::copyDirectory($from, $to);
    }

    /**
     * The path as the developer recognises it: relative to the project root when it is inside it.
     *
     * An absolute path in a temp directory says nothing at a glance, and the rehearsal exists to
     * be read at a glance.
     */
    private static function short(string $path): string
    {
        $raiz = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $raiz) ? substr($path, strlen($raiz)) : $path;
    }
}
