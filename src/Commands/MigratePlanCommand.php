<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\MigrationPlanResolver;
use Throwable;

class MigratePlanCommand extends Command
{
    protected $signature = 'innodite:migrate-plan
        {--manifest= : Manifiesto JSON (ej: central_order.json) en module-maker-config/migrations}
        {--dry-run : Muestra el plan sin ejecutar migraciones ni seeders}
        {--seed : Ejecuta seeders después de migraciones}';

    protected $description = 'Ejecuta migraciones modulares desde un manifiesto en orden explícito.';

    public function handle(): int
    {
        $resolver = new MigrationPlanResolver();
        $dryRun = (bool) $this->option('dry-run');
        $runSeeders = (bool) $this->option('seed');

        $this->newLine();
        $this->line('  <fg=blue;options=bold>Innodite ModuleMaker — Migrate Plan</>');
        $this->newLine();

        try {
            $manifestPath = $resolver->resolveManifestPath($this->option('manifest'));
            $plan = $resolver->loadPlan($manifestPath);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        $migrations = $plan['migrations'];
        $seeders = $plan['seeders'];
        $connectionName = $this->resolveExecutionConnection($manifestPath, $migrations, $seeders);

        if (empty($migrations) && (!$runSeeders || empty($seeders))) {
            $this->components->warn("El manifiesto '{$manifestPath}' no contiene tareas para ejecutar.");
            return self::SUCCESS;
        }

        if (!$dryRun) {
            $databaseValidationError = $this->validateDatabaseExists($connectionName);

            if ($databaseValidationError !== null) {
                $this->components->error($databaseValidationError);
                return self::FAILURE;
            }
        }

        $this->components->info("Manifiesto: {$manifestPath}");
        $this->line('  Conexion:   ' . $connectionName);
        $this->line('  Migraciones: ' . count($migrations));
        $this->line('  Seeders:     ' . ($runSeeders ? count($seeders) : 0));
        $this->newLine();

        // ── Migraciones ─────────────────────────────────────────────────────
        foreach ($migrations as $index => $coordinate) {
            try {
                $resolved = $resolver->resolveMigrationCoordinate($coordinate);
            } catch (Throwable $e) {
                $this->components->error($e->getMessage());
                return self::FAILURE;
            }

            $step = ($index + 1) . '/' . count($migrations);

            if ($dryRun) {
                $this->line("  [DRY-RUN] Migración {$step}: {$coordinate}");
                $this->line("           → {$resolved['path']}");
                continue;
            }

            $this->components->task("Migración {$step}: {$coordinate}", function () use ($resolved, $connectionName) {
                $exitCode = $this->call('migrate', [
                    '--path' => $resolved['path'],
                    '--database' => $connectionName,
                    '--realpath' => true,
                    '--force' => true,
                ]);

                return $exitCode === self::SUCCESS;
            });
        }

        // ── Seeders ─────────────────────────────────────────────────────────
        if ($runSeeders && !empty($seeders)) {
            $this->newLine();

            foreach ($seeders as $index => $coordinate) {
                try {
                    $resolved = $resolver->resolveSeederCoordinate($coordinate);
                } catch (Throwable $e) {
                    $this->components->error($e->getMessage());
                    return self::FAILURE;
                }

                $step = ($index + 1) . '/' . count($seeders);
                $fqcn = $resolved['fqcn'];

                if ($dryRun) {
                    $this->line("  [DRY-RUN] Seeder {$step}: {$coordinate}");
                    $this->line("           → {$fqcn}");
                    continue;
                }

                $this->components->task("Seeder {$step}: {$coordinate}", function () use ($fqcn, $connectionName) {
                    $exitCode = $this->call('db:seed', [
                        '--class' => $fqcn,
                        '--database' => $connectionName,
                        '--force' => true,
                    ]);

                    return $exitCode === self::SUCCESS;
                });
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->components->info('Dry-run completado. No se aplicaron cambios a la base de datos.');
        } else {
            $this->components->info('Migrate-plan completado correctamente.');
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $migrations
     * @param array<int, string> $seeders
     */
    private function resolveExecutionConnection(string $manifestPath, array $migrations, array $seeders): string
    {
        $manifestName = strtolower(basename($manifestPath));

        if (str_starts_with($manifestName, 'tenant_')) {
            return 'tenant';
        }

        $coordinates = array_merge($migrations, $seeders);

        foreach ($coordinates as $coordinate) {
            $normalized = strtolower($coordinate);

            if (str_contains($normalized, ':tenant/') || str_contains($normalized, ':tenant\\')) {
                return 'tenant';
            }
        }

        return (string) config('database.default', 'mysql');
    }

    private function validateDatabaseExists(string $connectionName): ?string
    {
        $connection = config("database.connections.{$connectionName}");

        if (!is_array($connection)) {
            return "La conexión '{$connectionName}' no está configurada.";
        }

        $driver = (string) ($connection['driver'] ?? '');
        $databaseName = (string) ($connection['database'] ?? '');

        if ($driver === 'sqlite') {
            if ($databaseName === '' || $databaseName === ':memory:') {
                return null;
            }

            if (!File::exists($databaseName)) {
                return "La base de datos '{$databaseName}' de la conexión '{$connectionName}' no existe.";
            }

            return null;
        }

        if ($databaseName === '') {
            return "La conexión '{$connectionName}' no tiene una base de datos configurada.";
        }

        try {
            $exists = match ($driver) {
                'mysql', 'mariadb' => DB::connection($connectionName)
                    ->table('information_schema.schemata')
                    ->where('schema_name', $databaseName)
                    ->exists(),
                'pgsql' => !empty(DB::connection($connectionName)
                    ->select('select 1 from pg_database where datname = ? limit 1', [$databaseName])),
                'sqlsrv' => !empty(DB::connection($connectionName)
                    ->select('select 1 from sys.databases where name = ?', [$databaseName])),
                default => true,
            };
        } catch (Throwable $e) {
            return "No se pudo validar la base de datos '{$databaseName}' de la conexión '{$connectionName}': {$e->getMessage()}";
        }

        if (!$exists) {
            return "La base de datos '{$databaseName}' de la conexión '{$connectionName}' no existe.";
        }

        return null;
    }
}
