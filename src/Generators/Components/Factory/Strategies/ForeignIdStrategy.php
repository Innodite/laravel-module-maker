<?php

namespace Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies;

use Innodite\LaravelModuleMaker\Generators\Components\Factory\Contracts\AttributeValueStrategy;
use Illuminate\Support\Str;

class ForeignIdStrategy implements AttributeValueStrategy
{
    protected string $moduleName;
    protected array $modelUses;
    protected array $componentConfig;

    // Inyectamos todas las dependencias que la estrategia necesita.
    public function __construct(string $moduleName, array &$modelUses, array $componentConfig)
    {
        $this->moduleName = $moduleName;
        $this->modelUses = &$modelUses; // Guardamos la referencia para modificarla.
        $this->componentConfig = $componentConfig;
    }

    public function generate(array $attribute): string
    {
        $foreignKeyName = $attribute['name'];
        $modelName = null;

        // Búsqueda en el nodo 'relations'.
        foreach ($this->componentConfig['relations'] ?? [] as $relation) {
            if (isset($relation['foreignKey']) && $relation['foreignKey'] === $foreignKeyName) {
                $modelName = $relation['model'];
                break;
            }
        }

        // Regreso a la convención de Laravel si no se encuentra en 'relations'.
        if (is_null($modelName)) {
            $relatedTableName = $attribute['on'] ?? Str::before($foreignKeyName, '_id');
            $modelName = Str::studly(Str::singular($relatedTableName));
        }

        // Lógica para registrar el 'use' sin excepciones codificadas.
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