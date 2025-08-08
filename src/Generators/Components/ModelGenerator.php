<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use InvalidArgumentException;

/**
 * Clase refactorizada para generar un modelo Eloquent para un módulo.
 *
 * Esta versión incluye:
 * - Uso de constantes para una mejor mantenibilidad.
 * - Lógica de relaciones separada para hasOne y hasMany.
 * - Soporte para relaciones polimórficas (morphOne y morphMany).
 */
class ModelGenerator extends AbstractComponentGenerator
{
    use HasStubs;

    // Constantes para nombres de directorios y archivos.
    protected const MODEL_DIRECTORY = 'Models';
    protected const MODEL_STUB_FILE = 'model.stub';
    
    // Constantes para tipos de atributos y relaciones.
    protected const ATTRIBUTE_TYPE_ID = 'id';
    protected const ATTRIBUTE_TYPE_TIMESTAMPS = 'timestamps';
    protected const ATTRIBUTE_TYPE_FOREIGN_ID = 'foreignId';

    protected const RELATION_TYPE_BELONGS_TO = 'belongsTo';
    protected const RELATION_TYPE_HAS_MANY = 'hasMany';
    protected const RELATION_TYPE_HAS_ONE = 'hasOne';
    protected const RELATION_TYPE_MORPH_ONE = 'morphOne';
    protected const RELATION_TYPE_MORPH_MANY = 'morphMany';

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
     * @param array $relations Las relaciones del modelo.
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

        $useStatements = $this->getUseStatements();
        $relationsMethods = $this->getRelationsMethods();

