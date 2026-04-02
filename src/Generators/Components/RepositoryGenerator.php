<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;

/**
 * Genera la interfaz y la implementación del repositorio respetando la convención de contextos.
 *
 * El repositorio es el único archivo con acceso directo al Model (Eloquent).
 * Ningún controller, service ni otro archivo puede tocar el Model directamente.
 *
 * Ejemplos de salida según contexto:
 *   central        → Repositories/Central/CentralUserRepository.php
 *   tenant_shared  → Repositories/Tenant/Shared/TenantSharedUserRepository.php
 *   tenant_alpha   → Repositories/Tenant/TenantAlpha/TenantTenantAlphaUserRepository.php
 */
class RepositoryGenerator extends AbstractComponentGenerator
{
    /**
     * Nombre base del modelo asociado al repositorio (StudlyCase, sin prefijo de contexto).
     *
     * @var string
     */
    protected string $modelName;

    /**
     * @param  string  $moduleName       Nombre del módulo
     * @param  string  $modulePath       Ruta absoluta al directorio del módulo
     * @param  bool    $isClean          true = stubs clean, false = stubs dynamic
     * @param  string  $modelName        Nombre del modelo en StudlyCase
     * @param  array   $componentConfig  Configuración del componente (debe incluir 'context')
     */
    public function __construct(
        string $moduleName,
        string $modulePath,
        bool $isClean,
        string $modelName,
        array $componentConfig = []
    ) {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->modelName = Str::studly($modelName);
    }

    /**
     * Genera la interfaz y la implementación del repositorio.
     *
     * @return void
     */
    public function generate(): void
    {
        $repoDir      = $this->buildPath('Repositories');
        $contractsDir = $this->buildContractsPath('Repositories');

        $this->ensureDirectoryExists($repoDir);
        $this->ensureDirectoryExists($contractsDir);

        $this->generateInterface($contractsDir);
        $this->generateImplementation($repoDir);
    }

    /**
     * Genera el archivo de la interfaz del repositorio.
     *
     * @param  string  $contractsDir  Ruta absoluta a la carpeta Contracts
     * @return void
     */
    private function generateInterface(string $contractsDir): void
    {
        $interfaceName = $this->prefixClass("{$this->modelName}RepositoryInterface");
        $namespace     = $this->buildContractsNamespace('Repositories');

        $stub = $this->getStubContent('repository-interface.stub', $this->isClean, [
            'namespace'               => $namespace,
            'repositoryInterfaceName' => $interfaceName,
            'modelName'               => $this->modelName,
            'module'                  => $this->moduleName,
        ]);

        $this->putFile(
            "{$contractsDir}/{$interfaceName}.php",
            $stub,
            "Interfaz creada: Modules/{$this->moduleName}/Repositories/Contracts/{$this->getContextFolder()}/{$interfaceName}.php"
        );
    }

    /**
     * Genera el archivo de la implementación del repositorio.
     *
     * @param  string  $repoDir  Ruta absoluta a la carpeta Repositories
     * @return void
     */
    private function generateImplementation(string $repoDir): void
    {
        $repoName              = $this->prefixClass("{$this->modelName}Repository");
        $repoInterface         = $this->prefixClass("{$this->modelName}RepositoryInterface");
        $modelInstance         = Str::camel($this->modelName);
        $namespace             = $this->buildNamespace('Repositories');
        // FQCN del model para el `use` statement (el modelo vive en Models/{contextFolder}/)
        $modelFqcn             = $this->buildNamespace('Models') . '\\' . $this->modelName;
        // FQCN del repository interface para el `use` statement
        $repoInterfaceNs       = $this->buildContractsNamespace('Repositories') . '\\' . $repoInterface;

        $stub = $this->getStubContent('repository.stub', $this->isClean, [
            'namespace'                    => $namespace,
            'repositoryName'               => $repoName,
            'modelName'                    => $this->modelName,
            'modelNamespace'               => $modelFqcn,
            'module'                       => $this->moduleName,
            'repositoryInterfaceName'      => $repoInterface,
            'repositoryInterfaceNamespace' => $repoInterfaceNs,
            'modelNameLowerCase'           => $modelInstance,
        ]);

        $this->putFile(
            "{$repoDir}/{$repoName}.php",
            $stub,
            "Repositorio creado: Modules/{$this->moduleName}/Repositories/{$this->getContextFolder()}/{$repoName}.php"
        );
    }
}
