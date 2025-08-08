<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use InvalidArgumentException;

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
    protected const RELATION_TYPE_MORPH_TO = 'morphTo';
    protected const RELATION_TYPE_MORPH_TO_MANY = 'morphToMany';

    // Constantes para las clases de relación de Eloquent.
    protected const RELATION_CLASS_NAMESPACE = 'Illuminate\\Database\\Eloquent\\Relations\\';
    protected const RELATION_CLASS_BELONGS_TO_NAME = 'BelongsTo';
    protected const RELATION_CLASS_HAS_MANY_NAME = 'HasMany';
    protected const RELATION_CLASS_HAS_ONE_NAME = 'HasOne';
    protected const RELATION_CLASS_MORPH_ONE_NAME = 'MorphOne';
    protected const RELATION_CLASS_MORPH_MANY_NAME = 'MorphMany';
    protected const RELATION_CLASS_MORPH_TO_NAME = 'MorphTo';
    protected const RELATION_CLASS_MORPH_TO_MANY_NAME = 'MorphToMany';


    protected string $modelName;
    protected array $fillableAttributes;
    protected array $relations;
    protected array $allComponents;
    protected string $componentName;
    protected ?string $tableName = null;

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
        $this->tableName = $componentConfig['table'] ?? null;
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
            'namespace' => $this->getNamespace(),
            'modelName' => $this->modelName,
            'table' => $this->getTableProperty(),
            'fillable' => $this->getFillableProperty(),
            'useStatements' => $useStatements,
            'relations' => $relationsMethods,
        ]);

        $this->putFile("{$modelDirectoryPath}/{$this->modelName}.php", $stubContent, "Modelo '{$this->modelName}' creado en Modules/{$this->moduleName}/Models");
    }

    /**
     * Obtiene el namespace correcto para el modelo.
     *
     * @return string
     */
    protected function getNamespace(): string
    {
        return "Modules\\{$this->moduleName}\\Models";
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
     * Genera la propiedad `$table` si se ha definido en la configuración.
     *
     * @return string
     */
    protected function getTableProperty(): string
    {
        if ($this->tableName) {
            return "protected \$table = '{$this->tableName}';\n";
        }

        return '';
    }

     /**
     * Genera las declaraciones 'use' para los modelos relacionados y las clases de relación.
     *
     * @return string
     */
    protected function getUseStatements(): string
    {
        $useStatements = [];
        $relationClasses = [];

        // Mapeo de tipos de relación a nombres de clase de Eloquent
        $relationTypeMap = [
            self::RELATION_TYPE_BELONGS_TO => self::RELATION_CLASS_BELONGS_TO_NAME,
            self::RELATION_TYPE_HAS_MANY => self::RELATION_CLASS_HAS_MANY_NAME,
            self::RELATION_TYPE_HAS_ONE => self::RELATION_CLASS_HAS_ONE_NAME,
            self::RELATION_TYPE_MORPH_ONE => self::RELATION_CLASS_MORPH_ONE_NAME,
            self::RELATION_TYPE_MORPH_MANY => self::RELATION_CLASS_MORPH_MANY_NAME,
            self::RELATION_TYPE_MORPH_TO => self::RELATION_CLASS_MORPH_TO_NAME,
            self::RELATION_TYPE_MORPH_TO_MANY => self::RELATION_CLASS_MORPH_TO_MANY_NAME,
        ];
        
        // Extrae los nombres de los componentes del módulo para la verificación.
        $componentNames = collect($this->allComponents)
            ->pluck('name')
            ->toArray();


        foreach ($this->relations as $relation) {
            // Recolecta los modelos relacionados.
            if (isset($relation['model']) && !empty($relation['model'])) {
                $relatedModel = $relation['model'];
                
                // Siempre usa el namespace del módulo para los modelos, ya que la herramienta
                // está diseñada para generarlos dentro de la estructura de módulos.
                $relatedModelNamespace = "Modules\\{$this->moduleName}\\Models\\{$relatedModel}";
                
                $useStatements[] = "use {$relatedModelNamespace};";
            }

            // Recolecta los tipos de relación de Eloquent usando el mapeo.
            if (isset($relation['type']) && isset($relationTypeMap[$relation['type']])) {
                $relationClassName = $relationTypeMap[$relation['type']];
                $relationClasses[] = "use " . self::RELATION_CLASS_NAMESPACE . $relationClassName . ";";
            }
        }
        
        $allStatements = array_merge($useStatements, $relationClasses);
        $uniqueStatements = array_unique($allStatements);
        
        // Retorna las sentencias 'use' ordenadas para una mejor legibilidad.
        sort($uniqueStatements);

        if (empty($uniqueStatements)) {
            return '';
        }

        return implode("\n", $uniqueStatements);
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
            
            $relatedModel = $relation['model'] ?? null;

            // Obtiene el nombre corto de la clase de relación
            $relationClassName = Str::studly($relationType);
            
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
                    $relationBody = $this->generateMorphOneMethodBody($relation, $relatedModel);
                    break;
                case self::RELATION_TYPE_MORPH_MANY:
                    $relationBody = $this->generateMorphManyMethodBody($relation, $relatedModel);
                    break;
                case self::RELATION_TYPE_MORPH_TO:
                    $relationBody = $this->generateMorphToMethodBody($relation);
                    break;
                case self::RELATION_TYPE_MORPH_TO_MANY:
                    $relationBody = $this->generateMorphToManyMethodBody($relation, $relatedModel);
                    break;
                default:
                    throw new InvalidArgumentException("Tipo de relación '{$relationType}' no reconocido para la relación '{$methodName}' en el componente '{$this->componentName}'.");
            }
            
            $methodSignature = "public function {$methodName}(): " . ($relationType === self::RELATION_TYPE_MORPH_TO ? self::RELATION_CLASS_MORPH_TO_NAME : $relationClassName);

            $methods[] = "
    /**
     * Obtiene el/los modelo(s) relacionado(s).
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

        $relationType = $relation['type'];
        $relationName = $relation['name'];

        // Validación para relaciones polimórficas que no requieren 'model'
        $polymorphicWithoutModel = [self::RELATION_TYPE_MORPH_TO];
        
        // Validación de la presencia de 'model'
        if (!in_array($relationType, $polymorphicWithoutModel)) {
            if (!isset($relation['model']) || empty($relation['model'])) {
                throw new InvalidArgumentException("La relación '{$relationName}' en el componente '{$this->componentName}' de tipo '{$relationType}' requiere un atributo 'model'. Por favor, revisa tu archivo de configuración JSON.");
            }
        }
        
        // Validación específica para relaciones polimórficas que requieren 'morphName'
        $polymorphicWithMorphName = [self::RELATION_TYPE_MORPH_ONE, self::RELATION_TYPE_MORPH_MANY, self::RELATION_TYPE_MORPH_TO, self::RELATION_TYPE_MORPH_TO_MANY];
        if (in_array($relationType, $polymorphicWithMorphName)) {
            // `morphTo` no requiere `morphName` si el nombre de la relación coincide con el nombre de la columna.
            // Para simplificar la validación, lo hacemos opcional en este caso.
            if ($relationType !== self::RELATION_TYPE_MORPH_TO) {
                if (!isset($relation['morphName']) || empty($relation['morphName'])) {
                    throw new InvalidArgumentException("La relación '{$relationName}' en el componente '{$this->componentName}' de tipo '{$relationType}' requiere un atributo 'morphName'. Por favor, revisa tu archivo de configuración JSON.");
                }
            }
        }

        // Validación de atributos incorrectos para tipos específicos
        switch ($relationType) {
            case self::RELATION_TYPE_BELONGS_TO:
                if (isset($relation['morphName'])) {
                    throw new InvalidArgumentException("El atributo 'morphName' no es válido para la relación '{$relationName}' de tipo 'belongsTo' en el componente '{$this->componentName}'.");
                }
                break;
            case self::RELATION_TYPE_HAS_MANY:
            case self::RELATION_TYPE_HAS_ONE:
                if (isset($relation['ownerKey']) || isset($relation['morphName'])) {
                    throw new InvalidArgumentException("Los atributos 'ownerKey' y 'morphName' no son válidos para la relación '{$relationName}' de tipo '{$relationType}' en el componente '{$this->componentName}'.");
                }
                break;
            case self::RELATION_TYPE_MORPH_ONE:
            case self::RELATION_TYPE_MORPH_MANY:
            case self::RELATION_TYPE_MORPH_TO_MANY:
                if (isset($relation['localKey']) || isset($relation['ownerKey'])) {
                    throw new InvalidArgumentException("Los atributos 'localKey' y 'ownerKey' no son válidos para la relación '{$relationName}' de tipo '{$relationType}' en el componente '{$this->componentName}'.");
                }
                break;
            case self::RELATION_TYPE_MORPH_TO:
                if (isset($relation['model']) || isset($relation['foreignKey']) || isset($relation['localKey']) || isset($relation['ownerKey'])) {
                    throw new InvalidArgumentException("Los atributos 'model', 'foreignKey', 'localKey' y 'ownerKey' no son válidos para la relación '{$relationName}' de tipo 'morphTo' en el componente '{$this->componentName}'.");
                }
                break;
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
     * @param string $relatedModel
     * @return string
     */
    private function generateMorphOneMethodBody(array $relation, string $relatedModel): string
    {
        $params = ["{$relatedModel}::class", "'{$relation['morphName']}'"];
        
        $eloquentMethod = Str::camel($relation['type']);
        return "return \$this->{$eloquentMethod}(" . implode(', ', $params) . ");";
    }

    /**
     * Genera el cuerpo del método para la relación morphMany.
     *
     * @param array $relation
     * @param string $relatedModel
     * @return string
     */
    private function generateMorphManyMethodBody(array $relation, string $relatedModel): string
    {
        $params = ["{$relatedModel}::class", "'{$relation['morphName']}'"];
        
        $eloquentMethod = Str::camel($relation['type']);
        return "return \$this->{$eloquentMethod}(" . implode(', ', $params) . ");";
    }

    /**
     * Genera el cuerpo del método para la relación morphTo.
     *
     * @param array $relation
     * @return string
     */
    private function generateMorphToMethodBody(array $relation): string
    {
        $params = [];

        // El `morphName` es opcional, ya que puede inferirse del nombre del método.
        if (isset($relation['morphName'])) {
            $params[] = "'{$relation['morphName']}'";
        } else {
            $params[] = "'{$relation['name']}'";
        }

        $eloquentMethod = Str::camel($relation['type']);
        return "return \$this->{$eloquentMethod}(" . implode(', ', $params) . ");";
    }
    
    /**
     * Genera el cuerpo del método para la relación morphToMany.
     *
     * @param array $relation
     * @param string $relatedModel
     * @return string
     */
    private function generateMorphToManyMethodBody(array $relation, string $relatedModel): string
    {
        $params = ["{$relatedModel}::class", "'{$relation['morphName']}'"];
        
        if (isset($relation['table'])) {
            $params[] = "'{$relation['table']}'";
        }
        if (isset($relation['foreignPivotKey'])) {
            $params[] = "'{$relation['foreignPivotKey']}'";
        }
        if (isset($relation['relatedPivotKey'])) {
            $params[] = "'{$relation['relatedPivotKey']}'";
        }

        $eloquentMethod = Str::camel($relation['type']);
        return "return \$this->{$eloquentMethod}(" . implode(', ', $params) . ");";
    }
}
