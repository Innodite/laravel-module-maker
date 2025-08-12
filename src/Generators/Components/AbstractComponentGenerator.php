<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractComponentGenerator
{
    use HasStubs;

    protected string $moduleName;
    protected string $modulePath;
    protected bool $isClean;
    protected array $componentConfig;
    protected ?OutputInterface $output = null;

    /**
     * @param string $moduleName El nombre del módulo.
     * @param string $modulePath La ruta base al directorio de módulos.
     * @param bool $isClean Indica si el módulo se está creando desde cero.
     * @param array $componentConfig La configuración específica para el componente.
     */
    public function __construct(string $moduleName, string $modulePath, bool $isClean, array $componentConfig = [])
    {
        $this->moduleName = Str::studly($moduleName);
        $this->modulePath = $modulePath;
        $this->isClean = $isClean;
        $this->componentConfig = $componentConfig;
    }

    /**
     * Establece el objeto de salida de la consola.
     *
     * @param OutputInterface $output La interfaz de salida de Symfony Console.
     * @return self
     */
    public function setOutput(OutputInterface $output): self
    {
        $this->output = $output;
        return $this;
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
        $this->info("✅ {$message}");
    }

    /**
     * Muestra un mensaje de información en la consola.
     *
     * @param string $message El mensaje a mostrar.
     * @return void
     */
    protected function info(string $message): void
    {
        if ($this->output) {
            $this->output->writeln("<info>{$message}</info>");
        }
    }

    /**
     * Muestra un mensaje de advertencia en la consola.
     *
     * @param string $message El mensaje a mostrar.
     * @return void
     */
    protected function warn(string $message): void
    {
        if ($this->output) {
            $this->output->writeln("<comment>{$message}</comment>");
        }
    }

    /**
     * Muestra un mensaje de error en la consola.
     *
     * @param string $message El mensaje a mostrar.
     * @return void
     */
    protected function error(string $message): void
    {
        if ($this->output) {
            $this->output->writeln("<error>{$message}</error>");
        }
    }
}