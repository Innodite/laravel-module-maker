<?php

namespace Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies;

use Innodite\LaravelModuleMaker\Generators\Components\Factory\Contracts\AttributeValueStrategy;
use Illuminate\Support\Str;

class ForeignIdStrategy implements AttributeValueStrategy
{
    protected string $moduleName;

    public function __construct(string $moduleName)
    {
        $this->moduleName = $moduleName;
    }

    public function generate(array $attribute): string
    {
        $name = $attribute['name'];
        $modelName = Str::studly(Str::before($name, '_id'));
        // La clase FactoryGenerator se encargará de registrar la declaración 'use'.
        return "{$modelName}::factory()";
    }
}