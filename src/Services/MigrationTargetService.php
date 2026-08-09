<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Exceptions\ConnectionNotConfiguredException;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Throwable;

class MigrationTargetService
{
    /**
     * Resuelve y valida la conexión contra la que ejecutar, a partir del **contexto**.
     *
     * **Antes el contexto se derivaba del nombre de un archivo JSON** —`tenant-shared.order.json` →
     * `tenant-shared`—, así que la base de datos donde se escribía dependía de cómo se llamara un
     * manifiesto. Renombrarlo, o pasar `--manifest` con otro, ejecutaba contra otra base sin que
     * nada lo advirtiera. Ahora lo dicen **la coordenada y el modo**: la coordenada lleva encima su
     * carpeta de contexto, que es el dato de verdad, y el modo decide si a ese contexto se le puede
     * exigir una conexión propia.
     *
     * Cadena de validaciones:
     *   1. El contexto existe en contexts.json (por carpeta, y si no, por id)
     *   2. El modo — un tenant de los que comparten funcionalidad no declara conexión
     *   3. tenancy_strategy === 'manual'
     *   4. connection_key no vacío
     *   5. La conexión existe en config/database.php (solo si !$dryRun)
     *
     * @param  string  $context  Carpeta ('Central', 'Tenant/Shared') o id ('central', 'tenant-one')
     *
     * @throws \InvalidArgumentException Si el contexto, la strategy o el connection_key fallan
     * @throws ConnectionNotConfiguredException Si la conexión no existe en config/database.php
     */
    public function resolveExecutionConnection(string $context, bool $dryRun = false): string
    {
        $contextId = trim($context);
        $context   = $this->findContext($contextId);

        // El modo decide ANTES que el contexto — es la corrección de R7 que dejó anotada la fase 1.
        //
        // En `multitenant-shared` los tenants comparten funcionalidad y **ninguno declara conexión**:
        // la conmuta la tenancy al identificar al inquilino, y nombrarla en el contexto ataría el
        // módulo a uno solo. Exigirle `connection_key` y `tenancy_strategy='manual'` sin mirar el
        // modo dejaba a ese modo entero **sin poder migrar**: la funcionalidad existía y no había
        // forma de desplegarla.
        //
        // Con la conexión ya conmutada, la ejecución va sobre la activa, que es lo que ese modo
        // necesita. La app central no entra por aquí: esa sí declara la suya siempre.
        if (! ModuleMode::current()->requiresTenantConnectionKey() && $this->isTenantContext($context)) {
            return (string) config('database.default');
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

    /**
     * La **carpeta** de un contexto — `central` → `Central`, `tenant-one` → `Tenant/TenantOne`.
     *
     * Es el dato con el que se filtran las migraciones de los traits, y sale del mismo sitio que la
     * conexión: el contexto declarado. Traducirlo aparte en el comando —un `match` con los nombres
     * conocidos— sería un segundo cálculo del mismo dato, y el día que alguien llame a su contexto de
     * otra manera uno de los dos se queda atrás. Es la familia de defecto que este paquete lleva doce
     * apariciones persiguiendo.
     *
     * @throws \InvalidArgumentException Si no hay ningún contexto que se llame así
     */
    public function folderOf(string $context): string
    {
        return (string) ($this->findContext(trim($context))['folder'] ?? '');
    }

    /**
     * El contexto, buscado primero por su **carpeta** y después por su id.
     *
     * Por carpeta primero porque es lo que traen encima las coordenadas y las rutas del proyecto;
     * por id después para que quien ya lo tenía a mano —una prueba, un script— siga funcionando.
     *
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException Si no hay ningún contexto que se llame así
     */
    private function findContext(string $contextId): array
    {
        if ($contextId === '') {
            throw new \InvalidArgumentException(
                'Falta el contexto contra el que ejecutar. Sale de la coordenada —'
                . "'Modulo:Central/archivo.php'— o de la opción --context."
            );
        }

        $porCarpeta = ContextResolver::findByFolder($contextId);

        if ($porCarpeta !== null) {
            return $porCarpeta;
        }

        try {
            return ContextResolver::find($contextId);
        } catch (\Throwable) {
            throw new \InvalidArgumentException(
                "No se encontró el contexto '{$contextId}' en contexts.json.\n"
                . 'Se buscó por carpeta y por id. Declara ese contexto, o corrige la coordenada.'
            );
        }
    }

    /**
     * ¿Este contexto es de tenant?
     *
     * Se mira la forma del contexto, no una lista de nombres: `contexts.json` lo declara con
     * `is_tenant`, y si no está, la carpeta lo dice (`Tenant/Shared`, `Tenant/Acme`). Una lista de
     * nombres conocidos envejecería en cuanto alguien llame a su contexto de otra manera.
     *
     * @param  array<string, mixed>  $context
     */
    private function isTenantContext(array $context): bool
    {
        if (array_key_exists('is_tenant', $context)) {
            return (bool) $context['is_tenant'];
        }

        $folder = (string) ($context['folder'] ?? '');

        return $folder === 'Tenant' || str_starts_with($folder, 'Tenant/');
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
     * La carpeta de contexto que lleva encima una coordenada.
     *
     * `Invoice:Central/2026_01_01_crea_facturas.php` → `central`. Es de donde sale ahora la base de
     * datos contra la que se ejecuta, en vez del nombre de un manifiesto.
     */
    public function extractContextPath(string $coordinate): string
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
}
