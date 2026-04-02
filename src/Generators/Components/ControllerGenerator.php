<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;

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
        $viewName           = $this->prefixClass("{$this->modelName}Index");

        $this->ensureDirectoryExists($controllerDir);

        $stub = $this->getStubContent('controller.stub', $this->isClean, [
            'namespace'                => $namespace,
            'controllerName'           => $controllerName,
            'modelName'                => $this->modelName,
            'module'                   => $this->moduleName,
            'serviceInterface'         => $serviceInterface,
            'serviceInstance'          => $serviceInstance,
            'serviceInterfaceNamespace'=> $serviceInterfaceNs,
            'viewName'                 => $viewName,
        ]);

        $relativePath = "Http/Controllers/{$this->getContextFolder()}/{$controllerName}.php";
        $this->putFile(
            "{$controllerDir}/{$controllerName}.php",
            $stub,
            "Controlador creado: Modules/{$this->moduleName}/{$relativePath}"
        );
    }
}
