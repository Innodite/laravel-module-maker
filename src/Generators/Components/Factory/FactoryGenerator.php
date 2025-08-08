<?php

namespace Innodite\LaravelModuleMaker\Generators\Components\Factory;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\Factory\Contracts\AttributeValueStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\Factory\BooleanStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\Factory\DateStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\Factory\EnumStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\Factory\ForeignIdStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\Factory\ForeignStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\Factory\IntegerStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\Factory\TextStrategy;

class FactoryGenerator extends AbstractComponentGenerator
{
    protected const FACTORY_PATH_SUFFIX = "/Database/Factories";
    protected const FACTORY_FILE_SUFFIX = "Factory.php";
    protected const STUB_FILE = 'factory.stub';

    protected string $factoryName;
    protected string $modelName;
    protected array $attributes;
    protected array $modelUses = [];

    protected array $strategies = [];

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $factoryName, string $modelName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->factoryName = Str::studly($factoryName);
        $this->modelName = Str::studly($modelName);
        $this->attributes = $componentConfig['attributes'] ?? [];

        // Inicializa el mapa de estrategias.
        $this->strategies = [
            'text' => new TextStrategy(),
            'integer' => new IntegerStrategy(),
            'tinyInteger' => new IntegerStrategy(),
            'smallInteger' => new IntegerStrategy(),
            'mediumInteger' => new IntegerStrategy(),
            'bigInteger' => new IntegerStrategy(),
            'unsignedBigInteger' => new IntegerStrategy(),
            'boolean' => new BooleanStrategy(),
            'timestamp' => new DateStrategy(),
            'date' => new DateStrategy(),
            'dateTime' => new DateStrategy(),
            'enum' => new EnumStrategy(),
            'foreignId' => new ForeignIdStrategy($this->moduleName),
            'foreign' => new ForeignStrategy($this->moduleName),
        ];
    }

    public function generate(): void
    {
        $factoryDir = $this->getComponentBasePath() . self::FACTORY_PATH_SUFFIX;
        $this->ensureDirectoryExists($factoryDir);
        $definitionAttributes = $this->generateDefinitionAttributes();
        $modelUsesString = implode("\n", array_unique($this->modelUses));

        $stub = $this->getStubContent(self::STUB_FILE, $this->isClean, [
            'module' => $this->moduleName,
            'namespace' => "Modules\\{$this->moduleName}\\Database\\Factories",
            'factoryName' => $this->factoryName,
            'modelName' => $this->modelName,
            'modelUses' => $modelUsesString,
            'definitionAttributes' => $definitionAttributes,
        ]);

        $this->putFile("{$factoryDir}/{$this->factoryName}" . self::FACTORY_FILE_SUFFIX, $stub, "Factory {$this->factoryName} creado en Modules/{$this->moduleName}/Database/Factories");
    }

    protected function generateDefinitionAttributes(): string
    {
        $lines = [];
        foreach ($this->attributes as $attribute) {
            $name = $attribute['name'];
            if (in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            $fakerMethod = $this->generateAttributeValue($attribute);
            $lines[] = "            '{$name}' => {$fakerMethod},";
        }
        return implode("\n", $lines);
    }

    protected function generateAttributeValue(array $attribute): string
    {
        $name = $attribute['name'];
        $type = $attribute['type'] ?? 'string';

        $fakerMethod = $this->mapCommonAttributeNamesToFaker($name);
        if ($fakerMethod) {
            return $fakerMethod;
        }

        // Obtiene la estrategia adecuada.
        $strategy = $this->strategies[$type] ?? null;
        if ($strategy instanceof AttributeValueStrategy) {
            // Si es una clave foránea, guarda la declaración 'use'.
            if (in_array($type, ['foreignId', 'foreign'])) {
                $tableName = $type === 'foreignId' ? Str::before($name, '_id') : ($attribute['on'] ?? 'users');
                $modelName = Str::studly(Str::singular($tableName));
                $this->modelUses[] = "use Modules\\{$this->moduleName}\\Models\\{$modelName};";
            }
            return $strategy->generate($attribute);
        }

        // Estrategia por defecto para tipos no definidos.
        return '$this->faker->word()';
    }

    protected function mapCommonAttributeNamesToFaker(string $name): ?string
    {
        if (Str::contains($name, 'email')) {
            return '$this->faker->unique()->safeEmail()';
        }
        if (Str::contains($name, 'password')) {
            return '$this->faker->password()';
        }
        if (Str::contains($name, 'title')) {
            return '$this->faker->sentence()';
        }
        if (Str::contains($name, 'name')) {
            return '$this->faker->name()';
        }
        return null;
    }
}
