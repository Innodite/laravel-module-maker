<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Exceptions\ContextNotFoundException;

/**
 * Resuelve la configuración de contextos del proyecto desde contexts.json.
 *
 * Arquitectura v3.5.0 - Búsqueda Híbrida por ID:
 *   - central, shared, tenant_shared: Objetos directos (acceso O(1))
 *   - tenant: Array de objetos (búsqueda con Collection->firstWhere)
 *
 * Prioridad de resolución:
 *   1. module-maker-config/contexts.json (project root)
 *   2. Template del paquete (stubs/contexts.json)
 */
class ContextResolver
{
    /**
     * Cache del archivo contexts.json completo.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $data = null;

    /**
     * Retorna un contexto por su ID usando búsqueda híbrida.
     *
     * Para contextos de objeto único (central, shared, tenant_shared):
     *   - Acceso directo O(1)
     *
     * Para contexto tenant (array de tenants):
     *   - Búsqueda con Collection->firstWhere('id', $id)
     *
     * @param  string  $contextKey  Clave del contexto (central|shared|tenant_shared|tenant)
     * @param  string  $id          ID del contexto a buscar
     * @return array<string, mixed>
     *
     * @throws ContextNotFoundException Si el contexto o ID no existe
     */
    public static function resolveById(string $contextKey, string $id): array
    {
        $all = self::all();

        if (!isset($all[$contextKey])) {
            throw ContextNotFoundException::contextKeyNotFound($contextKey, array_keys($all));
        }

        $context = $all[$contextKey];

        // Contextos de objeto único (central, shared, tenant_shared)
        if (is_array($context) && isset($context['id'])) {
            if ($context['id'] === $id) {
                return $context;
            }

            throw ContextNotFoundException::forId($contextKey, $id, [$context['id']]);
        }

        // Contexto tenant (array de objetos)
        if (is_array($context) && !isset($context['id'])) {
            $collection = collect($context);
            $found = $collection->firstWhere('id', $id);

            if ($found !== null) {
                return $found;
            }

            $availableIds = $collection->pluck('id')->filter()->toArray();
            throw ContextNotFoundException::forId($contextKey, $id, $availableIds);
        }

        throw ContextNotFoundException::contextKeyNotFound($contextKey, array_keys($all));
    }

    /**
     * Retorna el contexto único para claves de objeto (central, shared, tenant_shared).
     *
     * @param  string  $contextKey  Clave del contexto
     * @return array<string, mixed>
     *
     * @throws ContextNotFoundException Si no existe o no es objeto único
     */
    public static function resolve(string $contextKey): array
    {
        $all = self::all();

        if (!isset($all[$contextKey])) {
            throw ContextNotFoundException::contextKeyNotFound($contextKey, array_keys($all));
        }

        $context = $all[$contextKey];

        if (is_array($context) && isset($context['id'])) {
            return $context;
        }

        throw new \InvalidArgumentException(
            "[ContextResolver] El contexto '{$contextKey}' no es un objeto único. " .
            "Usa resolveById() para contextos con múltiples items."
        );
    }

    /**
     * Busca un contexto por su campo 'id' en toda la estructura híbrida.
     *
     * Búsqueda Híbrida:
     *   - central, shared, tenant_shared: acceso directo por clave, verifica que 'id' coincide
     *   - tenant: itera el array con Collection->firstWhere('id', $id)
     *
     * @param  string  $id  ID del contexto a buscar (ej: 'central', 'tenant-one')
     * @return array<string, mixed>
     *
     * @throws ContextNotFoundException Si no se encuentra ningún contexto con ese ID
     */
    public static function find(string $id): array
    {
        $all = self::all();

        // 1. Acceso directo a contextos de objeto único (O(1))
        foreach (['central', 'shared', 'tenant_shared'] as $key) {
            if (isset($all[$key]) && is_array($all[$key]) && ($all[$key]['id'] ?? null) === $id) {
                return $all[$key];
            }
        }

        // 2. Búsqueda por iteración en el array de tenants
        $tenants = $all['tenant'] ?? [];
        if (is_array($tenants) && !isset($tenants['id'])) {
            $found = collect($tenants)->firstWhere('id', $id);
            if ($found !== null) {
                return $found;
            }
        }

        // 3. No encontrado → excepción descriptiva
        $available = collect(self::allItems())->pluck('id')->filter()->values()->toArray();
        throw ContextNotFoundException::forId('*', $id, $available);
    }

