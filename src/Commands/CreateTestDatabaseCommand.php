<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\PrintsHeader;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Innodite\LaravelModuleMaker\Services\SchemaCloners\MySqlSchemaCloner;
use Innodite\LaravelModuleMaker\Services\SchemaCloners\SchemaCloner;
use Innodite\LaravelModuleMaker\Services\SchemaCloners\SqliteSchemaCloner;
use Innodite\LaravelModuleMaker\Support\DryRun;
use Throwable;

/**
 * Builds the `_test` database: the real schema, without a single row.
 *
 *     php artisan innodite:crear-bd-test
 *     php artisan innodite:crear-bd-test --connection=tenant_one --force
 *
 * **Why the package ships this and not each project.** Every project was writing its own — kapitalizando
 * has one, NeoCenter another under a different name — and it is not project code: it is the
 * infrastructure the test contract stands on (R81). One implementation, tested once, used by all.
 *
 * **Why a clone and not `migrate --path`.** Migrations build the schema they *describe*. The clone
 * builds the schema that *is*: the index somebody added by hand, the collation that differs on one
 * table, the column widened in production and never written down. A suite green against the first and
 * red against the second is the deployment that fails at 2am — and the reason it fails is that nobody
 * ever tested against the real shape.
 *
 * **Why it does not run on every test run.** It does not decide that; {@see TestCommand} does. But the
 * rule it obeys is worth stating here: re-cloning always would hide the thing that is dirtying the
 * database. In kapitalizando the same pair of tests failed three times from contamination before
 * anybody wrote it down, and that repetition is what led to the real defect underneath. A clean slate
 * on every run would have buried it for good.
 *
 * ⛔ **This is not the seeder's destructive mode.** That one rebuilds the canonical *data* inside a
 * schema that already exists, behind an explicit `SEEDER_DESTRUCTIVE`. This one rebuilds the *schema*,
 * with no rows at all. Confusing them is easy and expensive.
 */
class CreateTestDatabaseCommand extends Command
{
    use PrintsHeader;
    use ReportsFailures;

    protected $signature = 'innodite:crear-bd-test
        {--connection= : Conexión de la que se clona el esquema — por defecto, la de la aplicación}
        {--force : Rehace la base de pruebas desde cero, aunque ya exista}
        {--dry-run : Ensayo: enseña qué base crearía y qué tablas copiaría, sin tocar nada}';

    protected $description = 'Crea la base de pruebas _test como clon del esquema real, sin una sola fila.';

    /** El sufijo que hace de guarda. Sin él, este comando podría apuntar a la base de producción. */
    public const SUFIJO = '_test';

