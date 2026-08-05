<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Innodite\LaravelModuleMaker\Generators\Concerns\WritesGeneratedFiles;

/**
 * Genera el archivo de Exception (NotFoundException) para el contexto Central.
 *
 * Ejemplo de salida:
 *   central → Exceptions/Central/CentralModuleNotFoundException.php
 */
class ExceptionGenerator
{
    use HasStubs;
    use WritesGeneratedFiles;

    public function __construct(
        private readonly array  $context,
        private readonly string $modulePath,
        private readonly string $moduleName,
    ) {}

    /**
     * Genera el archivo de Exception en la carpeta correcta según el contexto.
     *
     * @return void
     */
    public function generate(): void
    {
        $contextPrefix    = $this->context['class_prefix'] ?? '';
        $contextFolder    = str_replace('\\', '/', $this->context['folder'] ?? '');
        $contextNamespace = str_replace('/', '\\', $contextFolder);
        $moduleNamespace  = 'Modules\\' . $this->moduleName;
        $className        = $contextPrefix . $this->moduleName;

        $placeholders = [
            'moduleNamespace' => $moduleNamespace,
            'contextFolder'   => $contextNamespace,
            'className'       => $className,
            'moduleName'      => $this->moduleName,
            'contextPrefix'   => $contextPrefix,
        ];

        $content = $this->getStubContent('exception.stub', true, $placeholders);

        $dir = $this->modulePath . '/Exceptions/' . $contextFolder;
        File::ensureDirectoryExists($dir);
        $this->putFile(
            $dir . '/' . $className . 'NotFoundException.php',
            $content,
            "Excepción generada: {$className}NotFoundException.php"
        );
    }
}
