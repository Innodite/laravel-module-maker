<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class MigrationTargetService
{
    public function ensureManifestPath(string $manifestName): string
    {
        $manifest = trim($manifestName);

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
     * @param array<int, string> $migrations
     * @param array<int, string> $seeders
     */
    public function resolveExecutionConnection(string $manifestPath, array $migrations, array $seeders): string
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

    public function resolveDatabaseName(string $connectionName): string
    {
        $connection = config("database.connections.{$connectionName}");

        if (!is_array($connection)) {
            return '';
        }

        return (string) ($connection['database'] ?? '');
    }

    public function validateDatabaseExists(string $connectionName): ?string
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

    /**
     * @return array<int, array{manifest: string, type: string, slug: string|null, label: string}>
     */
    public function resolveTargetsForCoordinate(string $coordinate, ?string $manifestOption = null): array
    {
        $manifestOption = trim((string) $manifestOption);

        if ($manifestOption !== '') {
            return [[
                'manifest' => $manifestOption,
                'type' => 'custom',
                'slug' => null,
                'label' => 'custom',
            ]];
        }

        $contextPath = $this->extractContextPath($coordinate);
        if ($contextPath === '') {
            return [];
        }

        $targets = $this->resolveAutoTargets();

        if ($this->isCentralContext($contextPath)) {
            return array_values(array_filter($targets, static fn (array $target): bool => $target['type'] === 'central'));
        }

        if ($this->isSharedContext($contextPath)) {
            return $targets;
        }

        if ($this->isTenantSharedContext($contextPath)) {
            return array_values(array_filter($targets, static fn (array $target): bool => $target['type'] === 'tenant'));
        }

        $tenantSlug = $this->resolveTenantSlugFromContextPath($contextPath);
        if ($tenantSlug === '') {
            return [];
        }

        return array_values(array_filter($targets, fn (array $target): bool => $target['type'] === 'tenant' && $this->normalizeSlug((string) $target['slug']) === $tenantSlug));
    }

    /**
     * @param array{migrations: array<int, string>, seeders: array<int, string>} $plan
     */
    public function addCoordinateIfMissing(array &$plan, string $section, string $coordinate): bool
    {
        if (!isset($plan[$section]) || !is_array($plan[$section])) {
            $plan[$section] = [];
        }

        if (in_array($coordinate, $plan[$section], true)) {
            return false;
        }

        $plan[$section][] = $coordinate;
        $plan[$section] = array_values(array_unique($plan[$section]));

        return true;
    }

    /**
     * @return array<int, array{manifest: string, type: string, slug: string|null, label: string}>
     */
    private function resolveAutoTargets(): array
    {
        $targets = [[
            'manifest' => 'central_order.json',
            'type' => 'central',
            'slug' => null,
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

            $slug = $this->resolveTenantSlug($tenant);
            if ($slug === '') {
                continue;
            }

            $targets[] = [
                'manifest' => "tenant_{$slug}_order.json",
                'type' => 'tenant',
                'slug' => $slug,
                'label' => "tenant:{$slug}",
            ];
        }

        $unique = [];
        foreach ($targets as $target) {
            $unique[$target['manifest']] = $target;
        }

        return array_values($unique);
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

    private function resolveTenantSlug(array $tenant): string
    {
        $candidates = [
            (string) ($tenant['permission_prefix'] ?? ''),
            (string) ($tenant['route_prefix'] ?? ''),
            (string) ($tenant['folder'] ?? ''),
            (string) ($tenant['class_prefix'] ?? ''),
            (string) ($tenant['name'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $slug = $this->normalizeSlug($candidate);
            if ($slug !== '' && $slug !== 'tenant') {
                return $slug;
            }
        }

        return '';
    }

    private function resolveTenantSlugFromContextPath(string $contextPath): string
    {
        $parts = explode('/', $contextPath);

        if (count($parts) < 2 || $parts[0] !== 'tenant') {
            return '';
        }

        return $this->normalizeSlug($parts[1]);
    }

    private function normalizeSlug(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '/')) {
            $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $value))));
            if (!empty($parts)) {
                $value = (string) end($parts);
            }
        }

        $value = Str::snake($value);
        $ascii = strtolower(Str::ascii($value));
        $ascii = preg_replace('/[^a-z0-9]+/', '_', $ascii) ?? '';

        return trim($ascii, '_');
    }

    private function normalizePath(string $value): string
    {
        $value = str_replace('\\', '/', trim($value));
        $value = preg_replace('#/+#', '/', $value) ?? $value;

        return strtolower(trim($value, '/'));
    }

    private function isCentralContext(string $contextPath): bool
    {
        return $contextPath === 'central' || str_starts_with($contextPath, 'central/');
    }

    private function isSharedContext(string $contextPath): bool
    {
        return $contextPath === 'shared' || str_starts_with($contextPath, 'shared/');
    }

    private function isTenantSharedContext(string $contextPath): bool
    {
        return $contextPath === 'tenant/shared' || str_starts_with($contextPath, 'tenant/shared/');
    }
}