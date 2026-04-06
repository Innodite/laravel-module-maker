<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Exceptions\ConnectionNotConfiguredException;
use Innodite\LaravelModuleMaker\Services\MigrationPlanResolver;
use Innodite\LaravelModuleMaker\Services\MigrationTargetService;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

class MigrateOneCommand extends Command
{
    protected $signature = 'innodite:migrate-one
        {coordinate : Coordenada de la migración a ejecutar}
        {--manifest= : Manifiesto JSON específico. Si se omite, se detecta automáticamente}
        {--yes : Confirma automáticamente la actualización del manifiesto y la ejecución}
        {--dry-run : Muestra lo que haría sin escribir ni ejecutar}';

    protected $description = 'Ejecuta una migración específica y la registra en su manifiesto si hace falta.';

    public function handle(): int
    {
        $resolver = new MigrationPlanResolver();
        $targetService = new MigrationTargetService();
        $coordinate = trim((string) $this->argument('coordinate'));
        $dryRun = (bool) $this->option('dry-run');
        $manifestOption = (string) $this->option('manifest');

        $this->newLine();
        $this->line('  <fg=blue;options=bold>Innodite ModuleMaker — Migrate One</>');
        $this->newLine();

        try {
            $resolved = $resolver->resolveMigrationCoordinate($coordinate);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        $targets = $targetService->resolveTargetsForCoordinate($coordinate, $manifestOption);

        if (empty($targets)) {
            $this->components->error('No se pudo determinar un manifiesto objetivo para la coordenada indicada.');
            return self::FAILURE;
        }

        $selectedTargets = $this->selectTargets($targets);
        if ($selectedTargets === null) {
            return self::FAILURE;
        }

        foreach ($selectedTargets as $target) {
            try {
                $manifestPath = $targetService->ensureManifestPath($target['manifest']);
                $plan = $resolver->loadPlan($manifestPath);
            } catch (Throwable $e) {
                $this->components->error($e->getMessage());
                return self::FAILURE;
            }

            $alreadyRegistered = in_array($coordinate, $plan['migrations'], true);

            $connectionName = $targetService->resolveExecutionConnection(
                $manifestPath,
                [$coordinate],
                []
            );
            $databaseName = $targetService->resolveDatabaseName($connectionName);

            if (!$dryRun) {
                // Guard Rail R03: validar que connection_key esté registrada en config/database.php
                if (preg_match('/^([a-z0-9][a-z0-9-]*)\.order\.json$/', basename($manifestPath), $m)) {
                    try {
                        ContextResolver::validateConnection($m[1]);
                    } catch (ConnectionNotConfiguredException $e) {
                        $this->components->error($e->getMessage());
                        return self::FAILURE;
                    }
                }

                $databaseValidationError = $targetService->validateDatabaseExists($connectionName);
                if ($databaseValidationError !== null) {
                    $this->components->error($databaseValidationError);
                    return self::FAILURE;
                }
            }

            $this->components->info("Destino: {$target['manifest']} ({$target['label']})");
            $this->line('  Tipo:          migracion');
            $this->line('  Coordenada:    ' . $coordinate);
            $this->line('  Conexion:      ' . $connectionName);
            $this->line('  Base de datos: ' . ($databaseName !== '' ? $databaseName : '[sin definir]'));
            $this->line('  Manifiesto:    ' . $manifestPath);
            $this->line('  Registro:      ' . ($alreadyRegistered ? 'ya existe en el manifiesto' : 'se agregara al manifiesto antes de ejecutar'));
            $this->line('  Archivo:       ' . $resolved['path']);
            $this->newLine();

            if (!$this->shouldExecuteTarget($target, $alreadyRegistered)) {
                $this->components->warn('Ejecucion cancelada por el usuario.');
                $this->newLine();
                continue;
            }

            if ($dryRun) {
                if (!$alreadyRegistered) {
                    $this->line('  [DRY-RUN] Se agregaria la coordenada al manifiesto.');
                }
                $this->line('  [DRY-RUN] Se ejecutaria la migracion especificada.');
                $this->newLine();
                continue;
            }

            if ($targetService->addCoordinateIfMissing($plan, 'migrations', $coordinate)) {
                File::put($manifestPath, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
                $this->components->info('Coordenada agregada al manifiesto antes de ejecutar.');
            }

            $success = $this->executeMigration((string) $resolved['path'], $connectionName);

            if (!$success) {
                return self::FAILURE;
            }

            $this->newLine();
        }

        if ($dryRun) {
            $this->components->info('Dry-run completado. No se aplicaron cambios.');
        } else {
            $this->components->info('migrate-one completado correctamente.');
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, array{manifest: string, type: string, slug: string|null, label: string}> $targets
     * @return array<int, array{manifest: string, type: string, slug: string|null, label: string}>|null
     */
    private function selectTargets(array $targets): ?array
    {
        if (count($targets) === 1) {
            return $targets;
        }

        $this->components->info('Se detectaron multiples manifiestos aplicables:');
        foreach ($targets as $target) {
            $this->line("  - {$target['manifest']} ({$target['label']})");
        }
        $this->newLine();

        if ((bool) $this->option('yes')) {
            return $targets;
        }

        if (!$this->input instanceof InputInterface || !$this->input->isInteractive()) {
            $this->components->error('Hay múltiples destinos posibles. Usa --manifest para uno específico o ejecuta el comando en modo interactivo.');
            return null;
        }

        $runAll = (bool) $this->confirm('La coordenada aplica a múltiples manifiestos. Deseas ejecutarla en todos?', false);

        if ($runAll) {
            return $targets;
        }

        $choices = array_map(static fn (array $target): string => $target['manifest'], $targets);
        $selectedManifest = (string) $this->choice('Selecciona el manifiesto objetivo', $choices, 0);

        return array_values(array_filter($targets, static fn (array $target): bool => $target['manifest'] === $selectedManifest));
    }

    /**
     * @param array{manifest: string, type: string, slug: string|null, label: string} $target
     */
    private function shouldExecuteTarget(array $target, bool $alreadyRegistered): bool
    {
        if ((bool) $this->option('yes')) {
            return true;
        }

        if (!$this->input instanceof InputInterface || !$this->input->isInteractive()) {
            $this->components->error('Este comando requiere confirmación interactiva. Usa --yes si deseas omitirla.');
            return false;
        }

        $manifestAction = $alreadyRegistered ? 'sin modificar el manifiesto' : 'agregandola primero al manifiesto';

        return (bool) $this->confirm(
            "Se ejecutara la migracion en {$target['manifest']} {$manifestAction}. Deseas continuar?",
            false
        );
    }

    private function executeMigration(string $path, string $connectionName): bool
    {
        return $this->components->task('Ejecutando migracion especifica', function () use ($path, $connectionName) {
            $exitCode = $this->call('migrate', [
                '--path' => $path,
                '--database' => $connectionName,
                '--realpath' => true,
                '--force' => true,
            ]);

            return $exitCode === self::SUCCESS;
        });
    }
}