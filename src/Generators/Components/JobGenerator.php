<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Innodite\LaravelModuleMaker\Support\Disk;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Innodite\LaravelModuleMaker\Generators\Concerns\WritesGeneratedFiles;

/**
 * Genera el archivo del Job para el contexto dado.
 *
 * Ejemplos de salida según contexto:
 *   central        → Jobs/Central/CentralModuleExportJob.php
 *   tenant_shared  → Jobs/Tenant/Shared/TenantSharedModuleExportJob.php
 *   tenant         → Jobs/Tenant/INNODITE/TenantINNODITEModuleReportJob.php
 */
class JobGenerator
{
    use HasStubs;
    use WritesGeneratedFiles;

    public function __construct(
        private readonly array $context,
        private readonly string $modulePath,
        private readonly string $moduleName,
    ) {
    }

    /**
     * Genera el archivo del Job en la carpeta correcta según el contexto.
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

        // Tenant específico (no Shared) → ReportJob, resto → ExportJob
        $isTenantSpecific = str_starts_with($contextFolder, 'Tenant/') && !str_ends_with($contextFolder, '/Shared') && $contextFolder !== 'Tenant/Shared';
        $jobSuffix        = $isTenantSpecific ? 'ReportJob' : 'ExportJob';

        // El namespace completo lo arma quien sabe si hay contexto, no la plantilla:
        // concatenarlo en el stub deja \\ cuando el contexto esta vacio, y eso no es PHP.
        $namespace = $moduleNamespace . '\\Jobs'
            . ($contextNamespace !== '' ? '\\' . $contextNamespace : '');

        $placeholders = [
            'namespace'       => $namespace,
            'moduleNamespace' => $moduleNamespace,
            'contextFolder'   => $contextNamespace,
            'className'       => $className,
            'moduleName'      => $this->moduleName,
            'contextPrefix'   => $contextPrefix,
            'jobSuffix'       => $jobSuffix,
        ];

        $content = $this->getStubContent('job.stub', true, $placeholders);

        $dir = $this->modulePath . '/Jobs/' . $contextFolder;
        Disk::ensureDirectory($dir);
        $this->putFile(
            $dir . '/' . $className . $jobSuffix . '.php',
            $content,
            "Job generado: {$className}{$jobSuffix}.php"
        );
    }
}
