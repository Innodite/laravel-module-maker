<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The `_test` database seen from the test runner: is it there, is it ours, is it still the real shape?
 *
 * The three questions {@see \Innodite\LaravelModuleMaker\Commands\TestCommand} has to answer before it
 * launches anything, and each one has a failure that looks like something else:
 *
 *   · **Is it ours?** A suite pointed at the real database does not fail — it passes, and deletes
 *     production data on the way. This is the only check here that stops the run.
 *   · **Is it there?** No database, no test. Asking about it is a pause that decides nothing.
 *   · **Is it the real shape?** A `_test` cloned two migrations ago is green about a schema nobody
 *     runs. That green is worse than red: it is a deployment that will fail with the tests passing.
 *
 * ⛔ What it does NOT do is re-clone on every run. Cleaning always hides whatever is dirtying the
 * database — in kapitalizando the same pair of tests failed three times from contamination before
 * anybody wrote it down, and that repetition is what led to the defect underneath (R81).
 */
final class TestDatabase
{
    public const SUFIJO = '_test';

    /** La conexión que se registra al vuelo para mirar la base real. Nunca se persiste. */
    public const CONEXION_REAL = 'innodite_real';

    /** Nombre de la base a la que apunta una conexión. */
    public static function nombreDe(string $conexion): string
    {
        return (string) Config::get("database.connections.{$conexion}.database", '');
    }

    /**
     * ¿Es una base de pruebas?
     *
     * En SQLite el nombre es una ruta, así que se mira el archivo sin su extensión:
     * `database/negocio_test.sqlite` sí, `database/negocio.sqlite` no.
     */
    public static function esDePruebas(string $nombre): bool
    {
        $corto = str_contains($nombre, '/') || str_contains($nombre, '\\')
            ? pathinfo($nombre, PATHINFO_FILENAME)
            : $nombre;

        return str_ends_with($corto, self::SUFIJO);
    }

    /** El nombre de la base real: el de la de pruebas sin su sufijo. */
    public static function realDe(string $nombreDePruebas): string
    {
        if (! str_contains($nombreDePruebas, '/') && ! str_contains($nombreDePruebas, '\\')) {
            return (string) preg_replace('/' . self::SUFIJO . '$/', '', $nombreDePruebas);
        }

        $directorio = dirname($nombreDePruebas);
        $extension  = pathinfo($nombreDePruebas, PATHINFO_EXTENSION);
        $archivo    = (string) preg_replace('/' . self::SUFIJO . '$/', '', pathinfo($nombreDePruebas, PATHINFO_FILENAME));

        return $directorio . '/' . $archivo . ($extension !== '' ? '.' . $extension : '');
    }

    /**
     * Registra —y devuelve— una conexión que apunta a la base real.
     *
     * Es la misma conexión de siempre con otro nombre de base: host, credenciales y charset se heredan.
     * Declararla en el `database.php` del proyecto sería un segundo sitio que mantener, y el día que
     * rote la contraseña solo se actualizaría uno de los dos.
     */
    public static function registrarReal(string $conexionDePruebas): string
    {
        $config = (array) Config::get("database.connections.{$conexionDePruebas}", []);

        Config::set(
            'database.connections.' . self::CONEXION_REAL,
            array_merge($config, ['database' => self::realDe((string) ($config['database'] ?? ''))])
        );

        DB::purge(self::CONEXION_REAL);

        return self::CONEXION_REAL;
    }

    /**
     * ¿La base de pruebas no está, o ya no tiene la forma de la real?
     *
     * Se comparan los **nombres de las tablas**, no su DDL completo. Es lo que detecta el caso que
     * importa —una migración nueva que la `_test` no tiene— sin convertir cada arranque del contrato
     * en una comparación de esquemas de veinte segundos.
     *
     * Si la base real no se puede mirar (un servidor donde solo existe la de pruebas), responde
     * `false`: lo que no se puede comprobar no se declara desfasado.
     */
    public static function desfasada(string $conexionDePruebas): bool
    {
        try {
            $deLaPrueba = self::tablas($conexionDePruebas);
        } catch (Throwable) {
            return true;   // no se puede ni abrir: falta
        }

        try {
            $deLaReal = self::tablas(self::registrarReal($conexionDePruebas));
        } catch (Throwable) {
            return false;  // la real no se puede mirar: no se afirma nada sobre el desfase
        }

        return $deLaPrueba !== $deLaReal;
    }

    /**
     * Los nombres de las tablas de una conexión, ordenados.
     *
     * @return array<int, string>
     */
    public static function tablas(string $conexion): array
    {
        $nombres = array_map(
            fn ($tabla) => is_array($tabla) ? (string) ($tabla['name'] ?? '') : (string) $tabla->getName(),
            DB::connection($conexion)->getSchemaBuilder()->getTables()
        );

        sort($nombres);

        return array_values(array_filter($nombres, fn ($n) => $n !== '' && ! str_starts_with($n, 'sqlite_')));
    }
}
