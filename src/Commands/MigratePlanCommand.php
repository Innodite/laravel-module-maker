<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Services\MigrationPlanResolver;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Throwable;

class MigratePlanCommand extends Command
{
    protected $signature = 'innodite:migrate-plan
        {--manifest= : Manifiesto JSON (ej: central.order.json) en module-maker-config/migrations}
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
        $connectionName = $this->resolveExecutionConnection($manifestPath, $migrations, $seeders, $dryRun);

        if ($connectionName === null) {
            return self::FAILURE;
        }

        if (empty($migrations) && (!$runSeeders || empty($seeders))) {
            $this->components->warn("El manifiesto '{$manifestPath}' no contiene tareas para ejecutar.");
            return self::SUCCESS;
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
     * Resuelve la conexión de ejecución para un manifest.
     *
     * Flujo explícito (v3.5.0+):
     *   1. Extrae el id del nombre del manifest (patrón {id}.order.json)
     *   2. Busca el contexto en contexts.json via ContextResolver::find()
     *   3. Valida que tenancy_strategy === 'manual' y que connection_key no esté vacío
     *   4. Retorna connection_key del contexto
     *
     * Retorna null y muestra un error amigable si la validación falla.
     *
     * @param array<int, string> $migrations
     * @param array<int, string> $seeders
     */
    private function resolveExecutionConnection(string $manifestPath, array $migrations, array $seeders, bool $dryRun = false): ?string
    {
        $manifestName = strtolower(basename($manifestPath));

        if (!preg_match('/^([a-z0-9][a-z0-9-]*)\.order\.json$/', $manifestName, $matches)) {
            $this->components->error(
                "El nombre del manifest '{$manifestName}' no tiene el formato esperado '{id}.order.json'."
            );
            return null;
        }

        $contextId = $matches[1];

        try {
            $context = ContextResolver::find($contextId);
        } catch (\Throwable) {
            $this->components->error(
                "No se encontró el contexto '{$contextId}' en contexts.json. " .
                "Verifica que el manifest corresponda a un contexto registrado."
            );
            return null;
        }

        $tenancyStrategy = $context['tenancy_strategy'] ?? null;
        if ($tenancyStrategy !== 'manual') {
            $this->components->error(
                "El contexto '{$contextId}' tiene tenancy_strategy='{$tenancyStrategy}'. " .
                "Solo se permite ejecutar migrate-plan con tenancy_strategy='manual'."
            );
            return null;
        }

        $connectionKey = $context['connection_key'] ?? null;
        if ($connectionKey === null || $connectionKey === '') {
            $this->components->error(
                "El contexto '{$contextId}' no tiene un connection_key definido. " .
                "Agrega un connection_key válido en contexts.json antes de ejecutar migrate-plan."
            );
            return null;
        }

        if (!$dryRun && !is_array(config("database.connections.{$connectionKey}"))) {
            $this->components->error(
                "La conexión '{$connectionKey}' del contexto '{$contextId}' no existe en config/database.php. " .
                "Créala manualmente o ejecuta innodite:make-connections."
            );
            return null;
        }

        return $connectionKey;
    }

}
