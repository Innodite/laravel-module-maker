<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;

/**
 * Resuelve la configuración de contextos del proyecto desde contexts.json.
 *
 * Estructura del archivo:
 *   contexts → objeto con 4 claves (central, shared, tenant, tenant_shared)
 *              cada clave contiene un ARRAY de sub-contextos con campo 'name'
 *
 * Los tenants del proyecto están en contexts.tenant (no en una sección separada).
 * Si el proyecto no tiene contexts.json publicado, usa el template del paquete.
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
     * Retorna el primer sub-contexto de una clave de contexto.
     * Útil para contextos con un único item (ej: central, tenant_shared).
     *
     * @param  string  $contextKey  Clave del contexto (ej: 'central', 'tenant_shared')
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException Si el contexto no existe o está vacío
     */
    public static function resolve(string $contextKey): array
    {
        $items = self::allContextItems($contextKey);

        if (empty($items)) {
            $available = implode(', ', array_keys(self::all()));
            throw new \InvalidArgumentException(
                "[ContextResolver] El contexto '{$contextKey}' no tiene items definidos en contexts.json.\n" .
                "Contextos disponibles: {$available}\n" .
                "Edita Modules/module-maker-config/contexts.json."
            );
        }

        return $items[0];
    }

    /**
     * Retorna un sub-contexto específico buscándolo por su campo 'name'.
     * Permite seleccionar una variante concreta cuando hay múltiples en el mismo contexto.
     *
     * @param  string  $contextKey  Clave del contexto (ej: 'tenant', 'shared')
     * @param  string  $name        Valor del campo 'name' del sub-contexto (ej: 'Energía España')
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException Si no se encuentra ningún item con ese name
     */
    public static function resolveItem(string $contextKey, string $name): array
    {
        foreach (self::allContextItems($contextKey) as $item) {
            if (($item['name'] ?? '') === $name) {
                return $item;
            }
        }

        $available = implode(', ', array_map(fn ($i) => $i['name'] ?? '?', self::allContextItems($contextKey)));
        throw new \InvalidArgumentException(
            "[ContextResolver] No se encontró el item '{$name}' en el contexto '{$contextKey}'.\n" .
            "Items disponibles: {$available}\n" .
            "Edita Modules/module-maker-config/contexts.json."
        );
    }

    /**
     * Retorna un tenant por su 'name'. Alias de resolveItem('tenant', $name).
     *
     * @param  string  $name  Valor del campo 'name' del tenant (ej: 'Energía España')
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException Si el tenant no existe
     */
    public static function resolveTenant(string $name): array
    {
        return self::resolveItem('tenant', $name);
    }

    /**
     * Retorna todos los contextos arquitectónicos.
     * Formato: { "central": [...], "shared": [...], "tenant": [...], "tenant_shared": [...] }
     *
     * @return array<string, array>
     */
    public static function all(): array
    {
        return self::load()['contexts'] ?? [];
    }

    /**
     * Retorna el array de sub-contextos para una clave de contexto específica.
     *
     * @param  string  $contextKey  Clave del contexto (ej: 'tenant', 'shared')
     * @return array<int, array>
     */
    public static function allContextItems(string $contextKey): array
    {
        return self::all()[$contextKey] ?? [];
    }

    /**
     * Retorna todos los tenants del proyecto (items del contexto 'tenant').
     *
     * @return array<int, array>
     */
    public static function allTenants(): array
    {
        return self::allContextItems('tenant');
    }

    /**
     * Retorna TODOS los sub-contextos de TODOS los contextos en un array plano.
     * Útil para RendersInertiaModule que necesita todos los prefijos.
     *
     * @return array<int, array>
     */
    public static function allItems(): array
    {
        $items = [];
        foreach (self::all() as $contextItems) {
            foreach ($contextItems as $item) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Retorna los tenants específicos. Alias de allTenants().
     * Mantenido para compatibilidad con RouteGenerator.
     *
     * @return array<int, array>
     */
    public static function getSpecificTenants(): array
    {
        return self::allTenants();
    }

    /**
     * Carga el archivo contexts.json. Prioriza el del proyecto sobre el del paquete.
     * Usa cache estático para no releer el archivo durante la misma ejecución.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException Si el JSON es inválido
     */
    private static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = self::resolvePath();
        $json = File::get($path);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "[ContextResolver] Error al parsear contexts.json: " . json_last_error_msg() .
                "\nRuta: {$path}"
            );
        }

        self::$data = $data;
        return self::$data;
    }

    /**
     * Resuelve la ruta del archivo contexts.json.
     * Prioridad: proyecto (publicado por setup) → default_config_path → template del paquete.
     *
     * @return string  Ruta absoluta al contexts.json
     */
    private static function resolvePath(): string
    {
        $projectPath = config('make-module.contexts_path');
        if ($projectPath && File::exists($projectPath)) {
            return $projectPath;
        }

        $defaultPath = config('make-module.default_config_path') . '/contexts.json';
        if (File::exists($defaultPath)) {
            return $defaultPath;
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
