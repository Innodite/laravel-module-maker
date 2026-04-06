<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Services\MigrationPlanResolver;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

class MigrationSyncCommand extends Command
{
    protected $signature = 'innodite:migration-sync
        {--manifest= : Manifiesto JSON (ej: central.order.json) en module-maker-config/migrations}
        {--all-manifests : Detecta contexts.json y sincroniza manifiestos por alcance (central + tenants)}
        {--yes : Confirma automaticamente prompts de sincronizacion}
        {--dry-run : Muestra coordenadas faltantes sin escribir el manifiesto}';

    protected $description = 'Escanea módulos y agrega al manifiesto las migraciones/seeders no registrados.';

    public function handle(): int
    {
        $resolver = new MigrationPlanResolver();
        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();
        $this->line('  <fg=blue;options=bold>Innodite ModuleMaker — Migration Sync</>');
        $this->newLine();

        $foundMigrations = $this->scanMigrationCoordinates();
        $foundSeeders = $this->scanSeederCoordinates();

        $targets = $this->resolveTargets();

        if (count($targets) > 1) {
            $this->components->info('Se detectaron manifiestos por contexto:');
            foreach ($targets as $target) {
                $this->line("  - {$target['manifest']} ({$target['label']})");
            }
            $this->newLine();

            if (!$this->shouldProceed()) {
                $this->components->warn('Sincronizacion cancelada por el usuario.');
                return self::SUCCESS;
            }
        }

        $hasErrors = false;

        foreach ($targets as $target) {
            try {
                $manifestPath = $this->resolveOrCreateManifestPath($target['manifest']);
                $plan = $resolver->loadPlan($manifestPath);
            } catch (Throwable $e) {
                $this->components->error($e->getMessage());
                $hasErrors = true;
                continue;
            }

            $scopedMigrations = $this->filterCoordinatesForTarget($foundMigrations, $target);
            $scopedSeeders = $this->filterCoordinatesForTarget($foundSeeders, $target);

            $existingMigrations = $plan['migrations'];
            $existingSeeders = $plan['seeders'];

            $missingMigrations = array_values(array_diff($scopedMigrations, $existingMigrations));
            $missingSeeders = array_values(array_diff($scopedSeeders, $existingSeeders));

            sort($missingMigrations);
            sort($missingSeeders);

            $this->components->info("Manifiesto: {$manifestPath} ({$target['label']})");
            $this->line('  Migraciones encontradas: ' . count($scopedMigrations));
            $this->line('  Seeders encontrados:     ' . count($scopedSeeders));
            $this->line('  Migraciones faltantes:   ' . count($missingMigrations));
            $this->line('  Seeders faltantes:       ' . count($missingSeeders));
            $this->newLine();

            if (empty($missingMigrations) && empty($missingSeeders)) {
                $this->components->info('No hay elementos faltantes. El manifiesto ya esta sincronizado.');
                $this->newLine();
                continue;
            }

            foreach ($missingMigrations as $coordinate) {
                $this->line("  + migration: {$coordinate}");
            }

            foreach ($missingSeeders as $coordinate) {
                $this->line("  + seeder:    {$coordinate}");
            }

            if ($dryRun) {
                $this->newLine();
                $this->components->info('Dry-run completado. No se escribio el manifiesto.');
                $this->newLine();
                continue;
            }

            $plan['migrations'] = array_values(array_unique(array_merge($existingMigrations, $missingMigrations)));
            $plan['seeders'] = array_values(array_unique(array_merge($existingSeeders, $missingSeeders)));

            File::put($manifestPath, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

            $this->newLine();
            $this->components->info('Sincronizacion completada. El manifiesto fue actualizado.');
            $this->newLine();
        }

        if ($hasErrors) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveOrCreateManifestPath(string $manifestName): string
    {
        $manifest = trim($manifestName);

        if ($manifest === '') {
            $manifest = 'central.order.json';
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
     * @return array<int, array{manifest: string, type: string, id: string|null, label: string}>
     */
    private function resolveTargets(): array
    {
        $manifestOption = trim((string) $this->option('manifest'));

        if ($manifestOption !== '') {
            return [[
                'manifest' => $manifestOption,
                'type' => 'custom',
                'id' => null,
                'label' => 'custom',
            ]];
        }

        return $this->resolveAutoTargets();
    }

    /**
     * Resuelve los manifiestos objetivo detectados automáticamente desde contexts.json.
     *
     * Arquitectura v3.5.0+:
     *   - central.order.json: contiene Central + Shared
     *   - {id}.order.json: un archivo por tenant usando su campo 'id' del JSON
     *
     * @return array<int, array{manifest: string, type: string, id: string|null, label: string}>
     */
    private function resolveAutoTargets(): array
    {
        $targets = [[
            'manifest' => 'central.order.json',
            'type' => 'central',
            'id' => null,
            'label' => 'central+shared',
        ]];

        $contextsPath = (string) config('make-module.contexts_path');
        if (!File::exists($contextsPath)) {
            return $targets;
        }

        $decoded = json_decode((string) File::get($contextsPath), true);
        if (!is_array($decoded)) {
            return $targets;
        }

        $tenants = $decoded['contexts']['tenant'] ?? [];
        if (!is_array($tenants)) {
            return $targets;
        }

        foreach ($tenants as $tenant) {
            if (!is_array($tenant)) {
                continue;
            }

            $id = (string) ($tenant['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $targets[] = [
                'manifest' => "{$id}.order.json",
                'type' => 'tenant',
                'id' => $id,
                'label' => "tenant:{$id}",
            ];
        }

        $unique = [];
        foreach ($targets as $target) {
            $unique[$target['manifest']] = $target;
        }

        return array_values($unique);
    }

    private function shouldProceed(): bool
    {
        if ((bool) $this->option('yes')) {
            return true;
        }

        if (!$this->input instanceof InputInterface || !$this->input->isInteractive()) {
            return true;
        }

        return (bool) $this->confirm('Se sincronizaran los manifiestos detectados automaticamente. Deseas continuar?', true);
    }

    /**
     * Filtra coordenadas según pertenencia arquitectónica al target.
     *
     * @param array<int, string> $coordinates
     * @param array{manifest: string, type: string, id: string|null, label: string} $target
     * @return array<int, string>
     */
    private function filterCoordinatesForTarget(array $coordinates, array $target): array
    {
        $filtered = [];

        foreach ($coordinates as $coordinate) {
            $contextPath = $this->extractContextPath($coordinate);
            if ($contextPath === '') {
                continue;
            }

            if ($target['type'] === 'custom') {
                $filtered[] = $coordinate;
                continue;
            }

            if ($target['type'] === 'central' && $this->isCentralOrSharedContext($contextPath)) {
                $filtered[] = $coordinate;
                continue;
            }

            if ($target['type'] === 'tenant' && is_string($target['id']) && $this->isTenantScopeContext($contextPath, $target['id'])) {
                $filtered[] = $coordinate;
            }
        }

        return array_values(array_unique($filtered));
    }

    private function extractContextPath(string $coordinate): string
    {
        if (!str_contains($coordinate, ':')) {
            return '';
        }

        [, $contextAndTarget] = explode(':', $coordinate, 2);
        $lastSlash = strrpos($contextAndTarget, '/');

        if ($lastSlash === false) {
            return '';
        }

        return $this->normalizePath(substr($contextAndTarget, 0, $lastSlash));
    }

    private function normalizePath(string $value): string
    {
        $value = str_replace('\\', '/', trim($value));
        $value = preg_replace('#/+#', '/', $value) ?? $value;

        return strtolower(trim($value, '/'));
    }

    private function isCentralOrSharedContext(string $contextPath): bool
    {
        return $contextPath === 'central'
            || str_starts_with($contextPath, 'central/')
            || $contextPath === 'shared'
            || str_starts_with($contextPath, 'shared/');
    }

    /**
     * Verifica si una ruta contextual pertenece a un tenant específico.
     *
     * Arquitectura v3.5.0:
     *   - Compara directamente con el 'id' del tenant (sin normalización)
     *
     * @param string $contextPath  Ruta normalizada (ej: 'tenant/clinic-one')
     * @param string $tenantId     ID del tenant desde contexts.json (ej: 'clinic-one')
     * @return bool
     */
    private function isTenantScopeContext(string $contextPath, string $tenantId): bool
    {
        // migraciones/seeders compartidos por todos los tenants
        if ($contextPath === 'shared' || str_starts_with($contextPath, 'shared/')) {
            return true;
        }

        if ($contextPath === 'tenant/shared' || str_starts_with($contextPath, 'tenant/shared/')) {
            return true;
        }

        // tenant específico: debe coincidir exactamente con el ID
        if (!str_starts_with($contextPath, 'tenant/')) {
            return false;
        }

        $parts = explode('/', $contextPath);
        if (count($parts) < 2) {
            return false;
        }

        return $parts[1] === $tenantId;
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
