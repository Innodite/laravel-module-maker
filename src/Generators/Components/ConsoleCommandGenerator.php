<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Innodite\LaravelModuleMaker\Generators\Concerns\WritesGeneratedFiles;

/**
 * Genera el archivo de Console Command para el contexto dado.
 *
 * Ejemplos de salida según contexto:
 *   central  → Console/Commands/Central/CentralModuleCleanupCommand.php
 *   tenant   → Console/Commands/Tenant/INNODITE/TenantINNODITEModuleImportCommand.php
 */
class ConsoleCommandGenerator
{
    use HasStubs;
    use WritesGeneratedFiles;

    public function __construct(
        private readonly array  $context,
        private readonly string $modulePath,
        private readonly string $moduleName,
    ) {}

    /**
     * Genera el archivo de Console Command en la carpeta correcta según el contexto.
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

        // Central → CleanupCommand + cleanup action, Tenant → ImportCommand + import action
        $isTenantSpecific = str_starts_with($contextFolder, 'Tenant/') && !str_ends_with($contextFolder, '/Shared') && $contextFolder !== 'Tenant/Shared';
        $commandSuffix    = $isTenantSpecific ? 'ImportCommand' : 'CleanupCommand';
        $action           = $isTenantSpecific ? 'import' : 'cleanup';

        // Signature: {contextPrefix|lowercase}:{moduleName|lowercase}-{action}
        $contextPrefixSlug = Str::lower(Str::kebab($contextPrefix));
        $moduleNameSlug    = Str::lower(Str::kebab($this->moduleName));
        $commandSignature  = "{$contextPrefixSlug}:{$moduleNameSlug}-{$action}";

        $commandDescription = "Execute {$className} {$action} operation.";

        // El namespace completo lo arma quien sabe si hay contexto, no la plantilla:
        // concatenarlo en el stub deja \\ cuando el contexto esta vacio, y eso no es PHP.
        $namespace = $moduleNamespace . '\\Console\\Commands'
            . ($contextNamespace !== '' ? '\\' . $contextNamespace : '');

        $placeholders = [
            'namespace'          => $namespace,
            'moduleNamespace'    => $moduleNamespace,
            'contextFolder'      => $contextNamespace,
            'className'          => $className,
            'moduleName'         => $this->moduleName,
            'contextPrefix'      => $contextPrefix,
            'commandSuffix'      => $commandSuffix,
            'commandSignature'   => $commandSignature,
            'commandDescription' => $commandDescription,
        ];

        $content = $this->getStubContent('console-command.stub', true, $placeholders);

        $dir = $this->modulePath . '/Console/Commands/' . $contextFolder;
        File::ensureDirectoryExists($dir);
        $this->putFile(
            $dir . '/' . $className . $commandSuffix . '.php',
            $content,
            "Comando generado: {$className}{$commandSuffix}.php"
        );
    }
}
