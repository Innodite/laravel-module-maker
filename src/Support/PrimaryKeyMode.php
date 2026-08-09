<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

/**
 * The primary key the generated tables carry.
 *
 * Four questions, one place. The migration asks what the key column looks like, the
 * migration asks again for every foreign key, the model asks whether it needs the ULID
 * trait, and the generated test suite asks how to build an id that does not exist so it
 * can assert a 404. Those four answers have to agree, and each of them lived in a
 * different file — which is the shape of every defect this package has been removing
 * for five phases: two halves that are each correct and point somewhere else.
 *
 * Unlike {@see ModuleMode}, this one HAS a default, and the difference matters. The three
 * project modes are equally legitimate, so guessing one produces a wrong structure
 * multiplied by every module; there is no right answer to fall back on. Here there is:
 * the house pattern requires ULID, so a project that says nothing gets exactly what the
 * pattern mandates. Choosing the other value is an explicit, informed opt-out.
 *
 * ⛔ The choice belongs at install time, next to the mode, and it does not change later:
 * switching once tables exist leaves new foreign keys of a type that cannot reference the
 * existing keys, and the constraint simply fails to build. That is a data migration in the
 * host project, not a configuration flag.
 */
enum PrimaryKeyMode: string
{
    /** ULID — what the pattern requires (R10). Not enumerable from a URL. */
    case Ulid = 'ulid';

    /** Auto-incrementing big integer — Laravel's own default, for whoever wants it. */
    case Increments = 'increments';

    /**
     * The configured key mode, defaulting to ULID.
     *
     * An unknown value is NOT silently replaced by the default: a project that wrote
     * `uuid` or `int` meant something, and generating ULID behind its back produces
     * migrations that contradict the intent — the exact failure this class exists to
     * prevent. Absent means "I did not choose"; wrong means "I chose and you ignored me".
     */
    public static function current(): self
    {
        $configured = config('make-module.primary_key');

        if ($configured === null || $configured === '') {
            return self::Ulid;
        }

        if (! is_string($configured)) {
            throw new \InvalidArgumentException(
                'FALLA: make-module.primary_key debe ser una cadena. · FIX: usa '
                . self::valoresValidos() . ' en config/make-module.php.'
            );
        }

        return self::tryFrom($configured)
            ?? throw new \InvalidArgumentException(
                "FALLA: clave primaria '{$configured}' desconocida. · FIX: usa "
                . self::valoresValidos() . ' en config/make-module.php, o quita la clave para '
                . 'quedarte con ULID, que es lo que exige el patrón.'
            );
    }

    /**
     * The primary key column, as the migration writes it.
     *
     * The generator writes this line and the stub writes none. Both writing it is what
     * produced `duplicate column name: id` — no generated migration could run at all.
     */
    public function primaryKeyColumn(): string
    {
        return match ($this) {
            self::Ulid => "\$table->ulid('id')->primary();",
            self::Increments => "\$table->id();",
        };
    }

    /**
     * The foreign key builder that matches this primary key.
     *
     * Same pair, one level down: a ULID primary key with a `foreignId` — an integer —
     * cannot be referenced, and the constraint fails to create. The types have to match.
     */
    public function foreignKeyMethod(): string
    {
        return match ($this) {
            self::Ulid => 'foreignUlid',
            self::Increments => 'foreignId',
        };
    }

    /** Does the generated model need Eloquent's `HasUlids`? */
    public function needsUlidTrait(): bool
    {
        return $this === self::Ulid;
    }

    /**
     * PHP expression that yields an id which does not exist, for the generated tests.
     *
     * The forgotten one. Every generated suite builds a missing id to assert a 404, and a
     * ULID literal against an auto-incrementing table asserts nothing useful. Without this
     * answer the other three still agree and the generated contract fails on a project
     * that opted out — the failure looking like a bug in code that is fine.
     */
    public function nonExistentIdExpression(): string
    {
        return match ($this) {
            self::Ulid => '(string) Str::ulid()',
            self::Increments => '999999999',
        };
    }

    /** Does the generated test file need Laravel's `Str` import? */
    public function needsStrImport(): bool
    {
        return $this === self::Ulid;
    }

    private static function valoresValidos(): string
    {
        return "'" . implode("' o '", array_column(self::cases(), 'value')) . "'";
    }
}