    /**
     * Retorna un contexto por su CARPETA — 'Central', 'Tenant/Shared', 'Tenant/Acme'.
     *
     * Existe porque la carpeta es el dato que llevan encima las cosas del proyecto: una coordenada
     * de migración (`Invoice:Central/2026_…php`) nombra la carpeta, no el id. Buscar por id obligaba
     * a derivarlo del nombre de un archivo, que fue exactamente de donde salía el contexto cuando lo
     * decidía el manifiesto JSON.
     *
     * La comparación ignora mayúsculas y barras sobrantes: la carpeta se escribe `Tenant/Shared` en
     * `contexts.json` y llega `tenant/shared` desde una coordenada normalizada.
     *
     * @return array<string, mixed>|null
     */
    public static function findByFolder(string $folder): ?array
    {
        $buscada = strtolower(trim(str_replace('\\', '/', $folder), '/'));

        if ($buscada === '') {
            return null;
        }

        foreach (self::allItems() as $item) {
            $suya = strtolower(trim(str_replace('\\', '/', (string) ($item['folder'] ?? '')), '/'));

            if ($suya !== '' && $suya === $buscada) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Retorna un tenant por su ID. Alias de resolveById('tenant', $id).
     *
     * @param  string  $id  ID del tenant
     * @return array<string, mixed>
     *
     * @throws ContextNotFoundException Si el tenant no existe
     */
    public static function resolveTenant(string $id): array
    {
        return self::resolveById('tenant', $id);
    }

    /**
     * Retorna todos los contextos arquitectónicos.
     *
     * @return array<string, array|array<int, array>>
     */
    public static function all(): array
    {
        return self::load()['contexts'] ?? [];
    }

    /**
     * Retorna el array de tenants del proyecto.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allTenants(): array
    {
        $all = self::all();
        $tenants = $all['tenant'] ?? [];

        return is_array($tenants) && !isset($tenants['id']) ? $tenants : [];
    }

    /**
     * Retorna TODOS los contextos (central, shared, tenant_shared + todos los tenants)
     * en un array plano para iteración.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allItems(): array
    {
        $all = self::all();
        $items = [];

        foreach ($all as $key => $value) {
            // Contexto de objeto único
            if (is_array($value) && isset($value['id'])) {
                $items[] = $value;
                continue;
            }

            // Contexto tenant (array de objetos)
            if ($key === 'tenant' && is_array($value)) {
                foreach ($value as $tenant) {
                    if (is_array($tenant) && isset($tenant['id'])) {
                        $items[] = $tenant;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Carga el archivo contexts.json.
     *
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = self::resolvePath();

        if (!File::exists($path)) {
            throw new \RuntimeException("[ContextResolver] No se encontró contexts.json en: {$path}");
        }

        $contents = File::get($path);
        $data = json_decode($contents, false); // false = retorna objetos stdClass

        if (!is_object($data) || !isset($data->contexts)) {
            throw new \RuntimeException("[ContextResolver] El archivo contexts.json no tiene estructura válida.");
        }

        // Convertir el objeto principal a array, pero mantener la estructura híbrida interna
        self::$data = json_decode($contents, true);
        return self::$data;
    }

    /**
     * Resuelve la ruta del archivo contexts.json.
     *
     * Prioridad:
     *   1. module-maker-config/contexts.json (project root)
     *   2. Template del paquete (stubs/contexts.json)
     *
     * @return string
     */
    private static function resolvePath(): string
    {
        $projectPath = config('make-module.contexts_path');
        if ($projectPath && File::exists($projectPath)) {
            return $projectPath;
        }

        return __DIR__ . '/../../stubs/contexts.json';
    }

    /**
     * Limpia el cache. Útil en tests.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$data = null;
    }
}
