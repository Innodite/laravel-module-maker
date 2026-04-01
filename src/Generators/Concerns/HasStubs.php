<?php

namespace Innodite\LaravelModuleMaker\Generators\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Trait HasStubs — v3.0.0
 *
 * Resuelve stubs en orden de prioridad:
 *   1. Stubs personalizados del proyecto: module-maker-config/stubs/contextual/{stub}
 *   2. Stubs por defecto del paquete: package/stubs/contextual/{stub}
 *
 * El parámetro $isClean se mantiene en la firma para compatibilidad, pero la resolución
 * en v3.0.0 usa un único directorio 'contextual/' sin distinción clean/dynamic.
 */
trait HasStubs
{
    /**
     * Obtiene el contenido del stub y reemplaza los marcadores de posición.
     *
     * @param  string       $stubFile      Nombre del archivo stub (ej: 'model.stub')
     * @param  bool         $isClean       Mantenido por compatibilidad (ignorado en resolución v3)
     * @param  array        $placeholders  Mapa de marcadores → valores
     * @param  string|null  $context       Clave de contexto (ignorada en v3, ruta única contextual/)
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
     * @param  string       $stubFile  Nombre del archivo stub
     * @param  bool         $isClean   Mantenido por compatibilidad
     * @param  string|null  $context   Mantenido por compatibilidad
     * @return string
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
                "Ejecuta 'php artisan innodite:module-setup' para publicar los stubs, " .
                "o crea el archivo en module-maker-config/stubs/contextual/{$stubFile}"
            );
        }

        return File::get($stubPath);
    }

    /**
     * Resuelve la ruta completa del archivo stub.
     *
     * Orden de resolución (v3.0.0):
     *   1. {config_path}/stubs/contextual/{stub}  — personalizado del proyecto
     *   2. package/stubs/contextual/{stub}         — default del paquete
     *
     * @param  string       $stubFile  Nombre del archivo stub
     * @param  bool         $isClean   Mantenido por compatibilidad
     * @param  string|null  $context   Mantenido por compatibilidad
     * @return string  Ruta al stub (custom si existe, default del paquete en caso contrario)
     */
    protected function getStubPath(string $stubFile, bool $isClean, ?string $context = null): string
    {
        // 1. Stubs personalizados del proyecto
        $customPath = config('make-module.stubs.path') . '/contextual/' . $stubFile;
        if (File::exists($customPath)) {
            return $customPath;
        }

        // 2. Stubs por defecto del paquete
        return __DIR__ . '/../../../stubs/contextual/' . $stubFile;
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
