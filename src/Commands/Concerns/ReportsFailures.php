<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands\Concerns;

/**
 * For the ten commands: a failure states what broke AND how to fix it.
 *
 * The package already applies this to the tests it generates — `FALLA: … · FIX: …` — and the
 * commands did not. Most of them did explain the way out, in prose, on the lines below the error;
 * what they lacked was the shape. That sounds cosmetic and is not, for two reasons.
 *
 * The first is that a fix nobody can find is a fix nobody applies: a wall of four sentences hides
 * the one line that says what to type. The second: a red run gets classified before it gets
 * investigated, and classifying means reading many failures fast. A message with a marked FIX can be
 * skimmed; one without it has to be read whole, and then it gets skipped.
 *
 * ⛔ The FIX is what the developer does next, not a restatement of the failure. "El contexto no
 * existe · FIX: usa un contexto que exista" is the shape of a message that adds nothing.
 */
trait ReportsFailures
{
    /**
     * Prints a failure with its fix, and optionally why it matters.
     *
     * @param  string  $falla   What went wrong, in one line
     * @param  string  $fix     What to do about it — an order, a command, a value
     * @param  string  $porque  Optional: the consequence that makes the fix worth applying
     */
    private function fallo(string $falla, string $fix, string $porque = ''): void
    {
        $this->components->error(self::mensajeDeFallo($falla, $fix, $porque));
    }

    /**
     * The failure message as text, for the callers that throw instead of printing.
     *
     * Same shape either way: an exception the developer reads in a stack trace deserves the fix as
     * much as a line printed in a console.
     */
    public static function mensajeDeFallo(string $falla, string $fix, string $porque = ''): string
    {
        $mensaje = "FALLA: {$falla}\n  · FIX: {$fix}";

        return $porque === '' ? $mensaje : $mensaje . "\n  {$porque}";
    }
}
