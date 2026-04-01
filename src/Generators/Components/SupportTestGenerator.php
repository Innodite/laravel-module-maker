<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

/**
 * Genera el archivo de Test Support (helper factory) para el contexto Central.
 *
 * Ejemplo de salida:
 *   central → Tests/Support/Central/CentralModuleSupport.php
 */
class SupportTestGenerator
{
    use HasStubs;

    public function __construct(
        private readonly array  $context,
        private readonly string $modulePath,
        private readonly string $moduleName,
    ) {}

    /**
     * Genera el archivo de Support en la carpeta Tests/Support según el contexto.
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

        $content = $this->getStubContent('test-support.stub', true, $placeholders);

        $dir = $this->modulePath . '/Tests/Support/' . $contextFolder;
        File::ensureDirectoryExists($dir);
        File::put($dir . '/' . $className . 'Support.php', $content);
    }
}