    public function handle(): int
    {
        $this->cabecera('Base de pruebas');

        $conexion = trim((string) $this->option('connection')) ?: (string) config('database.default');
        $config   = config("database.connections.{$conexion}");

        if (! is_array($config)) {
            $this->fallo(
                "no hay ninguna conexión llamada '{$conexion}'.",
                'pásale una de las declaradas en config/database.php — --connection=<nombre>',
                'De esa conexión sale el esquema que se clona: sin ella no hay de dónde copiar.'
            );

            return self::FAILURE;
        }

        $driver   = (string) ($config['driver'] ?? '');
        $clonador = $this->clonador($driver);

        if ($clonador === null) {
            $this->fallo(
                "el driver '{$driver}' todavía no se puede clonar.",
                'usa una conexión mysql, mariadb o sqlite — o crea la base _test a mano con el '
                . 'esquema de la real.',
                'Crear una base de datos es la operación en la que cada motor habla un idioma '
                . 'distinto, así que se implementa uno a uno en vez de fingir que son iguales.'
            );

            return self::FAILURE;
        }

        try {
            $destino = $clonador->nombreDestino($config);
        } catch (Throwable) {
            $this->fallo(
                'la conexión apunta a :memory:, y eso no se puede clonar.',
                'apúntala a un archivo — DB_DATABASE=database/database.sqlite',
                'Dos bases en memoria no se ven entre sí: no hay de dónde copiar ni dónde dejarlo.'
            );

            return self::FAILURE;
        }

        if (! $this->esDePruebas($destino)) {
            $this->fallo(
                "el destino '{$destino}' no termina en " . self::SUFIJO . '.',
                'renombra la base de pruebas para que termine en _test.',
                'Es la única guarda que separa este comando de borrar la base real, así que no tiene '
                . 'excepción.'
            );

            return self::FAILURE;
        }

        if ($destino === (string) ($config['database'] ?? '')) {
            $this->fallo(
                "la conexión '{$conexion}' ya apunta a la base de pruebas.",
                'ejecútalo contra la conexión de la base real — --connection=<la de verdad>',
                'Clonar una base sobre sí misma no copia el esquema real: lo que hace es rehacer el '
                . 'que ya tenías, con lo que traiga de malo.'
            );

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Origen', (string) $config['database'] . "  <fg=gray>({$conexion} · {$driver})</>");
        $this->components->twoColumnDetail('Destino', $destino);
        $this->newLine();

        return $this->clonar($clonador, $conexion, $destino);
    }

    private function clonar(SchemaCloner $clonador, string $conexion, string $destino): int
    {
        $rehacer = (bool) $this->option('force');
        $existe  = $clonador->existe($conexion, $destino);

        if (DryRun::active() || (bool) $this->option('dry-run')) {
            return $this->ensayo($clonador, $conexion, $destino, $existe, $rehacer);
        }

        if ($existe && ! $rehacer) {
            $this->components->warn("La base {$destino} ya existe: no se toca.");
            $this->line('  <fg=gray>Para rehacerla desde cero: --force</>');

            return self::SUCCESS;
        }

        try {
            $tablas = $clonador->clonar($conexion, $destino, $rehacer);
        } catch (Throwable $e) {
            $this->fallo(
                'el clonado se detuvo: ' . $e->getMessage(),
                'comprueba que el usuario de la conexión puede crear bases de datos — en MySQL, '
                . 'GRANT CREATE ON *.* al usuario que corre artisan.',
                'La base real no se ha tocado: todo lo que este comando escribe va a la de pruebas.'
            );

            return self::FAILURE;
        }

        $this->components->info(
            count($tablas) . " tabla(s) copiadas a {$destino}. Estructura, sin una sola fila."
        );
        $this->line('  <fg=gray>Los datos canónicos los pone el seeder de la subfuncionalidad, que es '
            . 'lo que el contrato prueba.</>');

        return self::SUCCESS;
    }

    /**
     * The rehearsal enumerates instead of counting.
     *
     * "Copiaría 34 tablas" is not a preview of anything: what has to be reviewable is *which* ones,
     * because the mistake this catches is having pointed at the wrong connection.
     */
    private function ensayo(
        SchemaCloner $clonador,
        string $conexion,
        string $destino,
        bool $existe,
        bool $rehacer
    ): int {
        $tablas = $clonador->tablasDe($conexion);

        $this->line('  <fg=yellow>ENSAYO — no se ha tocado nada.</>');
        $this->newLine();

        if ($existe && ! $rehacer) {
            $this->components->warn("La base {$destino} ya existe: no se tocaría. Con --force se rehace.");

            return self::SUCCESS;
        }

        $this->line('  ' . ($existe ? "Rehaería {$destino}" : "Crearía {$destino}") . ', y copiaría:');

        foreach ($tablas as $tabla) {
            $this->line("    · {$tabla}");
        }

        $this->newLine();
        $this->line('  <fg=gray>' . count($tablas) . ' tabla(s), estructura y ni una fila.</>');

        return self::SUCCESS;
    }

    /** One cloner per driver — the same shape stancl/tenancy uses for its database managers. */
    private function clonador(string $driver): ?SchemaCloner
    {
        foreach ([new MySqlSchemaCloner(), new SqliteSchemaCloner()] as $clonador) {
            if ($clonador->soporta($driver)) {
                return $clonador;
            }
        }

        return null;
    }

    /**
     * The guard: the destination has to end in `_test`.
     *
     * On SQLite the name is a path, so what is checked is the file name without its extension —
     * `algo/kapitalizando_test.sqlite` passes, `algo/kapitalizando.sqlite` does not.
     */
    private function esDePruebas(string $destino): bool
    {
        $nombre = str_contains($destino, '/') || str_contains($destino, '\\')
            ? pathinfo($destino, PATHINFO_FILENAME)
            : $destino;

        return str_ends_with($nombre, self::SUFIJO);
    }
}
