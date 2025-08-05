<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Illuminate\Support\Facades\File; // Asegúrate de que File esté importado si se usa en los métodos concretos.

abstract class AbstractComponentGenerator
{
    use HasStubs;

    protected string $moduleName;
    protected string $modulePath;
    protected bool $isClean;
    protected array $componentConfig; // Para configuraciones dinámicas de componentes

    public function __construct(string $moduleName, string $modulePath, bool $isClean, array $componentConfig = [])
    {
        $this->moduleName = Str::studly($moduleName); // Aseguramos StudlyCase para el nombre del módulo
        $this->modulePath = $modulePath;
        $this->isClean = $isClean;
        $this->componentConfig = $componentConfig;
    }

    /**
     * Ejecuta la generación del componente específico.
     * Cada clase concreta debe implementar este método.
     *
     * @return void
     */
    abstract public function generate(): void;

    /**
     * Obtiene la ruta base para el componente dentro del módulo.
     *
     * @return string
     */
    protected function getComponentBasePath(): string
    {
        return config('make-module.module_path') . "/{$this->moduleName}";
    }

    /**
     * Crea los directorios necesarios para el componente si no existen.
     *
     * @param string $directoryPath La ruta del directorio a crear.
     * @return void
     */
    protected function ensureDirectoryExists(string $directoryPath): void
    {
        File::ensureDirectoryExists($directoryPath);
    }

    /**
     * Escribe el contenido en un archivo y muestra un mensaje de éxito.
     *
     * @param string $filePath La ruta completa del archivo a escribir.
     * @param string $content El contenido a escribir en el archivo.
     * @param string $message El mensaje a mostrar en la consola.
     * @return void
     */
    protected function putFile(string $filePath, string $content, string $message): void
    {
        File::put($filePath, $content);
        // En un entorno real, esto se pasaría a un logger o al comando de consola.
        // Por ahora, lo dejamos como comentario o un simple echo para fines de demostración.
        // echo "✅ {$message}\n";
    }

    /**
     * Muestra un mensaje de información (simulado para la refactorización).
     * En el comando real, esto se pasaría al método info() del comando.
     *
     * @param string $message
     * @return void
     */
    protected function info(string $message): void
    {
        // Esto será inyectado por el comando MakeModuleCommand
        // o se puede usar un logger si se desea una solución más desacoplada.
        // echo "INFO: {$message}\n";
    }
}
