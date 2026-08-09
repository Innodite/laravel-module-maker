<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands\Concerns;

use Innodite\LaravelModuleMaker\Support\DryRun;

/**
 * `--dry-run` for the five commands that write files or touch a database.
 *
 * The other five only read — they diagnose, plan or list — so a rehearsal there previews nothing.
 * Adding the flag to all ten would have been the tidy-looking choice and the wrong one: an option
 * that changes nothing teaches the developer that the option changes nothing, and then it gets
 * skipped on the command where it mattered.
 *
 * The pairing matters as much as the flag. A rehearsal that says "would write 41 files" and stops
 * is not a preview: what the developer needs to see is WHICH files, in which context, so a module
 * generated on the wrong axis is caught before it exists rather than after.
 */
trait RehearsesChanges
{
    /** Turns the rehearsal on when `--dry-run` came in, and says so before anything runs. */
    private function startRehearsal(): bool
    {
        if (! (bool) $this->option('dry-run')) {
            return false;
        }

        DryRun::enable();

        $this->components->info('ENSAYO (--dry-run): no se va a escribir ni a ejecutar nada.');

        return true;
    }

    /**
     * Prints what would have happened, and puts the switch back.
     *
     * The reset is not optional: the switch is process-wide, so leaving it on turns the next
     * command of the same process into a rehearsal that nobody asked for.
     */
    private function reportRehearsal(): void
    {
        if (! DryRun::active()) {
            return;
        }

        $acciones = DryRun::actions();

        DryRun::reset();

        $this->newLine();

        if ($acciones === []) {
            $this->components->warn('El ensayo no encontró nada que hacer: no habría cambios.');

            return;
        }

        $this->components->info('Esto es lo que haría (' . count($acciones) . '):');

        foreach ($acciones as $accion) {
            $this->line("  · {$accion}");
        }

        $this->newLine();
        $this->components->warn('Nada de lo anterior se ha hecho. Quita --dry-run para ejecutarlo.');
    }
}
