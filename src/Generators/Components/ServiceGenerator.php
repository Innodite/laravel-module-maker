<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

/**
 * Class ServiceGenerator
 *
 * Genera una interfaz y una clase de servicio para un modelo dado, siguiendo el patrón de diseño Repository/Service.
 * La clase ServiceGenerator se encarga de crear la estructura de archivos y el contenido del código.
 */
class ServiceGenerator extends AbstractComponentGenerator
{
    /**
     * Directorio base para los servicios.
     */
    protected const SERVICES_DIRECTORY = 'Services';

    /**
     * Directorio para las interfaces de servicio.
     */
    protected const CONTRACTS_DIRECTORY = 'Services/Contracts';

    /**
     * Suffix para las interfaces de servicio.
     */
    protected const INTERFACE_SUFFIX = 'Interface';

    /**
     * Suffix para los repositorios.
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
     * @var string Nombre del servicio.
     */
    protected string $serviceName;

    /**
     * @var string Nombre del modelo asociado al servicio.
     */
    protected string $modelName;

    /**
     * ServiceGenerator constructor.
     *
     * @param string $moduleName Nombre del módulo.
     * @param string $modulePath Ruta del módulo.
     * @param bool $isClean Indica si el generador debe usar stubs "limpios".
     * @param string $serviceName Nombre del servicio a generar.
     * @param string $modelName Nombre del modelo asociado.
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
     * Este método actúa como un "Template Method", orquestando los pasos de la generación.
     *
     * @return void
     */
    public function generate(): void
    {
        $this->createDirectoryStructure();
        $this->generateServiceInterface();
        $this->generateServiceImplementation();
    }

    /**
     * Crea la estructura de directorios necesaria para los servicios.
     *
     * @return void
     */
    protected function createDirectoryStructure(): void
    {
        $servicesDir = $this->getComponentBasePath(self::SERVICES_DIRECTORY);
        $this->ensureDirectoryExists($servicesDir);

        $contractsDir = $this->getComponentBasePath(self::CONTRACTS_DIRECTORY);
        $this->ensureDirectoryExists($contractsDir);
    }

    /**
     * Genera la interfaz del servicio.
     *
     * @return void
     */
    protected function generateServiceInterface(): void
    {
        $serviceInterfaceName = $this->serviceName . self::INTERFACE_SUFFIX;
        $contractsDir = $this->getComponentBasePath(self::CONTRACTS_DIRECTORY);

        $stubContent = $this->getStubContent(self::SERVICE_INTERFACE_STUB, $this->isClean, [
            'namespace' => $this->getNamespace(self::CONTRACTS_DIRECTORY),
            'serviceInterfaceName' => $serviceInterfaceName,
            'modelName' => $this->modelName,
            'module' => $this->moduleName,
        ]);

        $outputPath = "{$contractsDir}/{$serviceInterfaceName}.php";
        $successMessage = "Interfaz {$serviceInterfaceName}.php creada en Modules/{$this->moduleName}/Services/Contracts";

        $this->putFile($outputPath, $stubContent, $successMessage);
    }

    /**
     * Genera la implementación del servicio.
     *
     * @return void
     */
    protected function generateServiceImplementation(): void
    {
        $servicesDir = $this->getComponentBasePath(self::SERVICES_DIRECTORY);
        $serviceInterfaceName = $this->serviceName . self::INTERFACE_SUFFIX;
        $repositoryName = Str::replaceLast('Service', self::REPOSITORY_SUFFIX, $this->serviceName);
        $repositoryInterfaceName = "{$repositoryName}Interface";
        $repositoryInstance = Str::camel($repositoryName);

        $stubContent = $this->getStubContent(self::SERVICE_IMPLEMENTATION_STUB, $this->isClean, [
            'namespace' => $this->getNamespace(self::SERVICES_DIRECTORY),
            'serviceName' => $this->serviceName,
            'modelName' => $this->modelName,
            'module' => $this->moduleName,
            'serviceInterfaceName' => $serviceInterfaceName,
            'repositoryInterfaceName' => $repositoryInterfaceName,
            'repositoryInstance' => $repositoryInstance,
        ]);

        $outputPath = "{$servicesDir}/{$this->serviceName}.php";
        $successMessage = "Servicio {$this->serviceName}.php creado en Modules/{$this->moduleName}/Services";

        $this->putFile($outputPath, $stubContent, $successMessage);
    }

    /**
     * Obtiene el namespace completo para un subdirectorio.
     *
     * @param string $subDirectory
     * @return string
     */
    protected function getNamespace(string $subDirectory): string
    {
        // Reemplazamos los slashes con backslashes y eliminamos el Services si es el caso.
        $subDirectory = str_replace(self::SERVICES_DIRECTORY . '/', '', $subDirectory);
        $subDirectory = str_replace('/', '\\', $subDirectory);

        return "Modules\\{$this->moduleName}\\Services" . ($subDirectory ? "\\{$subDirectory}" : '');
    }
}
