<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services\SchemaCloners;

/**
 * Clones the schema of a real database into its `_test` twin — structure only, not one row.
 *
 * One implementation per driver, the same shape stancl/tenancy uses for its database managers, and
 * for the same reason: creating a database is the one operation where every engine speaks a different
 * language, and pretending otherwise produces SQL that works on the developer's machine and fails on
 * the server.
 *
 * **Why the test database is a clone and not a set of migrations.** Running migrations builds the
 * schema the migrations *say* exists. The clone builds the schema that *is* — including the column
 * somebody added by hand in production three months ago. Tests that pass against the first and fail
 * against the second are the deployments that break at 2am.
 */
interface SchemaCloner
{
    /** Is this the driver this cloner speaks? */
    public function soporta(string $driver): bool;

    /**
     * The name of the test twin for a real database.
     *
     * @param  array<string, mixed>  $config  The `database.connections.X` entry of the real database
     */
    public function nombreDestino(array $config): string;

    /** Does the test database already exist? */
    public function existe(string $conexion, string $destino): bool;

    /**
     * Copies the structure across, and answers with the tables it copied.
     *
     * ⛔ Never writes to the source: every statement it issues is either a read on the source or DDL
     * on the destination.
     *
     * @param  string  $conexion  Name of the connection holding the real database
     * @param  string  $destino   Name (or path) of the test database — already validated
     * @param  bool    $rehacer   Drop the test database first, instead of adding to what is there
     * @return array<int, string> Tables copied, in the order they were copied
     */
    public function clonar(string $conexion, string $destino, bool $rehacer): array;

    /**
     * The tables the clone would copy — for the rehearsal, which must not touch anything.
     *
     * @return array<int, string>
     */
    public function tablasDe(string $conexion): array;
}
