<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;

/**
 * Resuelve la configuración de contextos y tenants del proyecto.
 *
 * Lee contexts.json con la estructura:
 *   contexts → 4 contextos arquitectónicos (central, shared, tenant, tenant_shared)
 *   tenants  → tenants específicos del proyecto (instancias del contexto 'tenant')
 *
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
     * Retorna la configuración de un contexto arquitectónico por su clave.
     * Contextos válidos: central, shared, tenant, tenant_shared.
     *
     * @param  string  $contextKey  Clave del contexto (ej: 'central', 'tenant_shared')
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException Si el contexto no existe
     */
    public static function resolve(string $contextKey): array
    {
        $contexts = self::load()['contexts'] ?? [];

        if (! isset($contexts[$contextKey])) {
            $available = implode(', ', array_keys($contexts));
            throw new \InvalidArgumentException(
                "[ContextResolver] El contexto '{$contextKey}' no está definido en contexts.json.\n" .
                "Contextos disponibles: {$available}\n" .
                "Edita Modules/module-maker-config/contexts.json para agregar o modificar contextos."
            );
        }

        return $contexts[$contextKey];
    }

    /**
     * Retorna la configuración de un tenant específico por su clave.
     * Los tenants son instancias del contexto 'tenant' (ej: energy_spain, telephony_spain).
     *
     * @param  string  $tenantKey  Clave del tenant (ej: 'energy_spain', 'telephony_peru')
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException Si el tenant no existe
     */
    public static function resolveTenant(string $tenantKey): array
    {
        $tenants = self::load()['tenants'] ?? [];

        if (! isset($tenants[$tenantKey])) {
            $available = implode(', ', array_keys($tenants));
            throw new \InvalidArgumentException(
                "[ContextResolver] El tenant '{$tenantKey}' no está definido en contexts.json.\n" .
                "Tenants disponibles: {$available}\n" .
                "Agrega el tenant en la sección 'tenants' de Modules/module-maker-config/contexts.json."
            );
        }

        return $tenants[$tenantKey];
    }

    /**
     * Retorna todos los contextos arquitectónicos definidos.
     * Siempre son exactamente 4: central, shared, tenant, tenant_shared.
     *
     * @return array<string, array>
     */
    public static function all(): array
    {
        return self::load()['contexts'] ?? [];
    }

    /**
     * Retorna todos los tenants específicos del proyecto.
     *
     * @return array<string, array>
     */
    public static function allTenants(): array
    {
        return self::load()['tenants'] ?? [];
    }

    /**
     * Retorna los tenants específicos. Usado por RouteGenerator para tenant_shared.
     *
     * @return array<string, array>
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
