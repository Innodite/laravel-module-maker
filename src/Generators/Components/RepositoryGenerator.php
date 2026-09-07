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
        ]);

        $this->putFile(
            "{$contractsDir}/{$interfaceName}.php",
            $stub,
            'Interfaz creada: ' . $this->rutaVisible("{$contractsDir}/{$interfaceName}.php")
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
        // El modelo se llama como lo escribió el ModelGenerator: CON el prefijo del contexto si el
        // modo lo pide. Sin él, en multitenant el repositorio importaba `…\Central\Invoice\Invoice`
        // cuando la clase generada es `CentralInvoice` — la segunda mitad de B13 otra vez, y aquí
        // se lleva por delante toda la persistencia del módulo, porque el Repository es la única
        // capa que toca Eloquent.
        $modelClass            = $this->prefixClass($this->modelName);
        $modelFqcn             = $this->buildNamespace('Models') . '\\' . $modelClass;
        // FQCN del repository interface para el `use` statement
        $repoInterfaceNs       = $this->buildContractsNamespace('Repositories') . '\\' . $repoInterface;

        $stub = $this->getStubContent('repository.stub', $this->isClean, [
            'namespace'                    => $namespace,
            'repositoryName'               => $repoName,
            'modelName'                    => $modelClass,
            'modelNamespace'               => $modelFqcn,
            'repositoryInterfaceName'      => $repoInterface,
            'repositoryInterfaceNamespace' => $repoInterfaceNs,
            'modelNameLowerCase'           => $modelInstance,
        ]);

        $this->putFile(
            "{$repoDir}/{$repoName}.php",
            $stub,
            'Repositorio creado: ' . $this->rutaVisible("{$repoDir}/{$repoName}.php")
        );
    }
}
