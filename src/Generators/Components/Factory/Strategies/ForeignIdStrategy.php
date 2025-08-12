<?php

namespace Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Contracts\AttributeValueStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\AbstractComponentGenerator;
use Illuminate\Support\Facades\File;

class ForeignIdStrategy implements AttributeValueStrategy
{
    protected string $moduleName;
    protected array $modelUses;
    protected array $componentConfig;
    protected AbstractComponentGenerator $generator;
    protected array $allModules;

    /**
     * @param string $moduleName El nombre del módulo.
     * @param array $modelUses Referencia al array de declaraciones 'use'.
     * @param array $componentConfig El array de configuración completo del componente.
     * @param AbstractComponentGenerator $generator La instancia del generador principal para la salida de la consola.
     */
    public function __construct(string $moduleName, array &$modelUses, array $componentConfig, AbstractComponentGenerator $generator)
    {
        $this->moduleName = $moduleName;
        $this->modelUses = &$modelUses;
        $this->componentConfig = $componentConfig;
        $this->generator = $generator;
        $this->allModules = $this->getAllModules();
    }

    public function generate(array $attribute): string
    {
        $foreignKeyName = $attribute['name'];
        $modelName = null;

        // Paso 1: Buscar el nombre del modelo en el nodo 'relations' del JSON
        foreach ($this->componentConfig['relations'] ?? [] as $relation) {
            if (isset($relation['foreignKey']) && $relation['foreignKey'] === $foreignKeyName) {
                $modelName = $relation['model'];
                break;
            }
        }

        // Paso 2: Si no se encuentra, usar el atributo 'on' o inferir de la convención
        if (is_null($modelName)) {
            $relatedTableName = $attribute['on'] ?? Str::before($foreignKeyName, '_id');
            $modelName = Str::studly(Str::singular($relatedTableName));
        }

        // Paso 3: Buscar el namespace del modelo
        $fullModelNamespace = $this->findModelNamespace($modelName, $this->moduleName, $this->allModules);

        if ($fullModelNamespace) {
            $this->modelUses[] = "use {$fullModelNamespace};";
        } else {
            // Mostrar un mensaje de advertencia detallado en la consola
            $factoryPath = "Modules/{$this->moduleName}/Database/Factories/{$this->generator->factoryName}Factory.php";
            $message = "⚠️ Advertencia en el factory: {$factoryPath}\n"
                     . "No se pudo encontrar el modelo '{$modelName}' en el módulo actual, otros módulos o App\Models. "
                     . "Por favor, ajuste el namespace manualmente en el archivo generado.";
            $this->generator->warn($message);
            
            // Dejar el comentario en el código
            $this->modelUses[] = "/* TODO: Ajustar el namespace para el modelo '{$modelName}' */";
        }

        return "{$modelName}::factory()";
    }

    protected function findModelNamespace(string $modelName, string $currentModuleName, array $allModules): ?string
    {
        // 1. Buscar en el módulo actual
        $moduleModelNamespace = "Modules\\{$currentModuleName}\\Models\\{$modelName}";
        if ($this->classExists($moduleModelNamespace)) {
            return $moduleModelNamespace;
        }

        // 2. Buscar en otros módulos
        foreach ($allModules as $module) {
            if ($module !== $currentModuleName) {
                $otherModuleNamespace = "Modules\\{$module}\\Models\\{$modelName}";
                if ($this->classExists($otherModuleNamespace)) {
                    return $otherModuleNamespace;
                }
            }
        }

        // 3. Buscar en el namespace global del proyecto
        $appModelNamespace = "App\\Models\\{$modelName}";
        if ($this->classExists($appModelNamespace)) {
            return $appModelNamespace;
        }

        return null;
    }

    protected function getAllModules(): array
    {
        if (File::exists(base_path('Modules'))) {
            return array_map('basename', File::directories(base_path('Modules')));
        }
        return [];
    }
    
    protected function classExists(string $class): bool
    {
        return class_exists($class);
    }
}