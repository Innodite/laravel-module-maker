<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
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

        if (empty($migrations) && (!$runSeeders || empty($seeders))) {
            $this->components->warn("El manifiesto '{$manifestPath}' no contiene tareas para ejecutar.");
            return self::SUCCESS;
        }

        $this->components->info("Manifiesto: {$manifestPath}");
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

            $this->components->task("Migración {$step}: {$coordinate}", function () use ($resolved) {
                $exitCode = $this->call('migrate', [
                    '--path' => $resolved['path'],
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

                $this->components->task("Seeder {$step}: {$coordinate}", function () use ($fqcn) {
                    $exitCode = $this->call('db:seed', [
                        '--class' => $fqcn,
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
}
