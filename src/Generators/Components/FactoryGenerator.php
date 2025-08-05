<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class FactoryGenerator extends AbstractComponentGenerator
{
    protected string $factoryName;
    protected string $modelName;

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $factoryName, string $modelName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->factoryName = Str::studly($factoryName);
        $this->modelName = Str::studly($modelName);
    }

    /**
     * Genera el archivo de la factory.
     *
     * @return void
     */
    public function generate(): void
    {
        $factoryDir = $this->getComponentBasePath() . "/Database/Factories";
        $this->ensureDirectoryExists($factoryDir);

        $stubFile = 'factory.stub';
        $stub = $this->getStubContent($stubFile, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Database\\Factories",
            'factoryName' => $this->factoryName,
            'modelName' => $this->modelName,
        ]);

        $this->putFile("{$factoryDir}/{$this->factoryName}Factory.php", $stub, "Factory {$this->factoryName}Factory.php creado en Modules/{$this->moduleName}/Database/Factories");
    }
}
