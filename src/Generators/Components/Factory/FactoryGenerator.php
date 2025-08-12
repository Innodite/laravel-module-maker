<?php

namespace Innodite\LaravelModuleMaker\Generators\Components\Factory;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Contracts\AttributeValueStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\BooleanStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\DateStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\EnumStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\ForeignIdStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\ForeignStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\IntegerStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\Factory\Strategies\TextStrategy;
use Innodite\LaravelModuleMaker\Generators\Components\AbstractComponentGenerator;

class FactoryGenerator extends AbstractComponentGenerator
{
    protected const FACTORY_PATH_SUFFIX = "/Database/Factories";
    protected const FACTORY_FILE_SUFFIX = "Factory.php";
    protected const STUB_FILE = 'factory.stub';

    public string $factoryName;
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
            'foreignId' => new ForeignIdStrategy($this->moduleName, $this->modelUses, $componentConfig, $this),
            'foreign' => new ForeignStrategy($this->moduleName, $this->modelUses, $componentConfig, $this),
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

        $this->putFile("{$factoryDir}/{$this->factoryName}" . self::FACTORY_FILE_SUFFIX, $stub, "Factory {$this->factoryName} creada en Modules/{$this->moduleName}/Database/Factories");
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
            $lines[] = "'{$name}' => {$fakerMethod},";
        }
        $firstLine = array_shift($lines);
        $otherLines = implode("\n            ", $lines);
        return $firstLine."            " . "\n            " . $otherLines;
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
        }if (Str::contains($name, 'slug')) {
            return '$this->faker->name()';
        }
        return null;
    }
}