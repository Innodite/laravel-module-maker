<?php

namespace Innodite\LaravelModuleMaker\Generators\Concerns;

use Illuminate\Support\Facades\File;

trait HasStubs
{
    /**
     * Obtiene el contenido de un stub y reemplaza los marcadores de posición.
     *
     * @param string $stubFile El nombre del archivo stub (ej. 'model.stub').
     * @param bool $isClean Indica si se usa la versión 'clean' o 'dynamic' del stub.
     * @param array $placeholders Un array asociativo de marcadores de posición y sus valores.
     * @return string El contenido del stub con los marcadores de posición reemplazados.
     * @throws \Exception Si el archivo stub no se encuentra.
     */
    protected function getStubContent(string $stubFile, bool $isClean, array $placeholders = []): string
    {
        $stub = $this->getStub($stubFile, $isClean);
        return $this->replacePlaceholders($stub, $placeholders);
    }

    /**
     * Obtiene el contenido puro del archivo stub.
     *
     * @param string $stubFile El nombre del archivo stub.
     * @param bool $isClean Indica si se usa la versión 'clean' o 'dynamic' del stub.
     * @return string El contenido del archivo stub.
     * @throws \Exception Si el archivo stub no se encuentra.
     */
    protected function getStub(string $stubFile, bool $isClean): string
    {
        $stubPath = $this->getStubPath($stubFile, $isClean);
        if (!File::exists($stubPath)) {
            throw new \Exception("El archivo stub '{$stubFile}' no se encuentra en '{$stubPath}'.");
        }
        return File::get($stubPath);
    }

    /**
     * Resuelve la ruta completa del archivo stub, basándose en la configuración del paquete.
     *
     * @param string $stubFile El nombre del archivo stub.
     * @param bool $isClean Indica si se usa la versión 'clean' o 'dynamic' del stub.
     * @return string La ruta completa al archivo stub.
     */
    protected function getStubPath(string $stubFile, bool $isClean): string
    {
        $baseStubPath = config('make-module.stubs.path');
        $subfolder = $isClean ? 'clean' : 'dynamic';
        return "{$baseStubPath}/{$subfolder}/{$stubFile}";
    }

    /**
     * Reemplaza los marcadores de posición en el contenido del stub.
     *
     * @param string $stub El contenido del stub.
     * @param array $placeholders Un array asociativo de marcadores de posición (sin llaves) y sus valores.
     * @return string El contenido del stub con los marcadores de posición reemplazados.
     */
    protected function replacePlaceholders(string $stub, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $stub = str_replace("{{ {$key} }}", $value, $stub);
        }
        return $stub;
    }
}