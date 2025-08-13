<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

/**
 * Class ServiceGenerator
 *
 * Genera una interfaz y una clase de servicio para un modelo dado, siguiendo el patrón Repository/Service.
 * Esta clase se encarga de crear la estructura de directorios y el contenido de los archivos.
 */
class ServiceGenerator extends AbstractComponentGenerator
{
    use HasStubs;
    /**
     * Directorio base para las implementaciones de servicios.
     */
    protected const SERVICES_DIR = 'Services';

    /**
     * Directorio para las interfaces de servicios.
     */
    protected const CONTRACTS_DIR = 'Services/Contracts';

    /**
     * Sufijo para las interfaces de servicio.
     */
    protected const INTERFACE_SUFFIX = 'Interface';

    /**
     * Sufijo para los repositorios.
     */
    protected const REPOSITORY_SUFFIX = 'Repository';

    /**
     * Stub para la interfaz del servicio.
     */
    protected const SERVICE_INTERFACE_STUB = 'service-interface.stub';

    /**
     * Stub para la implementación del servicio.
     */
    protected const SERVICE_IMPLEMENTATION_STUB = 'service.stub';

    /**
     * @var string El nombre del servicio.
     */
    protected string $serviceName;

    /**
     * @var string El nombre del modelo asociado al servicio.
     */
    protected string $modelName;

    
     /**
     * ServiceGenerator constructor.
     *
     * @param string $moduleName El nombre del módulo.
     * @param string $modulePath La ruta del módulo.
     * @param bool $isClean Indica si el generador debe usar stubs "limpios".
     * @param string $serviceName El nombre del servicio a generar.
     * @param string $modelName El nombre del modelo asociado.
     * @param array $componentConfig Configuración adicional del componente.
     */
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
        $serviceDirectory = $this->getComponentBasePath() .'/'. self::SERVICES_DIR;
        $serviceContractDirectory = $this->getComponentBasePath() .'/'.self::CONTRACTS_DIR;

        $this->ensureDirectoryExists($serviceDirectory);
        $this->ensureDirectoryExists($serviceContractDirectory);

        
        $this->generateServiceContract($serviceContractDirectory);

        $this->generateServiceImplementation($serviceDirectory);
    }

    /**
     * Genera la interfaz del servicio.
     *
     * @param string $serviceContractDirectory El directorio donde se guardarán los contratos.
     * @return void
     */
    protected function generateServiceContract(string $serviceContractDirectory): void
    {
        $serviceInterfaceName = $this->serviceName . self::INTERFACE_SUFFIX;

        $stubInterface = $this->getStubContent(self::SERVICE_INTERFACE_STUB, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Services\\Contracts",
            'serviceInterfaceName' => $serviceInterfaceName,
            'modelName' => $this->modelName,
            'module' => $this->moduleName,
        ]);

        $this->putFile(
            "{$serviceContractDirectory}/{$serviceInterfaceName}.php", 
            $stubInterface, 
            "Interfaz {$serviceInterfaceName}.php creada en Modules/{$this->moduleName}/Services/Contracts"
        );
    }

    /**
     * Genera la implementación de la clase de servicio.
     *
     * @param string $serviceDirectory El directorio donde se guardarán las implementaciones.
     * @return void
     */
    protected function generateServiceImplementation(string $serviceDirectory): void
    {
         $serviceInterfaceName = $this->serviceName . self::INTERFACE_SUFFIX;
         $repositoryName = Str::replaceLast('Service', self::REPOSITORY_SUFFIX, $this->serviceName);
         $repositoryInterfaceName = "{$repositoryName}Interface";
         $repositoryInstance = Str::camel($repositoryName);
         
         $stubService = $this->getStubContent(self::SERVICE_IMPLEMENTATION_STUB, $this->isClean, [
             'namespace' => "Modules\\{$this->moduleName}\\Services",
             'serviceName' => $this->serviceName,
             'modelName' => $this->modelName,
             'module' => $this->moduleName,
             'serviceInterfaceName' => $serviceInterfaceName,
             'repositoryInterfaceName' => $repositoryInterfaceName,
             'repositoryInstance' => $repositoryInstance,
         ]);
         $this->putFile(
            "{$serviceDirectory}/{$this->serviceName}.php", 
            $stubService, 
            "Servicio {$this->serviceName}.php creado en Modules/{$this->moduleName}/Services"
        );
    }

}