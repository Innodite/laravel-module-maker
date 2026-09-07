<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Exceptions\ContextNotFoundException;

/**
 * Resuelve la configuración de contextos del proyecto desde contexts.json.
 *
 * **Cada contexto es un objeto, y se accede por su clave.** Hasta la 4.x el catálogo era híbrido:
 * `central`, `shared` y `tenant_shared` eran objetos, y `tenant` una LISTA de inquilinos nombrados
 * que había que recorrer. Esa lista es la que hacía que el modo de inquilinos iguales generara
 * `Tenant/TenantOne/` —el nombre del primer cliente del catálogo, en un modo que existe para que no
 * haya clientes nombrados— y la que hacía que un módulo declarara rutas hacia controladores que
 * nadie escribió.
 *
 * Ahora los contextos son dos: `central` y `tenant`. Un proyecto que necesite otro lo declara en su
 * propio catálogo, y como todos tienen la misma forma, declararlo no pide ningún caso especial.
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
     * Retorna un contexto por su clave, comprobando que su id es el esperado.
     *
     * @param  string  $contextKey  Clave del contexto (central|tenant, o la que declare el proyecto)
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

        if (is_array($context) && isset($context['id'])) {
            if ($context['id'] === $id) {
                return $context;
            }

            throw ContextNotFoundException::forId($contextKey, $id, [$context['id']]);
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
            "[ContextResolver] El contexto '{$contextKey}' no tiene la forma de un contexto: "
            . 'se esperaba un objeto con su `id`. Revisa module-maker-config/contexts.json.'
        );
    }

    /**
     * Busca un contexto por su campo 'id', mire la clave que mire.
     *
     * Recorre el catálogo entero en vez de consultar una lista de claves conocidas: esa lista era
     * lo que dejaba fuera cualquier contexto que declarase el proyecto y no estuviera escrito aquí.
     *
     * @param  string  $id  ID del contexto a buscar (ej: 'central', 'tenant')
     * @return array<string, mixed>
     *
     * @throws ContextNotFoundException Si no se encuentra ningún contexto con ese ID
     */
    public static function find(string $id): array
    {
        foreach (self::allItems() as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

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
     * Retorna todos los contextos arquitectónicos.
     *
     * @return array<string, array|array<int, array>>
     */
    public static function all(): array
    {
        return self::load()['contexts'] ?? [];
    }

    /**
     * Los inquilinos NOMBRADOS que declare el catálogo.
     *
     * ⚠️ Con el catálogo de fábrica esto devuelve **vacío**, y es lo correcto: `tenant` es un
     * contexto, no una lista de clientes. Sigue aquí porque un proyecto puede declarar su propia
     * lista, y porque el generador de rutas todavía la consulta — eso se cierra al reescribirlo.
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
     * Todos los contextos del catálogo, en un array plano para iterar.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allItems(): array
    {
        $items = [];

        foreach (self::all() as $value) {
            if (is_array($value) && isset($value['id'])) {
                $items[] = $value;
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
