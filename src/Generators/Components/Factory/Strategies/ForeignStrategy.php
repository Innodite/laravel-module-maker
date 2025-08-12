<?php

namespace Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Contracts\AttributeValueStrategy;

class ForeignStrategy implements AttributeValueStrategy
{
    protected string $moduleName;
    protected array $modelUses;
    protected array $componentConfig;

    /**
     * Inyecta las dependencias necesarias.
     *
     * @param string $moduleName El nombre del módulo.
     * @param array $modelUses Referencia al array de declaraciones 'use'.
     * @param array $componentConfig El array de configuración completo del componente.
     */
    public function __construct(string $moduleName, array &$modelUses, array $componentConfig)
    {
        $this->moduleName = $moduleName;
        $this->modelUses = &$modelUses; // Guardamos la referencia para modificarla.
        $this->componentConfig = $componentConfig;
    }

    /**
     * Genera la llamada a la factory de Eloquent.
     *
     * @param array $attribute La configuración del atributo.
     * @return string La cadena de código PHP.
     */
    public function generate(array $attribute): string
    {
        $foreignKeyName = $attribute['name'];
        $modelName = null;

        // Paso 1: Buscar el nombre del modelo en el nodo 'relations' del JSON.
        foreach ($this->componentConfig['relations'] ?? [] as $relation) {
            if (isset($relation['foreignKey']) && $relation['foreignKey'] === $foreignKeyName) {
                $modelName = $relation['model'];
                break;
            }
        }

        // Paso 2: Si no se encuentra, usar el atributo 'on' del JSON.
        if (is_null($modelName)) {
            $relatedTableName = $attribute['on'] ?? Str::before($foreignKeyName, '_id');
            $modelName = Str::studly(Str::singular($relatedTableName));
        }

        // Paso 3: Registrar el 'use' si el modelo pertenece al módulo actual.
        if ($this->isInternalModel($modelName, $this->componentConfig['components'] ?? [])) {
            $this->modelUses[] = "use Modules\\{$this->moduleName}\\Models\\{$modelName};";
        }

        return "{$modelName}::factory()";
    }

    /**
     * Determina si un modelo pertenece al módulo actual.
     */
    protected function isInternalModel(string $modelName, array $components): bool
    {
        foreach ($components as $component) {
            if ($component['name'] === $modelName) {
                return true;
            }
        }
        return false;
    }
}