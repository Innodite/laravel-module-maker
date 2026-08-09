<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands\Concerns;

use Innodite\LaravelModuleMaker\Support\PackageVersion;

/**
 * The one header the nine commands print, so they read as one tool.
 *
 * Before this, six of the nine printed a header and three did not — and the three that did not were
 * the generator, the installer and the deployer, which is to say the three whose output someone
 * actually pastes into a message when something goes wrong. Of the six that did, two titled themselves
 * in English (`Migrate One`, `Add Entity`) and four in Spanish, and one carried a hand-written
 * `v1.0.0` — a sibling of the `v3.0.0` the diagnostic used to announce.
 *
 * None of that is decoration. A tool whose commands introduce themselves differently reads as a pile
 * of scripts that happen to share a prefix, and the version on the first line is what tells whoever
 * reports a bug which code they were running.
 */
trait PrintsHeader
{
    /**
     * Prints `Innodite ModuleMaker — <what this run is doing>  v<installed version>`.
     *
     * @param  string  $queHace  What this particular run is about, in Spanish (R51) and specific:
     *                           "Módulo Invoice" beats "Generador de módulos".
     */
    private function cabecera(string $queHace): void
    {
        $version = PackageVersion::current();

        // La `v` la pone quien imprime, y solo si falta. Composer devuelve la etiqueta tal cual se
        // publicó —`v4.0.0-beta.1`, con su v—, así que anteponer otra daba `vv4.0.0-beta.1` en la
        // cabecera de los nueve comandos. Con `dev-main` o `sin determinar` no se antepone nada.
        $prefijo = preg_match('/^\d/', $version) === 1 ? 'v' : '';

        $this->newLine();
        $this->line(
            "  <fg=blue;options=bold>Innodite ModuleMaker — {$queHace}</>"
            . "  <fg=gray>{$prefijo}{$version}</>"
        );
        $this->newLine();
    }
}
