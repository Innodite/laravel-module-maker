<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Services\MigrationPlanResolver;
use Throwable;

class MigrationSyncCommand extends Command
{
    protected $signature = 'innodite:migration-sync
        {--manifest= : Manifiesto JSON (ej: central_order.json) en module-maker-config/migrations}
        {--dry-run : Muestra coordenadas faltantes sin escribir el manifiesto}';

    protected $description = 'Escanea módulos y agrega al manifiesto las migraciones/seeders no registrados.';

    public function handle(): int
    {
        $resolver = new MigrationPlanResolver();
        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();
        $this->line('  <fg=blue;options=bold>Innodite ModuleMaker — Migration Sync</>');
        $this->newLine();

        try {
            $manifestPath = $this->resolveOrCreateManifestPath();
            $plan = $resolver->loadPlan($manifestPath);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        $foundMigrations = $this->scanMigrationCoordinates();
        $foundSeeders = $this->scanSeederCoordinates();

        $existingMigrations = $plan['migrations'];
        $existingSeeders = $plan['seeders'];

        $missingMigrations = array_values(array_diff($foundMigrations, $existingMigrations));
        $missingSeeders = array_values(array_diff($foundSeeders, $existingSeeders));

        sort($missingMigrations);
        sort($missingSeeders);

        $this->components->info("Manifiesto: {$manifestPath}");
        $this->line('  Migraciones encontradas: ' . count($foundMigrations));
        $this->line('  Seeders encontrados:     ' . count($foundSeeders));
        $this->line('  Migraciones faltantes:   ' . count($missingMigrations));
        $this->line('  Seeders faltantes:       ' . count($missingSeeders));
        $this->newLine();

        if (empty($missingMigrations) && empty($missingSeeders)) {
            $this->components->info('No hay elementos faltantes. El manifiesto ya está sincronizado.');
            return self::SUCCESS;
        }

        foreach ($missingMigrations as $coordinate) {
            $this->line("  + migration: {$coordinate}");
        }

        foreach ($missingSeeders as $coordinate) {
            $this->line("  + seeder:    {$coordinate}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->components->info('Dry-run completado. No se escribió el manifiesto.');
            return self::SUCCESS;
        }

        $plan['migrations'] = array_values(array_unique(array_merge($existingMigrations, $missingMigrations)));
        $plan['seeders'] = array_values(array_unique(array_merge($existingSeeders, $missingSeeders)));

        File::put($manifestPath, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $this->newLine();
        $this->components->info('Sincronización completada. El manifiesto fue actualizado.');

        return self::SUCCESS;
    }

    private function resolveOrCreateManifestPath(): string
    {
        $manifest = trim((string) ($this->option('manifest') ?: 'central_order.json'));

        if ($manifest === '') {
            $manifest = 'central_order.json';
        }

        if (File::exists($manifest)) {
            return $manifest;
        }

        $dir = rtrim((string) config('make-module.config_path'), '/\\') . '/migrations';
        File::ensureDirectoryExists($dir);

        $path = $dir . '/' . $manifest;

        if (!File::exists($path)) {
            File::put($path, json_encode(['migrations' => [], 'seeders' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        }

        return $path;
    }

    /**
     * @return array<int, string>
     */
    private function scanMigrationCoordinates(): array
    {
        $modulesPath = (string) config('make-module.module_path');
        if (!File::isDirectory($modulesPath)) {
            return [];
        }

        $coordinates = [];

        foreach (File::directories($modulesPath) as $moduleDir) {
            $moduleName = Str::studly(basename($moduleDir));
            $base = $moduleDir . '/Database/Migrations';

            if (!File::isDirectory($base)) {
                continue;
            }

            foreach (File::allFiles($base) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));

                if (!str_contains($relativePath, '/')) {
                    // Solo sincronizamos coordenadas con contexto explícito.
                    continue;
                }

                $coordinates[] = "{$moduleName}:{$relativePath}";
            }
        }

        return array_values(array_unique($coordinates));
    }

    /**
     * @return array<int, string>
     */
    private function scanSeederCoordinates(): array
    {
        $modulesPath = (string) config('make-module.module_path');
        if (!File::isDirectory($modulesPath)) {
            return [];
        }

        $coordinates = [];

        foreach (File::directories($modulesPath) as $moduleDir) {
            $moduleName = Str::studly(basename($moduleDir));
            $base = $moduleDir . '/Database/Seeders';

            if (!File::isDirectory($base)) {
                continue;
            }

            foreach (File::allFiles($base) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));

                if (!str_contains($relativePath, '/')) {
                    // Solo sincronizamos coordenadas con contexto explícito.
                    continue;
                }

                $classPath = preg_replace('/\.php$/', '', $relativePath) ?? $relativePath;
                $coordinates[] = "{$moduleName}:{$classPath}";
            }
        }

        return array_values(array_unique($coordinates));
    }
}
