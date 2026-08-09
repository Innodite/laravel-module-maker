<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services\SchemaCloners;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * MySQL and MariaDB — the engines the projects actually run on.
 *
 * The structure is copied with `SHOW CREATE TABLE`, statement by statement, rather than rebuilt from
 * a Blueprint. It is the only way to get the schema that *is* instead of the one the migrations
 * describe: indexes added by hand, collations that differ per table, a column somebody widened in
 * production — all of it comes across, and none of it is in any migration file.
 */
final class MySqlSchemaCloner implements SchemaCloner
{
    /** The connection this cloner opens against the test database. Reused, never persisted. */
    private const CONEXION_DESTINO = 'innodite_bd_test';

    public function soporta(string $driver): bool
    {
        return in_array($driver, ['mysql', 'mariadb'], true);
    }

    public function nombreDestino(array $config): string
    {
        $real = (string) ($config['database'] ?? '');

        return str_ends_with($real, '_test') ? $real : $real . '_test';
    }

    public function existe(string $conexion, string $destino): bool
    {
        $filas = DB::connection($conexion)->select(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$destino]
        );

        return $filas !== [];
    }

    public function tablasDe(string $conexion): array
    {
        $base   = (string) DB::connection($conexion)->getDatabaseName();
        $tablas = DB::connection($conexion)->select(
            'SELECT TABLE_NAME AS nombre FROM information_schema.TABLES '
            . "WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            [$base]
        );

        return array_map(fn ($fila) => (string) $fila->nombre, $tablas);
    }

    public function clonar(string $conexion, string $destino, bool $rehacer): array
    {
        $origen = DB::connection($conexion);

        // El `CREATE DATABASE` va por la conexión de origen porque es la única abierta todavía; lo
        // que NO se hace nunca por ahí es DDL sobre sus tablas.
        if ($rehacer) {
            $origen->statement("DROP DATABASE IF EXISTS `{$destino}`");
        }

        $origen->statement(
            "CREATE DATABASE IF NOT EXISTS `{$destino}` "
            . 'DEFAULT CHARACTER SET ' . $this->charset($conexion)
            . ' COLLATE ' . $this->collation($conexion)
        );

        $target = $this->conexionAlDestino($conexion, $destino);
        $tablas = $this->tablasDe($conexion);

        // Las foráneas se apagan mientras dura la copia: las tablas se crean en orden alfabético y
        // una que apunta a otra que todavía no existe abortaría. Se vuelven a encender al terminar,
        // así que la base resultante las tiene igual que la real.
        $target->statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tablas as $tabla) {
                $ddl = $origen->select("SHOW CREATE TABLE `{$tabla}`");

                if ($ddl === []) {
                    continue;
                }

                $sentencia = (array) $ddl[0];
                $target->statement((string) ($sentencia['Create Table'] ?? ''));
            }
        } finally {
            $target->statement('SET FOREIGN_KEY_CHECKS = 1');
            DB::purge(self::CONEXION_DESTINO);
        }

        return $tablas;
    }

    /**
     * A connection to the test database, built from the real one.
     *
     * Everything is inherited — host, credentials, charset — and only the database name changes.
     * Declaring a separate connection in the project's `database.php` would be a second place to keep
     * in sync, and the day the password rotates only one of the two gets updated.
     */
    private function conexionAlDestino(string $conexion, string $destino): \Illuminate\Database\Connection
    {
        Config::set(
            'database.connections.' . self::CONEXION_DESTINO,
            array_merge(
                (array) Config::get("database.connections.{$conexion}", []),
                ['database' => $destino]
            )
        );

        DB::purge(self::CONEXION_DESTINO);

        return DB::connection(self::CONEXION_DESTINO);
    }

    private function charset(string $conexion): string
    {
        return (string) (Config::get("database.connections.{$conexion}.charset") ?: 'utf8mb4');
    }

    private function collation(string $conexion): string
    {
        return (string) (Config::get("database.connections.{$conexion}.collation") ?: 'utf8mb4_unicode_ci');
    }
}
