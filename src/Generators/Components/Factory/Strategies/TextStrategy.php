<?php

namespace Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies;

use Innodite\LaravelModuleMaker\Generators\Components\Factory\Contracts\AttributeValueStrategy;
 

class TextStrategy implements AttributeValueStrategy
{
    public function generate(array $attribute): string
    {
        return '$this->faker->paragraph()';
    }
}