        $stubContent = $this->getStubContent(self::MODEL_STUB_FILE, $this->isClean, [
            'modelName' => $this->modelName,
            'fillable' => $this->getFillableProperty(),
            'useStatements' => $useStatements,
            'relations' => $relationsMethods,
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
            if (!in_array($type, [self::ATTRIBUTE_TYPE_ID, self::ATTRIBUTE_TYPE_TIMESTAMPS, self::ATTRIBUTE_TYPE_FOREIGN_ID])) {
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
     * Genera las declaraciones 'use' para los modelos relacionados.
     *
     * @return string
     */
    protected function getUseStatements(): string
    {
        $useStatements = [];
        foreach ($this->relations as $relation) {
            $relatedModel = $relation['model'];
            $relatedComponent = collect($this->allComponents)->firstWhere('name', $relatedModel);
            $relatedModelNamespace = $relatedComponent ? "Modules\\{$this->moduleName}\\Models\\{$relatedModel}" : "App\\Models\\{$relatedModel}";
            
            // Para relaciones morph, el modelo no está en el mismo namespace que el módulo.
            if (!in_array($relation['type'], [self::RELATION_TYPE_MORPH_ONE, self::RELATION_TYPE_MORPH_MANY])) {
                 $useStatements[] = "use {$relatedModelNamespace};";
            }
        }
        
        if (empty($useStatements)) {
            return '';
        }

        return implode("\n", array_unique($useStatements));
    }

    /**
     * Genera los métodos de relaciones de Eloquent, con validación de entrada.
     *
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getRelationsMethods(): string
    {
        $methods = [];
        
        foreach ($this->relations as $relation) {
            $this->validateRelationConfig($relation);

            $methodName = $relation['name'];
            $relationType = $relation['type'];
            $relatedModel = $relation['model'];
            
            $relationBaseClass = "\\Illuminate\\Database\\Eloquent\\Relations\\" . Str::studly($relationType);
            
            $relationBody = '';
            switch ($relationType) {
                case self::RELATION_TYPE_HAS_MANY:
                    $relationBody = $this->generateHasManyMethodBody($relation, $relatedModel);
                    break;
                case self::RELATION_TYPE_HAS_ONE:
                    $relationBody = $this->generateHasOneMethodBody($relation, $relatedModel);
                    break;
                case self::RELATION_TYPE_BELONGS_TO:
                    $relationBody = $this->generateBelongsToMethodBody($relation, $relatedModel);
                    break;
                case self::RELATION_TYPE_MORPH_ONE:
                    $relationBody = $this->generateMorphOneMethodBody($relation);
                    break;
                case self::RELATION_TYPE_MORPH_MANY:
                    $relationBody = $this->generateMorphManyMethodBody($relation);
                    break;
                default:
                    $relationBody = '';
            }

            $methodSignature = "public function {$methodName}(): {$relationBaseClass}";
            
            $methods[] = "
    /**
     * Obtiene el/los modelo(s) '{$relatedModel}' relacionado(s).
     */
    {$methodSignature}
    {
        {$relationBody}
    }";
        }
        
        return implode("\n\n", $methods);
    }
    
    /**
     * Valida la configuración de la relación.
     *
     * @param array $relation
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateRelationConfig(array $relation): void
    {
        if (!isset($relation['name']) || empty($relation['name'])) {
            throw new InvalidArgumentException("La relación en el componente '{$this->componentName}' no tiene un 'name' válido definido. Por favor, revisa tu archivo de configuración JSON.");
        }
        if (!isset($relation['type']) || empty($relation['type'])) {
            throw new InvalidArgumentException("La relación '{$relation['name']}' en el componente '{$this->componentName}' no tiene un tipo de relación ('type') válido definido. Por favor, revisa tu archivo de configuración JSON.");
        }
        if (!isset($relation['model']) || empty($relation['model'])) {
            // La validación del modelo es diferente para relaciones polimórficas.
            if (!in_array($relation['type'], [self::RELATION_TYPE_MORPH_ONE, self::RELATION_TYPE_MORPH_MANY])) {
                 throw new InvalidArgumentException("La relación '{$relation['name']}' en el componente '{$this->componentName}' no tiene un modelo ('model') válido definido. Por favor, revisa tu archivo de configuración JSON.");
            }
        }
    }

    /**
     * Genera el cuerpo del método para la relación hasMany.
     *
     * @param array $relation
     * @param string $relatedModel
     * @return string
     */
    private function generateHasManyMethodBody(array $relation, string $relatedModel): string
    {
        $params = ["{$relatedModel}::class"];

        if (isset($relation['foreignKey'])) {
            $params[] = "'{$relation['foreignKey']}'";
        }

        if (isset($relation['localKey'])) {
            $params[] = "'{$relation['localKey']}'";
        }
        
        $eloquentMethod = Str::camel($relation['type']);
        return "return \$this->{$eloquentMethod}(" . implode(', ', $params) . ");";
    }

    /**
     * Genera el cuerpo del método para la relación hasOne.
     *
     * @param array $relation
     * @param string $relatedModel
     * @return string
     */
    private function generateHasOneMethodBody(array $relation, string $relatedModel): string
    {
        $params = ["{$relatedModel}::class"];

        if (isset($relation['foreignKey'])) {
            $params[] = "'{$relation['foreignKey']}'";
        }

        if (isset($relation['localKey'])) {
            $params[] = "'{$relation['localKey']}'";
        }
        
        $eloquentMethod = Str::camel($relation['type']);
        return "return \$this->{$eloquentMethod}(" . implode(', ', $params) . ");";
    }

    /**
     * Genera el cuerpo del método para la relación belongsTo.
     *
     * @param array $relation
     * @param string $relatedModel
     * @return string
     */
    private function generateBelongsToMethodBody(array $relation, string $relatedModel): string
    {
        $params = ["{$relatedModel}::class"];
        
        if (isset($relation['foreignKey'])) {
            $params[] = "'{$relation['foreignKey']}'";
        }

        if (isset($relation['ownerKey'])) {
            if (!isset($relation['foreignKey'])) {
                $params[] = "null";
            }
            $params[] = "'{$relation['ownerKey']}'";
        }
        
        $eloquentMethod = Str::camel($relation['type']);
        return "return \$this->{$eloquentMethod}(" . implode(', ', $params) . ");";
    }

    /**
     * Genera el cuerpo del método para la relación morphOne.
     *
     * @param array $relation
     * @return string
     */
    private function generateMorphOneMethodBody(array $relation): string
    {
        // Se valida el nombre de la relación polimórfica, que es el "morph name".
        if (!isset($relation['morphName'])) {
            throw new InvalidArgumentException("La relación '{$relation['name']}' es de tipo 'morphOne' pero no tiene el parámetro 'morphName' definido.");
        }
        
        $params = ["'{$relation['morphName']}'"];
        
        $eloquentMethod = Str::camel($relation['type']);
        return "return \$this->{$eloquentMethod}(" . implode(', ', $params) . ");";
    }

    /**
     * Genera el cuerpo del método para la relación morphMany.
     *
     * @param array $relation
     * @return string
     */
    private function generateMorphManyMethodBody(array $relation): string
    {
        // Se valida el nombre de la relación polimórfica, que es el "morph name".
        if (!isset($relation['morphName'])) {
            throw new InvalidArgumentException("La relación '{$relation['name']}' es de tipo 'morphMany' pero no tiene el parámetro 'morphName' definido.");
        }
        
        $params = ["'{$relation['morphName']}'"];
        
        $eloquentMethod = Str::camel($relation['type']);
        return "return \$this->{$eloquentMethod}(" . implode(', ', $params) . ");";
    }
}
