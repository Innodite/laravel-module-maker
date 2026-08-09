<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

/**
 * The rehearsal switch: what a command WOULD do, without doing any of it.
 *
 * A generator writes dozens of files across the host project, and a deploy runs seeders against
 * a real database. Both are worth seeing before they happen — and for the same reason the whole
 * package exists: what these commands produce gets multiplied by every module of every project,
 * so the cheapest moment to notice a wrong context or a wrong connection is before the first
 * file lands.
 *
 * The switch is process-wide on purpose. The alternative — threading a boolean through every
 * generator, service and stub — is the shape of change that gets applied to nine of ten callers,
 * and the tenth writes to disk during a rehearsal without anyone noticing for months. One gate,
 * asked in one place ({@see Disk}), cannot be half-applied.
 *
 * ⛔ It is NOT a validation mode: a rehearsal still runs the output check on everything it would
 * write, so it reports a file that would not parse instead of pretending all is well.
 */
final class DryRun
{
    private static bool $active = false;

    /** @var array<int, string> Human-readable lines: what would have happened, in order. */
    private static array $actions = [];

    /** Turns the rehearsal on and clears whatever a previous one recorded. */
    public static function enable(): void
    {
        self::$active = true;
        self::$actions = [];
    }

    /**
     * Back to writing for real.
     *
     * Tests need this between cases: the switch outlives a single command, which is the price of
     * having exactly one gate instead of a flag passed around.
     */
    public static function reset(): void
    {
        self::$active = false;
        self::$actions = [];
    }

    public static function active(): bool
    {
        return self::$active;
    }

    /** Records something that would have happened — a file, a directory, a seeder run. */
    public static function record(string $action): void
    {
        self::$actions[] = $action;
    }

    /** @return array<int, string> */
    public static function actions(): array
    {
        return self::$actions;
    }
}
