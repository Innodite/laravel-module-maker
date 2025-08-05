<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class RepositoryGenerator extends AbstractComponentGenerator
{
    protected string $repositoryName;
    protected string $modelName; // Nombre del modelo asociado al repositorio

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $repositoryName, string $modelName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->repositoryName = Str::studly($repositoryName);
        $this->modelName = Str::studly($modelName);
    }

    /**
     * Genera el archivo del repositorio y su interfaz.
     *
     * @return void
     */
    public function generate(): void
    {
        $repositoryDir = $this->getComponentBasePath() . "/Repositories";
        $this->ensureDirectoryExists($repositoryDir);
        $repositoryContractDir = "{$repositoryDir}/Contracts";
        $this->ensureDirectoryExists($repositoryContractDir);
        
        $repositoryInterfaceName = "{$this->repositoryName}Interface";

        // Crear la interfaz primero
        $stubFileInterface = 'repository-interface.stub';
        $stubInterface = $this->getStubContent($stubFileInterface, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Repositories\\Contracts",
            'repositoryInterfaceName' => $repositoryInterfaceName,
            'modelName' => $this->modelName,
            'module' => $this->moduleName,
        ]);
        $this->putFile("{$repositoryContractDir}/{$repositoryInterfaceName}.php", $stubInterface, "Interfaz {$repositoryInterfaceName}.php creada en Modules/{$this->moduleName}/Repositories/Contracts");

        // Crear la implementación del repositorio
        $stubFileRepository = 'repository.stub';
        $modelNameLowerCase = Str::camel($this->modelName);
        
        $stubRepository = $this->getStubContent($stubFileRepository, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Repositories",
            'repositoryName' => $this->repositoryName,
            'modelName' => $this->modelName,
            'module' => $this->moduleName,
            'repositoryInterfaceName' => $repositoryInterfaceName,
            'modelNameLowerCase' => $modelNameLowerCase,
        ]);
        $this->putFile("{$repositoryDir}/{$this->repositoryName}.php", $stubRepository, "Repositorio {$this->repositoryName}.php creado en Modules/{$this->moduleName}/Repositories");
    }
}
