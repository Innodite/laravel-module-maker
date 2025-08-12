<?php

namespace Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Contracts\AttributeValueStrategy;

class ForeignIdStrategy implements AttributeValueStrategy
{
    // ...
    // Constructor y propiedades se mantienen igual
    // ...

    public function generate(array $attribute): string
    {
        // ... (Lógica para determinar el nombre del modelo, se mantiene igual)

        // Nuevo: Buscar el namespace en la nueva jerarquía
        $allModules = $this->getAllModules(); // Asume que tienes un método para obtener todos los módulos
        $fullModelNamespace = $this->findModelNamespace($modelName, $this->moduleName, $allModules);
        
        if ($fullModelNamespace) {
            $this->modelUses[] = "use {$fullModelNamespace};";
        } else {
            $this->modelUses[] = "/* TODO: Ajustar el namespace para el modelo '{$modelName}' */";
            // Lógica para mostrar un mensaje atractivo en la consola
        }

        return "{$modelName}::factory()";
    }

    /**
     * Busca el namespace completo de un modelo en el módulo actual, otros módulos o el proyecto.
     *
     * @param string $modelName El nombre del modelo.
     * @param string $currentModuleName El nombre del módulo actual.
     * @param array $allModules La lista de todos los módulos.
     * @return string|null El namespace completo del modelo o null.
     */
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

    /**
     * Este método debería ser proporcionado por la clase base o un trait.
     * Por ejemplo, podría usar File::directories('path/to/modules').
     */
    protected function getAllModules(): array
    {
        // Implementación de ejemplo
        // return array_map('basename', File::directories(base_path('Modules')));
        return ['UserManagement', 'Blog', 'Shop']; // Ejemplo de módulos
    }

    /**
     * Verifica si una clase existe.
     * ... (se mantiene igual)
     */
    protected function classExists(string $class): bool
    {
        return class_exists($class);
    }
}