<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services\SchemaCloners;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * SQLite — where the database is a file, so the twin is another file next to it.
 *
 * It exists for two reasons, and the second is the honest one. The first: small projects and local
 * prototypes run on SQLite, and they deserve the same test database as everyone else. The second:
 * it is what lets this package **prove that the clone works**, end to end, inside its own suite —
 * creating a MySQL database needs a MySQL server, and a command that creates databases and is only
 * checked on its guard clauses is a command nobody has actually seen run.
 *
 * ⛔ `:memory:` is not clonable and says so: two in-memory databases cannot see each other, so there
 * is nothing to copy from and nowhere to copy to.
 */
final class SqliteSchemaCloner implements SchemaCloner
{
    private const CONEXION_DESTINO = 'innodite_bd_test';

    public function soporta(string $driver): bool
    {
        return $driver === 'sqlite';
    }

    /**
     * `algo/kapitalizando.sqlite` → `algo/kapitalizando_test.sqlite`.
     *
     * The suffix goes on the file name and not on the extension, so the twin sorts next to the
     * original in the directory listing and still opens as a database.
     */
    public function nombreDestino(array $config): string
    {
        $ruta = (string) ($config['database'] ?? '');

        if ($ruta === ':memory:') {
            throw new RuntimeException(':memory:');
        }

        $directorio = dirname($ruta);
        $extension  = pathinfo($ruta, PATHINFO_EXTENSION);
        $nombre     = pathinfo($ruta, PATHINFO_FILENAME);

        if (! str_ends_with($nombre, '_test')) {
            $nombre .= '_test';
        }

        return $directorio . '/' . $nombre . ($extension !== '' ? '.' . $extension : '');
    }

    public function existe(string $conexion, string $destino): bool
    {
        return File::exists($destino);
    }

    public function tablasDe(string $conexion): array
    {
        $filas = DB::connection($conexion)->select(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' "
            . 'ORDER BY name'
        );

        return array_map(fn ($fila) => (string) $fila->name, $filas);
    }

    public function clonar(string $conexion, string $destino, bool $rehacer): array
    {
        if ($rehacer && File::exists($destino)) {
            File::delete($destino);
        }

        File::ensureDirectoryExists(dirname($destino));

        if (! File::exists($destino)) {
            File::put($destino, '');
        }

        $origen = DB::connection($conexion);
        $target = $this->conexionAlDestino($conexion, $destino);

        // Tablas, índices y disparadores: los tres llevan su DDL guardado en sqlite_master, así que
        // la copia es literal. Las tablas van primero — un índice sobre una tabla que todavía no
        // existe no se puede crear.
        $objetos = $origen->select(
            "SELECT type, name, sql FROM sqlite_master "
            . "WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' "
            . "ORDER BY CASE type WHEN 'table' THEN 0 WHEN 'index' THEN 1 ELSE 2 END, name"
        );

        $tablas = [];

        try {
            foreach ($objetos as $objeto) {
                $target->statement((string) $objeto->sql);

                if ((string) $objeto->type === 'table') {
                    $tablas[] = (string) $objeto->name;
                }
            }
        } finally {
            DB::purge(self::CONEXION_DESTINO);
        }

        return $tablas;
    }

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
}
