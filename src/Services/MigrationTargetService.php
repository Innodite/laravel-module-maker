<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Exceptions\ConnectionNotConfiguredException;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Throwable;

class MigrationTargetService
{
    public function ensureManifestPath(string $manifestName): string
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
     * Resuelve y valida la conexión de ejecución para un manifest.
     *
     * Cadena de validaciones:
     *   1. Formato del manifest → /^([a-z0-9][a-z0-9-]*)\.order\.json$/
     *   2. Contexto existe en contexts.json
     *   3. tenancy_strategy === 'manual'
     *   4. connection_key no vacío
     *   5. Conexión existe en config/database.php (solo si !$dryRun)
     *
     * @throws \InvalidArgumentException Si el formato, contexto, strategy o connection_key fallan
     * @throws ConnectionNotConfiguredException Si la conexión no existe en config/database.php
     */
    public function resolveExecutionConnection(string $manifestPath, bool $dryRun = false): string
    {
        $manifestName = strtolower(basename($manifestPath));

        if (!preg_match('/^([a-z0-9][a-z0-9-]*)\.order\.json$/', $manifestName, $matches)) {
            throw new \InvalidArgumentException(
                "El nombre del manifest '{$manifestName}' no tiene el formato esperado '{id}.order.json'."
            );
        }

        $contextId = $matches[1];

        try {
            $context = ContextResolver::find($contextId);
        } catch (\Throwable) {
            throw new \InvalidArgumentException(
                "No se encontró el contexto '{$contextId}' en contexts.json. " .
                "Verifica que el manifest corresponda a un contexto registrado."
            );
        }

        $tenancyStrategy = $context['tenancy_strategy'] ?? null;
        if ($tenancyStrategy !== 'manual') {
            throw new \InvalidArgumentException(
                "El contexto '{$contextId}' tiene tenancy_strategy='{$tenancyStrategy}'. " .
                "Solo se permite ejecutar migraciones con tenancy_strategy='manual'."
            );
        }

        $connectionKey = $context['connection_key'] ?? null;
        if ($connectionKey === null || $connectionKey === '') {
            throw new \InvalidArgumentException(
                "El contexto '{$contextId}' no tiene un connection_key definido. " .
                "Agrega un connection_key válido en contexts.json antes de ejecutar migraciones."
            );
        }

        if (!$dryRun && !is_array(config("database.connections.{$connectionKey}"))) {
            throw ConnectionNotConfiguredException::forContext($contextId, $connectionKey);
        }

        return $connectionKey;
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
     * Resuelve targets de manifiestos para una coordenada dada.
     *
     * Arquitectura v3.5.0:
     *   - central_order.json: Central + Shared
     *   - {id}.order.json: Uno por tenant usando su campo 'id'
     *
     * @return array<int, array{manifest: string, type: string, id: string|null, label: string}>
     */
    public function resolveTargetsForCoordinate(string $coordinate, ?string $manifestOption = null): array
    {
        $manifestOption = trim((string) $manifestOption);

        if ($manifestOption !== '') {
            return [[
                'manifest' => $manifestOption,
                'type' => 'custom',
                'id' => null,
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

        $tenantId = $this->resolveTenantIdFromContextPath($contextPath);
        if ($tenantId === '') {
            return [];
        }

        return array_values(array_filter($targets, fn (array $target): bool => $target['type'] === 'tenant' && ($target['id'] === $tenantId)));
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
     * Resuelve manifiestos detectados automáticamente desde contexts.json.
     *
     * Arquitectura v3.5.0+:
     *   - central.order.json: contiene Central + Shared
     *   - {id}.order.json: uno por tenant usando su campo 'id'
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

    /**
     * Extrae el ID del tenant desde un contextPath (ej: 'tenant/clinic-one' → 'clinic-one').
     *
     * @param string $contextPath  Ruta normalizada del contexto
     * @return string  ID del tenant o cadena vacía
     */
    private function resolveTenantIdFromContextPath(string $contextPath): string
    {
        $parts = explode('/', $contextPath);

        if (count($parts) < 2 || $parts[0] !== 'tenant') {
            return '';
        }

        return $parts[1];
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