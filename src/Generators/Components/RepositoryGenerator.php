<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class RepositoryGenerator extends AbstractComponentGenerator
{
    // Constantes para nombres de directorios y stubs, lo que facilita el mantenimiento
    protected const REPOSITORY_DIRECTORY_NAME = 'Repositories';
    protected const REPOSITORY_CONTRACT_DIRECTORY_NAME = 'Contracts';
    protected const REPOSITORY_INTERFACE_STUB_FILE = 'repository-interface.stub';
    protected const REPOSITORY_STUB_FILE = 'repository.stub';

    protected string $repositoryClassName;
    protected string $modelClassName; // Nombre de la clase del modelo asociado al repositorio

    public function __construct(
        string $moduleName, 
        string $modulePath, 
        bool $isClean, 
        string $repositoryClassName, 
        string $modelClassName, 
        array $componentConfig = []
    ) {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->repositoryClassName = Str::studly($repositoryClassName);
        $this->modelClassName = Str::studly($modelClassName);
    }

    /**
     * Genera el archivo del repositorio y su interfaz.
     *
     * @return void
     */
    public function generate(): void
    {
        // Usamos constantes para definir las rutas de los directorios
        $repositoryDirectoryPath = $this->getComponentBasePath() . '/' . self::REPOSITORY_DIRECTORY_NAME;
        $this->ensureDirectoryExists($repositoryDirectoryPath);

        $repositoryContractDirectoryPath = $repositoryDirectoryPath . '/' . self::REPOSITORY_CONTRACT_DIRECTORY_NAME;
        $this->ensureDirectoryExists($repositoryContractDirectoryPath);
        
        $repositoryInterfaceClassName = "{$this->repositoryClassName}Interface";

        // Crear la interfaz primero
        $stubInterfaceContent = $this->getStubContent(
            self::REPOSITORY_INTERFACE_STUB_FILE, 
            $this->isClean, 
            [
                'namespace' => "Modules\\{$this->moduleName}\\" . self::REPOSITORY_DIRECTORY_NAME . '\\' . self::REPOSITORY_CONTRACT_DIRECTORY_NAME,
                'repositoryInterfaceName' => $repositoryInterfaceClassName,
                'modelName' => $this->modelClassName,
                'module' => $this->moduleName,
            ]
        );
        $this->putFile(
            "{$repositoryContractDirectoryPath}/{$repositoryInterfaceClassName}.php", 
            $stubInterfaceContent, 
            "Interfaz {$repositoryInterfaceClassName}.php creada en Modules/{$this->moduleName}/Repositories/Contracts"
        );

        // Crear la implementación del repositorio
        $modelVariableName = Str::camel($this->modelClassName);
        
        $stubRepositoryContent = $this->getStubContent(
            self::REPOSITORY_STUB_FILE, 
            $this->isClean, 
            [
                'namespace' => "Modules\\{$this->moduleName}\\" . self::REPOSITORY_DIRECTORY_NAME,
                'repositoryName' => $this->repositoryClassName,
                'modelName' => $this->modelClassName,
                'module' => $this->moduleName,
                'repositoryInterfaceName' => $repositoryInterfaceClassName,
                'modelNameLowerCase' => $modelVariableName,
            ]
        );
        $this->putFile(
            "{$repositoryDirectoryPath}/{$this->repositoryClassName}.php", 
            $stubRepositoryContent, 
            "Repositorio {$this->repositoryClassName}.php creado en Modules/{$this->moduleName}/Repositories"
        );
    }
}
