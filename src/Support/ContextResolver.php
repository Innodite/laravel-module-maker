<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;

/**
 * Resuelve la configuración de contextos del proyecto desde contexts.json.
 *
 * Estructura del archivo (v3.0.0):
 *   contexts → objeto con claves (central, shared, tenant_shared, tenant)
 *              cada clave contiene un ARRAY de sub-contextos con campo 'name'
 *
 * Prioridad de resolución:
 *   1. module-maker-config/contexts.json (project root — publicado por innodite:module-setup)
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
                "Edita module-maker-config/contexts.json."
            );
        }

        return $items[0];
    }

    /**
     * Retorna un sub-contexto específico buscándolo por su campo 'name'.
     *
     * @param  string  $contextKey  Clave del contexto (ej: 'tenant', 'shared')
     * @param  string  $name        Valor del campo 'name' del sub-contexto
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
            "Edita module-maker-config/contexts.json."
        );
    }

    /**
     * Retorna un tenant por su 'name'. Alias de resolveItem('tenant', $name).
     *
     * @param  string  $name  Valor del campo 'name' del tenant
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
     *
     * @return array<int, array>
     */
    public static function getSpecificTenants(): array
    {
        return self::allTenants();
    }

    /**
     * Retorna el archivo(s) de ruta definido para un contexto dado.
     * Puede ser un string ('web.php') o un array (['web.php', 'tenant.php']) para Shared.
     *
     * @param  string  $contextKey  Clave del contexto
     * @return string|array<int, string>
     */
    public static function getRouteFile(string $contextKey): string|array
    {
        $item = self::resolve($contextKey);
        return $item['route_file'] ?? 'web.php';
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
     *
     * Prioridad:
     *   1. module-maker-config/contexts.json (project root)
     *   2. Template del paquete (stubs/contexts.json)
     *
     * @return string  Ruta absoluta al contexts.json
     */
    private static function resolvePath(): string
    {
        // 1. Ruta publicada en el proyecto (project root — config configurable)
        $projectPath = config('make-module.contexts_path');
        if ($projectPath && File::exists($projectPath)) {
            return $projectPath;
        }

        // 2. Fallback: template del paquete
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
