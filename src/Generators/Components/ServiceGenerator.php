<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class ServiceGenerator extends AbstractComponentGenerator
{
    protected string $serviceName;
    protected string $modelName; // Nombre del modelo asociado al servicio

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $serviceName, string $modelName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->serviceName = Str::studly($serviceName);
        $this->modelName = Str::studly($modelName);
    }

    /**
     * Genera el archivo del servicio y su interfaz.
     *
     * @return void
     */
    public function generate(): void
    {
        $serviceDir = $this->getComponentBasePath() . "/Services";
        $this->ensureDirectoryExists($serviceDir);
        $serviceContractDir = "{$serviceDir}/Contracts";
        $this->ensureDirectoryExists($serviceContractDir);

        $serviceInterfaceName = "{$this->serviceName}Interface";

        // Crear la interfaz primero
        $stubFileInterface = 'service-interface.stub';
        $stubInterface = $this->getStubContent($stubFileInterface, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Services\\Contracts",
            'serviceInterfaceName' => $serviceInterfaceName,
            'modelName' => $this->modelName,
            'module' => $this->moduleName,
        ]);
        $this->putFile("{$serviceContractDir}/{$serviceInterfaceName}.php", $stubInterface, "Interfaz {$serviceInterfaceName}.php creada en Modules/{$this->moduleName}/Services/Contracts");

        // Crear la implementación del servicio
        $stubFileService = 'service.stub';
        $repositoryName = Str::replaceLast('Service', 'Repository', $this->serviceName);
        $repositoryInterfaceName = "{$repositoryName}Interface";
        $repositoryInstance = Str::camel($repositoryName);
        
        $stubService = $this->getStubContent($stubFileService, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Services",
            'serviceName' => $this->serviceName,
            'modelName' => $this->modelName,
            'module' => $this->moduleName,
            'serviceInterfaceName' => $serviceInterfaceName,
            'repositoryInterfaceName' => $repositoryInterfaceName,
            'repositoryInstance' => $repositoryInstance,
        ]);
        $this->putFile("{$serviceDir}/{$this->serviceName}.php", $stubService, "Servicio {$this->serviceName}.php creado en Modules/{$this->moduleName}/Services");
    }
}