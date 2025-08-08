<?php

namespace Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies;

use Innodite\LaravelModuleMaker\Generators\Components\Factory\Contracts\AttributeValueStrategy;
 

class ForeignStrategy implements AttributeValueStrategy
{
    protected string $moduleName;

    public function __construct(string $moduleName)
    {
        $this->moduleName = $moduleName;
    }

    public function generate(array $attribute): string
    {
        $tableName = $attribute['on'] ?? 'users';
        $modelName = Str::studly(Str::singular($tableName));
        // La clase FactoryGenerator se encargará de registrar la declaración 'use'.
        return "{$modelName}::factory()";
    }
}