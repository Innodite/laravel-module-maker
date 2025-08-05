<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class ProviderGenerator extends AbstractComponentGenerator
{
    protected string $providerName;
    protected array $components; // Componentes para generar imports y bindings

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $providerName, array $components = [], array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->providerName = Str::studly($providerName);
        $this->components = $components;
    }

    /**
     * Genera el archivo del Service Provider.
     *
     * @return void
     */
    public function generate(): void
    {
        $providerDir = $this->getComponentBasePath() . "/Providers";
        $this->ensureDirectoryExists($providerDir);

        $stubFile = 'provider.stub';
        $modelImports = '';
        $modelBindings = '';

        // Generar los imports y bindings si el módulo es dinámico y tiene componentes
        if (!$this->isClean && !empty($this->components)) {
            foreach ($this->components as $component) {
                $modelName = Str::studly($component['name']);
                $modelImports .= "use Modules\\{$this->moduleName}\\Services\\Contracts\\{$modelName}ServiceInterface;\n";
                $modelImports .= "use Modules\\{$this->moduleName}\\Services\\{$modelName}Service;\n";
                $modelImports .= "use Modules\\{$this->moduleName}\\Repositories\\Contracts\\{$modelName}RepositoryInterface;\n";
                $modelImports .= "use Modules\\{$this->moduleName}\\Repositories\\{$modelName}Repository;\n";
                $modelBindings .= "        \$this->app->bind(\n";
                $modelBindings .= "            {$modelName}ServiceInterface::class,\n";
                $modelBindings .= "            {$modelName}Service::class\n";
                $modelBindings .= "        );\n";
                $modelBindings .= "        \$this->app->bind(\n";
                $modelBindings .= "            {$modelName}RepositoryInterface::class,\n";
                $modelBindings .= "            {$modelName}Repository::class\n";
                $modelBindings .= "        );\n";
            }
        } else {
            // Lógica para módulos limpios o si no hay componentes específicos en el JSON
            $modelName = Str::studly($this->moduleName);
            $modelImports .= "use Modules\\{$this->moduleName}\\Services\\Contracts\\{$modelName}ServiceInterface;\n";
            $modelImports .= "use Modules\\{$this->moduleName}\\Services\\{$modelName}Service;\n";
            $modelImports .= "use Modules\\{$this->moduleName}\\Repositories\\Contracts\\{$modelName}RepositoryInterface;\n";
            $modelImports .= "use Modules\\{$this->moduleName}\\Repositories\\{$modelName}Repository;\n";
            $modelBindings .= "        \$this->app->bind(\n";
            $modelBindings .= "            {$modelName}ServiceInterface::class,\n";
            $modelBindings .= "            {$modelName}Service::class\n";
            $modelBindings .= "        );\n";
            $modelBindings .= "        \$this->app->bind(\n";
            $modelBindings .= "            {$modelName}RepositoryInterface::class,\n";
            $modelBindings .= "            {$modelName}Repository::class\n";
            $modelBindings .= "        );\n";
        }

        $stub = $this->getStubContent($stubFile, $this->isClean, [
            'module' => $this->moduleName,
            'modelImports' => trim($modelImports),
            'modelBindings' => trim($modelBindings),
        ]);

        $this->putFile("{$providerDir}/{$this->moduleName}ServiceProvider.php", $stub, "Provider {$this->moduleName}ServiceProvider.php creado en Modules/{$this->moduleName}/Providers");
    }
}