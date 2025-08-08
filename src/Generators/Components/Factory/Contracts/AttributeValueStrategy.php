<?php

namespace Innodite\LaravelModuleMaker\Generators\Strategies\Factory;

interface AttributeValueStrategy
{
    /**
     * Genera la llamada al método Faker o a la factory de Eloquent
     * para el atributo dado.
     *
     * @param array $attribute La configuración del atributo (ej. ['name' => 'email', 'type' => 'string']).
     * @return string La cadena de código PHP.
     */
    public function generate(array $attribute): string;
}
