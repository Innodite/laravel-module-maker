<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class RouteGenerator extends AbstractComponentGenerator
{
    protected string $controllerName;

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $controllerName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->controllerName = Str::studly($controllerName);
    }

    /**
     * Genera los archivos de rutas (api.php y web.php).
     *
     * @return void
     */
    public function generate(): void
    {
        $routesDir = $this->getComponentBasePath() . "/routes";
        $this->ensureDirectoryExists($routesDir);

        // Generar rutas API
        $stubFileApi = 'route-api.stub';
        $stubApi = $this->getStubContent($stubFileApi, $this->isClean, [
            'StudlyModule' => $this->moduleName,
            'snakeModule' => Str::snake($this->moduleName),
            'controllerName' => $this->controllerName,
        ]);
        $this->putFile("{$routesDir}/api.php", $stubApi, "Rutas API creadas en Modules/{$this->moduleName}/routes/api.php");

        // Generar rutas Web
        $stubFileWeb = 'route-web.stub';
        $stubWeb = $this->getStubContent($stubFileWeb, $this->isClean, [
            'StudlyModule' => $this->moduleName,
            'snakeModule' => Str::snake($this->moduleName),
            'controllerName' => $this->controllerName,
        ]);
        $this->putFile("{$routesDir}/web.php", $stubWeb, "Rutas WEB creadas en Modules/{$this->moduleName}/routes/web.php");
    }
}
