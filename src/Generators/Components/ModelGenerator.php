<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Clase para generar archivos de modelo de Laravel de forma dinámica.
 *
 * Esta versión incluye refactorización para generar la propiedad $fillable
 * y los métodos de relación de Eloquent de forma más robusta y legible.
 */
class ModelGenerator extends AbstractComponentGenerator
{
    // Constantes para nombres de directorios y archivos de stub
    protected const MODEL_DIRECTORY = 'Models';
    protected const MODEL_STUB_FILE = 'model.stub';

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
        $modelDir = $this->getComponentBasePath() . '/' . self::MODEL_DIRECTORY;
        $this->ensureDirectoryExists($modelDir);

        $stubFile = self::MODEL_STUB_FILE;
        $fillable = $this->getFillableForModel($this->componentConfig['attributes'] ?? []);
        $relationships = $this->getRelationshipsForModel($this->componentConfig['attributes'] ?? [], $this->moduleName);

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
     * Obtiene la cadena 'fillable' para el modelo a partir de la configuración de atributos.
     *
     * @param array $attributes
     * @return string
     */
    protected function getFillableForModel(array $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }

        // Filtra los atributos que no son 'fillable' (p.ej. ID, relaciones, timestamps)
        $fillableAttributes = collect($attributes)
            ->filter(fn ($attr) => isset($attr['name']) && !in_array($attr['type'], ['id', 'bigIncrements', 'increments', 'foreignId', 'foreign', 'relationship']))
            ->map(fn ($attr) => "'" . $attr['name'] . "'")
            ->implode(', ');

        return $fillableAttributes ? "    protected \$fillable = [{$fillableAttributes}];" : '';
    }

    /**
     * Genera el código para los métodos de relación del modelo.
     *
     * @param array $attributes
     * @param string $module
     * @return string
     */
    protected function getRelationshipsForModel(array $attributes, string $module): string
    {
        if (empty($attributes)) {
            return '';
        }

        $relationshipMethods = collect($attributes)
            ->filter(fn ($attr) => isset($attr['name']) && in_array($attr['type'], ['foreignId', 'foreign']))
            ->map(function ($attr) use ($module) {
                // El nombre de la relación será el nombre de la clave foránea sin _id
                $methodName = Str::camel(Str::before($attr['name'], '_id'));
                // El modelo relacionado se deduce del nombre de la clave foránea o del atributo 'on'
                $relatedModel = Str::studly(Str::before($attr['name'], '_id'));
                $relatedModelClass = "Modules\\{$module}\\Models\\{$relatedModel}";

                // El tipo de relación por defecto para una clave foránea es BelongsTo
                $type = 'BelongsTo';
                $relationCall = "\$this->{$type}(" . $relatedModelClass . "::class)";

                // Añadimos la documentación del método
                $code = "    /**\n";
                $code .= "     * Obtiene el {$relatedModel} que posee este modelo.\n";
                $code .= "     */\n";
                $code .= "    public function {$methodName}(): \\Illuminate\\Database\\Eloquent\\Relations\\{$type}\n";
                $code .= "    {\n";
                $code .= "        return {$relationCall};\n";
                $code .= "    }\n\n";

                return $code;
            })
            ->implode('');

        return $relationshipMethods;
    }
}
