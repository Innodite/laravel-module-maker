<?php

namespace Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies;

use Innodite\LaravelModuleMaker\Generators\Components\Factory\Contracts\AttributeValueStrategy;
 

class EnumStrategy implements AttributeValueStrategy
{
    public function generate(array $attribute): string
    {
        $options = $attribute['options'] ?? "['value1', 'value2']";
        return '$this->faker->randomElement(' . json_encode($options) . ')';
    }
}