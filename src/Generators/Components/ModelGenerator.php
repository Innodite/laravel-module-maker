<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use InvalidArgumentException;

/**
 * Clase para generar un modelo Eloquent para un módulo.
 *
 * Esta versión se ha mejorado para manejar relaciones explícitamente definidas
 * en el archivo de configuración JSON, permitiendo relaciones complejas
 * como One-to-Many (`hasMany`) y la definición de las claves foráneas
 * si no siguen las convenciones de Laravel.
 */
class ModelGenerator extends AbstractComponentGenerator
{
    use HasStubs;

    protected const MODEL_DIRECTORY = 'Models';
    protected const MODEL_STUB_FILE = 'model.stub';
    
    protected string $modelName;
    protected array $fillableAttributes;
    protected array $relations;
    protected array $allComponents;
    protected string $componentName;

    /**
     * Constructor para el generador de modelos.
     *
     * @param string $moduleName El nombre del módulo.
     * @param string $modulePath La ruta base del módulo.
     * @param bool $isClean Indica si el generador se ejecuta en modo "limpio".
     * @param string $componentName El nombre del componente (p.ej., 'Post').
     * @param array $attributes Los atributos del modelo.
     * @param array $relations Las relaciones del modelo, definidas explícitamente.
     * @param array $allComponents Todos los componentes del módulo para referenciar otros modelos.
     * @param array $componentConfig Configuración adicional del componente.
     */
    public function __construct(
        string $moduleName,
        string $modulePath,
        bool $isClean,
        string $componentName,
        array $attributes = [],
        array $relations = [],
        array $allComponents = [],
        array $componentConfig = []
    ) {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->modelName = Str::studly($componentName);
        $this->fillableAttributes = $this->getFillableAttributes($attributes);
        $this->relations = $relations;
        $this->allComponents = $allComponents;
        $this->componentName = $componentName;
    }

    /**
     * Genera el archivo del modelo.
     *
     * @return void
     */
    public function generate(): void
    {
        $modelDirectoryPath = $this->getComponentBasePath() . '/' . self::MODEL_DIRECTORY;
        $this->ensureDirectoryExists($modelDirectoryPath);

        $stubContent = $this->getStubContent(self::MODEL_STUB_FILE, $this->isClean, [
            'modelName' => $this->modelName,
            'fillable' => $this->getFillableProperty(),
            'relations' => $this->getRelationsMethods(),
        ]);

        $this->putFile("{$modelDirectoryPath}/{$this->modelName}.php", $stubContent, "Modelo '{$this->modelName}' creado en Modules/{$this->moduleName}/Models");
    }

    /**
     * Extrae los atributos "fillable" de la configuración.
     *
     * @param array $attributes
     * @return array
     */
    protected function getFillableAttributes(array $attributes): array
    {
        $fillable = [];
        foreach ($attributes as $attribute) {
            $type = $attribute['type'] ?? '';
            // Excluimos los campos ID, timestamps, y foreign keys para que no sean fillable.
            if (!in_array($type, ['id', 'timestamps', 'foreignId'])) {
                $name = $attribute['name'] ?? null;
                if ($name) {
                    $fillable[] = $name;
                }
            }
        }
        return $fillable;
    }
    
    /**
     * Genera el string para la propiedad `$fillable`.
     *
     * @return string
     */
    protected function getFillableProperty(): string
    {
        return empty($this->fillableAttributes)
            ? 'protected $guarded = [];'
            : "protected \$fillable = ['" . implode("', '", $this->fillableAttributes) . "'];";
    }

    /**
     * Genera los métodos de relaciones de Eloquent.
     *
     * @return string
     */
    protected function getRelationsMethods(): string
    {
        $methods = [];
        
        foreach ($this->relations as $relation) {
            $methodName = $relation['name'] ?? null;
            $relationType = $relation['type'] ?? null;
            $relatedModel = $relation['model'] ?? null;
            
            if (!$methodName || !$relationType || !$relatedModel) {
                continue;
            }
            
            $relatedComponent = collect($this->allComponents)->firstWhere('name', $relatedModel);
            $relatedModelNamespace = $relatedComponent ? "Modules\\{$this->moduleName}\\Models\\{$relatedModel}" : "App\\Models\\{$relatedModel}";
            
            $relationBaseClass = "\\Illuminate\\Database\\Eloquent\\Relations\\" . Str::studly($relationType);
            
            $eloquentMethod = Str::camel($relationType);
            
            $params = [$relatedModelNamespace . '::class'];
            
            // Añadir foreignKey si se proporciona
            if (isset($relation['foreignKey'])) {
                $params[] = "'{$relation['foreignKey']}'";
            }
            
            // Añadir localKey o ownerKey si se proporciona
            if (isset($relation['localKey']) || isset($relation['ownerKey'])) {
                if ($relationType === 'hasMany' || $relationType === 'hasOne' || $relationType === 'morphMany' || $relationType === 'morphOne') {
                    if (isset($relation['localKey'])) {
                        $params[] = "'{$relation['localKey']}'";
                    }
                } else if ($relationType === 'belongsTo' || $relationType === 'morphTo') {
                    if (isset($relation['ownerKey'])) {
                        $params[] = "'{$relation['ownerKey']}'";
                    }
                }
            }
            
            $methodSignature = "public function {$methodName}(): {$relationBaseClass}";
            $methodBody = "return \$this->{$eloquentMethod}(" . implode(', ', $params) . ")";
            
            $methodBody .= ';';
            
            $methods[] = "
    /**
     * Obtiene el/los modelo(s) '{$relatedModel}' relacionado(s).
     */
    {$methodSignature}
    {
        {$methodBody}
    }";
        }
        
        return implode("\n\n", $methods);
    }
}
