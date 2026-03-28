<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;

/**
 * Resuelve la configuración de contexto para el generador de módulos.
 *
 * Lee el archivo contexts.json del proyecto (publicado por innodite:setup)
 * y expone la información de cada contexto: carpeta, prefijo de clase,
 * namespace, prefijo de ruta, middleware de permisos y de rutas.
 *
 * Si el proyecto no tiene contexts.json publicado, usa el template
 * incluido en el paquete como fallback.
 */
class ContextResolver
{
    /**
     * Cache de contextos cargados para no releer el archivo en cada llamada.
     *
     * @var array<string, array>|null
     */
    private static ?array $contexts = null;

    /**
     * Retorna la configuración completa de un contexto por su clave.
     * Lanza una excepción si el contexto no existe en el archivo de configuración.
     *
     * @param  string  $contextKey  Clave del contexto (ej: 'central', 'energy_spain', 'tenant_shared')
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException Si el contexto no está definido en contexts.json
     */
    public static function resolve(string $contextKey): array
    {
        $contexts = self::load();

        if (! isset($contexts[$contextKey])) {
            $available = implode(', ', array_filter(array_keys($contexts), fn ($k) => ! str_starts_with($k, '_')));
            throw new \InvalidArgumentException(
                "[ContextResolver] El contexto '{$contextKey}' no está definido en contexts.json.\n" .
                "Contextos disponibles: {$available}\n" .
                "Para agregar un contexto nuevo, edita Modules/module-maker-config/contexts.json."
            );
        }

        return $contexts[$contextKey];
    }

    /**
     * Retorna todos los contextos que tienen generates_routes_for_all_tenants = false
     * y is_tenant = true. Usado por RouteGenerator para saber a qué tenants
     * generar rutas cuando el contexto es tenant_shared.
     *
     * @return array<string, array>
     */
    public static function getSpecificTenants(): array
    {
        $contexts = self::load();

        return array_filter(
            $contexts,
            fn ($key, $ctx) => ! str_starts_with($key, '_')
                && ($ctx['is_tenant'] ?? false) === true
                && ($ctx['generates_routes_for_all_tenants'] ?? false) === false,
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Retorna todos los contextos definidos, excluyendo las claves de metadata (_readme, etc.).
     *
     * @return array<string, array>
     */
    public static function all(): array
    {
        return array_filter(
            self::load(),
            fn ($key) => ! str_starts_with($key, '_'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Carga el archivo contexts.json. Prioriza el del proyecto sobre el del paquete.
     * Usa cache estático para no releer el archivo en cada llamada durante la misma ejecución.
     *
     * @return array<string, array>
     *
     * @throws \RuntimeException Si el JSON es inválido
     */
    private static function load(): array
    {
        if (self::$contexts !== null) {
            return self::$contexts;
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

        self::$contexts = $data;
        return self::$contexts;
    }

    /**
     * Resuelve la ruta del archivo contexts.json.
     * Busca primero en el proyecto (publicado por setup), luego usa el template del paquete.
     *
     * @return string  Ruta absoluta al archivo contexts.json
     */
    private static function resolvePath(): string
    {
        // Prioridad 1 — publicado por el proyecto via innodite:setup
        $projectPath = config('make-module.contexts_path');
        if ($projectPath && File::exists($projectPath)) {
            return $projectPath;
        }

        // Prioridad 2 — ubicación por defecto del setup
        $defaultPath = config('make-module.default_config_path') . '/contexts.json';
        if (File::exists($defaultPath)) {
            return $defaultPath;
        }

        // Fallback — template incluido en el paquete
        return __DIR__ . '/../../stubs/contexts.json';
    }

    /**
     * Limpia el cache de contextos. Útil en tests.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$contexts = null;
    }
}
