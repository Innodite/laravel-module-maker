<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class ControllerGenerator extends AbstractComponentGenerator
{
    protected string $controllerName;
    protected string $modelName; // Nombre del modelo asociado al controlador

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $controllerName, string $modelName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->controllerName = Str::studly($controllerName);
        $this->modelName = Str::studly($modelName);
    }

    /**
     * Genera el archivo del controlador.
     *
     * @return void
     */
    public function generate(): void
    {
        $controllerDir = $this->getComponentBasePath() . "/Http/Controllers";
        $this->ensureDirectoryExists($controllerDir);

        $stubFile = 'controller.stub';
        $serviceInterface = "{$this->modelName}ServiceInterface";
        $serviceInstance = Str::camel($this->modelName) . 'Service';

        $stub = $this->getStubContent($stubFile, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Http\\Controllers",
            'controllerName' => $this->controllerName,
            'modelName' => $this->modelName,
            'module' => $this->moduleName,
            'serviceInterface' => $serviceInterface,
            'serviceInstance' => $serviceInstance,
        ]);

        $this->putFile("{$controllerDir}/{$this->controllerName}.php", $stub, "Controlador {$this->controllerName}.php creado en Modules/{$this->moduleName}/Http/Controllers");
    }
}
