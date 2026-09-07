<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Support\RequestNames;

/**
 * Genera el archivo del controlador respetando la convención de contextos.
 *
 * El nombre de la clase y la carpeta de destino se derivan automáticamente
 * del campo 'context' en la configuración del componente.
 *
 * Ejemplos de salida según contexto:
 *   central        → Http/Controllers/Central/CentralUserController.php
 *   tenant_shared  → Http/Controllers/Tenant/Shared/TenantSharedUserController.php
 *   tenant_alpha   → Http/Controllers/Tenant/Alpha/TenantAlphaUserController.php
 */
class ControllerGenerator extends AbstractComponentGenerator
{
    /**
     * Nombre base del modelo asociado al controlador (StudlyCase, sin prefijo de contexto).
     *
     * @var string
     */
    protected string $modelName;

    /**
     * @param  string  $moduleName       Nombre del módulo
     * @param  string  $modulePath       Ruta absoluta al directorio del módulo
     * @param  bool    $isClean          true = stubs clean, false = stubs dynamic
     * @param  string  $modelName        Nombre del modelo en StudlyCase (ej: 'User', 'Product')
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
     * Genera el archivo del controlador en la carpeta correcta según el contexto.
     *
     * @return void
     */
    public function generate(): void
    {
        $controllerName     = $this->prefixClass("{$this->modelName}Controller");
        $serviceInterface   = $this->prefixClass("{$this->modelName}ServiceInterface");
        $serviceInstance    = Str::camel($this->prefixClass("{$this->modelName}Service"));
        $namespace          = $this->buildNamespace('Http\\Controllers');
        $controllerDir      = $this->buildPath('Http/Controllers');
        // FQCN del service interface: Services/Contracts/{Context}/{Interface}
        $serviceInterfaceNs = $this->buildContractsNamespace('Services') . '\\' . $serviceInterface;
        // ⚠️ Con su carpeta delante. El generador de vistas escribe en
        // `Pages/{Contexto}/{SubFuncionalidad}/`, y pedir solo el nombre del archivo hacía que el
        // trait buscara en `Pages/{Contexto}/` — un archivo que no está ahí. Ninguna prueba lo veía
        // porque todas comprobaban que la vista se GENERA, no que se RESUELVA.
        // La ruta de la vista se resuelve AQUÍ, en generación, no en tiempo de ejecución.
        //
        // Antes la componía a medias un trait del paquete, que en cada petición leía `contexts.json`,
        // deducía la carpeta del prefijo del nombre del componente y comprobaba que el archivo
        // existiera. Eso mezclaba los dos modos en una sola pieza —tenía que preguntar si el
        // proyecto tenía eje de contexto para decidir si exigir prefijo— y trasladaba a producción
        // un cálculo cuya respuesta ya se conoce al generar: el generador **sabe** en qué contexto
        // está escribiendo, porque es él quien elige la carpeta donde deja el `.vue`.
        //
        // Así que escribe la ruta entera y el controlador llama a `Inertia::render()` como cualquier
        // controlador de Laravel. Sin trait, sin lectura de configuración por petición, y sin un
        // `if` de modo en el camino de una pantalla.
        $carpetaDeContexto  = $this->getContextFolder();
        $viewName           = implode('/', array_filter([
            $carpetaDeContexto,
            $this->subFeatureName(),
            $this->prefixClass("{$this->modelName}Index"),
        ]));

        // Los dos FormRequests que reciben `store()` y `update()`. El nombre lo decide
        // `RequestNames`, que es de donde lo lee también el generador que los escribe: componerlo
        // aquí por separado sería la tercera copia del mismo cálculo, y es esa clase de copia la
        // que dejó al manifiesto del contrato apuntando —en uno de los modos— a una clase que
        // nadie generaba nunca.
        $requests   = RequestNames::all($this->getClassPrefix(), $this->subFeatureName());
        $requestsNs = $this->buildNamespace('Http\\Requests');

        $this->ensureDirectoryExists($controllerDir);

        $stub = $this->getStubContent('controller.stub', $this->isClean, [
            'namespace'                => $namespace,
            'controllerName'           => $controllerName,
            'module'                   => $this->moduleName,
            'serviceInterface'         => $serviceInterface,
            'serviceInstance'          => $serviceInstance,
            'serviceInterfaceNamespace' => $serviceInterfaceNs,
            'viewName'                 => $viewName,
            'storeRequest'             => $requests['store'],
            'updateRequest'            => $requests['update'],
            'storeRequestNamespace'    => "{$requestsNs}\\{$requests['store']}",
            'updateRequestNamespace'   => "{$requestsNs}\\{$requests['update']}",
        ]);

        $this->putFile(
            "{$controllerDir}/{$controllerName}.php",
            $stub,
            'Controlador creado: ' . $this->rutaVisible("{$controllerDir}/{$controllerName}.php")
        );
    }
}
