<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;

/**
 * Genera la interfaz y la implementación del servicio respetando la convención de contextos.
 *
 * Ejemplos de salida según contexto:
 *   central        → Services/Central/CentralUserService.php + Services/Central/Contracts/CentralUserServiceInterface.php
 *   tenant_shared  → Services/Tenant/Shared/TenantSharedUserService.php + .../Contracts/TenantSharedUserServiceInterface.php
 *   energy_spain   → Services/Tenant/EnergySpain/TenantEnergySpainUserService.php + .../Contracts/...
 */
class ServiceGenerator extends AbstractComponentGenerator
{
    /**
     * Nombre base del modelo asociado al servicio (StudlyCase, sin prefijo de contexto).
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
     * Genera la interfaz y la implementación del servicio.
     *
     * @return void
     */
    public function generate(): void
    {
        $serviceDir      = $this->buildPath('Services');
        $contractsDir    = $this->buildContractsPath('Services');

        $this->ensureDirectoryExists($serviceDir);
        $this->ensureDirectoryExists($contractsDir);

        $this->generateInterface($contractsDir);
        $this->generateImplementation($serviceDir);
    }

    /**
     * Genera el archivo de la interfaz del servicio.
     *
     * @param  string  $contractsDir  Ruta absoluta a la carpeta Contracts
     * @return void
     */
    private function generateInterface(string $contractsDir): void
    {
        $interfaceName = $this->prefixClass("{$this->modelName}ServiceInterface");
        $namespace     = $this->buildContractsNamespace('Services');

        $stub = $this->getStubContent('service-interface.stub', $this->isClean, [
            'namespace'            => $namespace,
            'serviceInterfaceName' => $interfaceName,
            'modelName'            => $this->modelName,
            'module'               => $this->moduleName,
        ]);

        $this->putFile(
            "{$contractsDir}/{$interfaceName}.php",
            $stub,
            "Interfaz creada: Modules/{$this->moduleName}/Services/Contracts/{$this->getContextFolder()}/{$interfaceName}.php"
        );
    }

    /**
     * Genera el archivo de la implementación del servicio.
     *
     * @param  string  $serviceDir  Ruta absoluta a la carpeta Services
     * @return void
     */
    private function generateImplementation(string $serviceDir): void
    {
        $serviceName               = $this->prefixClass("{$this->modelName}Service");
        $serviceInterfaceName      = $this->prefixClass("{$this->modelName}ServiceInterface");
        $repositoryName            = $this->prefixClass("{$this->modelName}Repository");
        $repositoryInterface       = $this->prefixClass("{$this->modelName}RepositoryInterface");
        $repositoryInterfaceNs     = $this->buildContractsNamespace('Repositories') . '\\' . $repositoryInterface;
        $repositoryInstance        = Str::camel($repositoryName);
        $namespace                 = $this->buildNamespace('Services');
        // FQCN del service interface para el `use` statement en el archivo de implementación
        $serviceInterfaceNamespace = $this->buildContractsNamespace('Services') . '\\' . $serviceInterfaceName;

        $stub = $this->getStubContent('service.stub', $this->isClean, [
            'namespace'                => $namespace,
            'serviceName'              => $serviceName,
            'modelName'                => $this->modelName,
            'module'                   => $this->moduleName,
            'serviceInterfaceName'     => $serviceInterfaceName,
            'serviceInterfaceNamespace'=> $serviceInterfaceNamespace,
            'repositoryInterfaceName'  => $repositoryInterface,
            'repositoryInterfaceNamespace' => $repositoryInterfaceNs,
            'repositoryInstance'       => $repositoryInstance,
        ]);

        $this->putFile(
            "{$serviceDir}/{$serviceName}.php",
            $stub,
            "Servicio creado: Modules/{$this->moduleName}/Services/{$this->getContextFolder()}/{$serviceName}.php"
        );
    }
}
