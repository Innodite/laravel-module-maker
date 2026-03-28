<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Traits;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Innodite\LaravelModuleMaker\Support\ContextResolver;

/**
 * Trait de resolución explícita de vistas Inertia basada en la convención de nombres.
 *
 * El prefijo del nombre del componente determina la carpeta destino — sin cascadas
 * ni detección de contexto en tiempo de ejecución. Los prefijos y carpetas se leen
 * dinámicamente desde contexts.json del proyecto.
 *
 * Ejemplo de uso en un controller:
 *   return $this->renderModule('UserManagement', 'TenantEnergySpainUserList');
 *   return $this->renderModule('UserManagement', 'Users/TenantSharedUserList');
 *   return $this->renderModule('UserManagement', 'CentralUserList');
 */
trait RendersInertiaModule
{
    /**
     * Renderiza un componente Inertia resolviendo la vista según el prefijo del nombre del archivo.
     *
     * @param  string  $module     Nombre del módulo Laravel (ej: 'UserManagement')
     * @param  string  $component  Ruta relativa al componente dentro de Pages/, sin extensión
     *                             (ej: 'TenantEnergySpainUserList', 'Users/TenantSharedUserList')
     * @param  array   $data       Props a inyectar en el componente Vue
     * @return InertiaResponse
     *
     * @throws \RuntimeException Si el prefijo no es válido o el archivo .vue no existe
     */
    protected function renderModule(string $module, string $component, array $data = []): InertiaResponse
    {
        return Inertia::render($this->resolveView($module, $component), $data);
    }

    /**
     * Resuelve la ruta completa del componente Vue a partir del prefijo de su nombre.
     * El prefijo es la única fuente de verdad — no se detecta contexto en tiempo de ejecución.
     *
     * @param  string  $module     Nombre del módulo Laravel
     * @param  string  $component  Ruta relativa del componente (ej: 'Users/TenantEnergySpainUserList')
     * @return string              Ruta en formato 'Module::Folder/Filename' para Inertia
     *
     * @throws \RuntimeException Si el prefijo no coincide o el archivo no existe
     */
    private function resolveView(string $module, string $component): string
    {
        $parts     = explode('/', $component);
        $filename  = end($parts);
        $subfolder = implode('/', array_slice($parts, 0, -1));

        $baseFolder = $this->resolveBaseFolder($filename);

        $relativePath = $subfolder
            ? "{$baseFolder}/{$subfolder}/{$filename}"
            : "{$baseFolder}/{$filename}";

        $absolutePath = base_path("Modules/{$module}/resources/js/Pages/{$relativePath}.vue");

        if (! file_exists($absolutePath)) {
            throw new \RuntimeException(
                "[RendersInertiaModule] Archivo no encontrado: {$filename}.vue (módulo: {$module})\n\n" .
                "Ruta esperada según convención de nombres:\n" .
                "  Modules/{$module}/resources/js/Pages/{$relativePath}.vue\n\n" .
                "Verifica que:\n" .
                "  1. El archivo existe en la ruta indicada\n" .
                "  2. El prefijo del nombre coincide con la carpeta donde está el archivo\n"
            );
        }

        return "{$module}::{$relativePath}";
    }

    /**
     * Mapea el prefijo del nombre del archivo a la carpeta base dentro de Pages/.
     * El mapa se construye dinámicamente desde contexts.json del proyecto.
     * Los prefijos más largos se evalúan primero para evitar coincidencias parciales.
     *
     * @param  string  $filename  Nombre del archivo sin extensión (ej: 'TenantEnergySpainUserList')
     * @return string             Carpeta base relativa a Pages/ (ej: 'Tenant/EnergySpain')
     *
     * @throws \RuntimeException Si el nombre no comienza con ningún prefijo válido
     */
    private function resolveBaseFolder(string $filename): string
    {
        $map = $this->buildPrefixMap();

        foreach ($map as $prefix => $folder) {
            if (str_starts_with($filename, $prefix)) {
                return $folder;
            }
        }

        $validPrefixes = implode("\n  ", array_map(
            fn ($p, $f) => "{$p}* → Pages/{$f}/",
            array_keys($map),
            array_values($map)
        ));

        throw new \RuntimeException(
            "[RendersInertiaModule] El componente '{$filename}' no tiene un prefijo de convención válido.\n\n" .
            "Prefijos válidos (según contexts.json):\n  {$validPrefixes}\n\n" .
            "Ejemplo correcto: \$this->renderModule('UserManagement', 'TenantEnergySpainUserList')\n"
        );
    }

    /**
     * Construye el mapa prefix → folder desde contexts.json del proyecto.
     * Los tenants se añaden primero (prefijos más largos) para garantizar coincidencias exactas.
     * Ordenado por longitud de clave descendente.
     *
     * @return array<string, string>  [class_prefix => folder]
     */
    private function buildPrefixMap(): array
    {
        $map = [];

        try {
            // Tenants primero (prefijos más específicos, ej: TenantEnergySpain)
            foreach (ContextResolver::allTenants() as $ctx) {
                $prefix = $ctx['class_prefix'] ?? null;
                $folder = $ctx['folder'] ?? null;
                if ($prefix && $folder) {
                    $map[$prefix] = $folder;
                }
            }

            // Contextos arquitectónicos (prefijos más cortos, ej: TenantShared, Central, Shared)
            foreach (ContextResolver::all() as $ctx) {
                $prefix = $ctx['class_prefix'] ?? null;
                $folder = $ctx['folder'] ?? null;
                if ($prefix && $folder) {
                    $map[$prefix] = $folder;
                }
            }
        } catch (\Throwable) {
            // Si contexts.json no está disponible, retorna mapa vacío (el error se lanzará arriba)
        }

        // Ordenar por longitud descendente para que prefijos más específicos ganen
        uksort($map, fn ($a, $b) => strlen($b) - strlen($a));

        return $map;
    }
}
