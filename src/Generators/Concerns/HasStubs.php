<?php

namespace Innodite\LaravelModuleMaker\Generators\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Trait HasStubs — v3.1.0
 *
 * Resuelve stubs en orden de prioridad (de más específico a más genérico):
 *   1. {config_path}/stubs/contextual/{ContextFolder}/{stub}  — custom del proyecto, por contexto
 *   2. {config_path}/stubs/contextual/{stub}                  — custom del proyecto, genérico
 *   3. package/stubs/contextual/{ContextFolder}/{stub}         — paquete, por contexto
 *   4. package/stubs/contextual/{stub}                         — paquete, genérico (fallback)
 *
 * El ContextFolder se pasa como $contextFolder (ej: "Central", "Tenant/Shared").
 * $isClean y $context se mantienen por compatibilidad de firma.
 */
trait HasStubs
{
    /**
     * Obtiene el contenido del stub y reemplaza los marcadores de posición.
     *
     * @param  string       $stubFile      Nombre del archivo stub (ej: 'model.stub')
     * @param  bool         $isClean       Mantenido por compatibilidad
     * @param  array        $placeholders  Mapa de marcadores → valores
     * @param  string|null  $context       Clave de contexto (ej: 'central') — usado internamente
     * @return string
     */
    protected function getStubContent(string $stubFile, bool $isClean, array $placeholders = [], ?string $context = null): string
    {
        $stub = $this->getStub($stubFile, $isClean, $context);
        return $this->replacePlaceholders($stub, $placeholders);
    }

    /**
     * Obtiene el contenido puro del archivo stub.
     *
     * @throws \Exception Si el archivo stub no se encuentra en ninguna ubicación
     */
    protected function getStub(string $stubFile, bool $isClean, ?string $context = null): string
    {
        $stubPath = $this->getStubPath($stubFile, $isClean, $context);

        if (!File::exists($stubPath)) {
            throw new \Exception(
                "El archivo stub '{$stubFile}' no se encuentra.\n" .
                "Buscado en: {$stubPath}\n" .
                "Ejecuta 'php artisan innodite:module-setup' para publicar los stubs."
            );
        }

        return File::get($stubPath);
    }

    /**
     * Resuelve la ruta completa del archivo stub con resolución por carpeta de contexto.
     *
     * Orden de prioridad:
     *   1. custom/{ContextFolder}/{stub}  — proyecto, específico por contexto
     *   2. custom/{stub}                  — proyecto, genérico
     *   3. package/{ContextFolder}/{stub} — paquete, específico por contexto
     *   4. package/{stub}                 — paquete, genérico (fallback final)
     *
     * @param  string       $stubFile       Nombre del archivo stub
     * @param  bool         $isClean        Mantenido por compatibilidad
     * @param  string|null  $contextFolder  Carpeta del contexto (ej: "Central", "Tenant/Shared")
     * @return string
     */
    protected function getStubPath(string $stubFile, bool $isClean, ?string $contextFolder = null): string
    {
        $customBase  = config('make-module.stubs.path') . '/contextual';
        $packageBase = __DIR__ . '/../../../stubs/contextual';

        $folder = $this->normalizeContextFolder($contextFolder);

        if ($folder) {
            // 1. Custom del proyecto, específico por contexto
            $p = "{$customBase}/{$folder}/{$stubFile}";
            if (File::exists($p)) return $p;

            // 2. Paquete, específico por contexto
            $p = "{$packageBase}/{$folder}/{$stubFile}";
            if (File::exists($p)) return $p;
        }

        // 3. Custom del proyecto, genérico
        $p = "{$customBase}/{$stubFile}";
        if (File::exists($p)) return $p;

        // 4. Paquete, genérico (fallback final)
        return "{$packageBase}/{$stubFile}";
    }

    /**
     * Normaliza la carpeta de contexto para usarla en la ruta de stubs.
     * Mapea claves de contexto ("central") o carpetas ("Tenant/Shared") al nombre de carpeta de stubs.
     *
     * @param  string|null  $context  Clave de contexto o carpeta de contexto
     * @return string|null  Nombre de carpeta de stubs (ej: "Central", "TenantShared", "TenantName")
     */
    private function normalizeContextFolder(?string $context): ?string
    {
        if (!$context) return null;

        // Si ya es una carpeta con slash (ej: "Tenant/Shared"), extraer el nombre de carpeta de stubs
        $map = [
            'central'       => 'Central',
            'shared'        => 'Shared',
            'tenant_shared' => 'TenantShared',
            'tenant'        => 'TenantName',
            // Carpetas directas
            'Central'         => 'Central',
            'Shared'          => 'Shared',
            'Tenant/Shared'   => 'TenantShared',
        ];

        if (isset($map[$context])) {
            return $map[$context];
        }

        // Tenants específicos (ej: "Tenant/INNODITE") → TenantName
        if (str_starts_with($context, 'Tenant/') && $context !== 'Tenant/Shared') {
            return 'TenantName';
        }

        return null;
    }

    /**
     * Reemplaza los marcadores de posición {{ key }} en el contenido del stub.
     *
     * @param  string  $stub          Contenido del stub
     * @param  array   $placeholders  Mapa de marcadores → valores (sin llaves)
     * @return string
     */
    protected function replacePlaceholders(string $stub, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $stub = str_replace("{{ {$key} }}", $value, $stub);
        }
        return $stub;
    }
}
