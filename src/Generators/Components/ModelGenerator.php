<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class ModelGenerator extends AbstractComponentGenerator
{
    protected string $modelName;

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $modelName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->modelName = Str::studly($modelName);
    }

    /**
     * Genera el archivo del modelo.
     *
     * @return void
     */
    public function generate(): void
    {
        $modelDir = $this->getComponentBasePath() . "/Models";
        $this->ensureDirectoryExists($modelDir);

        $stubFile = 'model.stub';
        $fillable = $this->getFillableForModel($this->componentConfig);
        $relationships = $this->getRelationshipsForModel($this->componentConfig, $this->moduleName);

        $stub = $this->getStubContent($stubFile, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Models",
            'modelName' => $this->modelName,
            'fillable' => $fillable,
            'relationships' => $relationships,
            'module' => $this->moduleName,
        ]);

        $this->putFile("{$modelDir}/{$this->modelName}.php", $stub, "Modelo {$this->modelName}.php creado en Modules/{$this->moduleName}/Models");
    }

    /**
     * Obtiene la cadena 'fillable' para el modelo a partir de la configuración del componente.
     *
     * @param array $componentConfig
     * @return string
     */
    protected function getFillableForModel(array $componentConfig): string
    {
        if (empty($componentConfig['attributes'])) {
            return '';
        }

        $fillable = collect($componentConfig['attributes'])
            ->filter(fn ($attr) => $attr['type'] !== 'relationship')
            ->map(fn ($attr) => "'" . $attr['name'] . "'")
            ->implode(', ');

        return $fillable ? "protected \$fillable = [{$fillable}];" : '';
    }

    /**
     * Genera el código para los métodos de relación del modelo.
     *
     * @param array $componentConfig
     * @param string $module
     * @return string
     */
    protected function getRelationshipsForModel(array $componentConfig, string $module): string
    {
        if (empty($componentConfig['attributes'])) {
            return '';
        }

        $relationships = collect($componentConfig['attributes'])
            ->filter(fn ($attr) => $attr['type'] === 'relationship')
            ->map(function ($attr) use ($componentConfig, $module) {
                $relationship = $attr['relationship'];
                $modelName = Str::studly($relationship['model']);
                $methodName = Str::camel($attr['name']);
                $type = $relationship['type'];

                $relatedModelClass = "Modules\\{$module}\\Models\\{$modelName}";

                $code = "    /**\n";
                $code .= "     * Get the {$attr['name']} associated with the {$componentConfig['name']}.\n";
                $code .= "     */\n";
                $code .= "    public function {$methodName}(): \\Illuminate\\Database\\Eloquent\\Relations\\{$type}\n";
                $code .= "    {\n";
                $code .= "        return \$this->{$type}(" . $relatedModelClass . "::class);\n";
                $code .= "    }\n\n";

                return $code;
            })
            ->implode('');

        return $relationships;
    }
}
