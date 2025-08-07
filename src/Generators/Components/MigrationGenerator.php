<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use InvalidArgumentException;

/**
 * Clase para generar archivos de migración de Laravel de forma dinámica.
 * * Esta versión incluye validación de atributos para evitar errores en la
 * generación de la migración y soporta más tipos de datos comunes.
 */
class MigrationGenerator extends AbstractComponentGenerator
{
    // Constantes para nombres de directorios y archivos de stub
    protected const MIGRATION_DIRECTORY = 'Database/Migrations';
    protected const MIGRATION_STUB_FILE = 'migration.stub';
    protected const TIMESTAMP_FORMAT = 'Y_m_d_His';

    protected string $migrationName;
    protected array $attributes;

    // Propiedades estáticas para asegurar un timestamp único por segundo
    protected static int $migrationCounter = 0;
    protected static ?string $lastTimestamp = null;

    /**
     * Define los atributos y modificadores válidos para cada tipo de dato.
     * Esta es la clave para la validación.
     *
     * @var array
     */
    protected const VALID_ATTRIBUTES = [
        'string' => ['name', 'type', 'length', 'nullable', 'unique', 'default', 'after'],
        'text' => ['name', 'type', 'nullable', 'default', 'after'],
        'integer' => ['name', 'type', 'nullable', 'unique', 'default', 'after'],
        'boolean' => ['name', 'type', 'nullable', 'default', 'after'],
        'timestamp' => ['name', 'type', 'nullable', 'default', 'after'],
        'json' => ['name', 'type', 'nullable', 'default', 'after'],
        'enum' => ['name', 'type', 'options', 'nullable', 'default', 'after'],
        'unsignedBigInteger' => ['name', 'type', 'nullable', 'unique', 'default', 'after'],
        'foreignId' => ['name', 'type', 'on', 'onDelete', 'onUpdate', 'nullable', 'after'],
        'foreign' => ['name', 'type', 'references', 'on', 'onDelete', 'onUpdate', 'nullable', 'after'],
    ];

    public function __construct(
        string $moduleName, 
        string $modulePath, 
        bool $isClean, 
        string $migrationName, 
        array $attributes = [], 
        array $componentConfig = []
    ) {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->migrationName = Str::studly($migrationName);
        $this->attributes = $attributes;
    }

    /**
     * Genera el archivo de migración completo.
     *
     * @return void
     */
    public function generate(): void
    {
        $migrationDirectoryPath = $this->getComponentBasePath() . '/' . self::MIGRATION_DIRECTORY;
        $this->ensureDirectoryExists($migrationDirectoryPath);

        $tableName = Str::snake(Str::plural($this->migrationName));
        $className = 'Create' . Str::studly($tableName) . 'Table';
        $tableSchema = $this->getMigrationSchema($this->attributes);

        $uniqueTimestamp = $this->getUniqueMigrationTimestamp();

        $stubContent = $this->getStubContent(self::MIGRATION_STUB_FILE, $this->isClean, [
            'className' => $className,
            'tableName' => $tableName,
            'tableSchema' => $tableSchema,
        ]);

        $fileName = "{$uniqueTimestamp}_create_{$tableName}_table.php";
        $this->putFile("{$migrationDirectoryPath}/{$fileName}", $stubContent, "Migración '{$tableName}' creada en Modules/{$this->moduleName}/Database/Migrations");
    }

    /**
     * Genera un timestamp único para el nombre del archivo de migración.
     *
     * @return string
     */
    protected function getUniqueMigrationTimestamp(): string
    {
        $currentTimestamp = date(self::TIMESTAMP_FORMAT);

        if (self::$lastTimestamp !== $currentTimestamp) {
            self::$migrationCounter = 0;
            self::$lastTimestamp = $currentTimestamp;
        } else {
            self::$migrationCounter++;
        }

        return $currentTimestamp . sprintf('_%02d', self::$migrationCounter);
    }

    /**
     * Genera el esquema de la tabla de migración a partir de los atributos.
     *
     * @param array $attributes
     * @return string
     */
    protected function getMigrationSchema(array $attributes): string
    {
        $schemaLines = [];
        $schemaLines[] = "\$table->id();";

        foreach ($attributes as $attribute) {
            if (($attribute['type'] ?? null) === 'relationship') {
                continue;
            }

            // Validación: asegura que el tipo y los atributos sean válidos
            $this->validateAttribute($attribute);

            $schemaLines[] = $this->getSchemaLineForAttribute($attribute);
        }

        $schemaLines[] = "\$table->timestamps();";
        return implode("\n            ", $schemaLines);
    }

    /**
     * Valida un atributo del JSON contra los atributos permitidos para su tipo.
     *
     * @param array $attribute
     * @throws InvalidArgumentException
     */
    protected function validateAttribute(array $attribute): void
    {
        $type = $attribute['type'] ?? null;
        if (! $type || ! array_key_exists($type, self::VALID_ATTRIBUTES)) {
            throw new InvalidArgumentException("Tipo de dato '{$type}' no válido o no especificado.");
        }

        $allowedAttributes = self::VALID_ATTRIBUTES[$type];
        foreach ($attribute as $key => $value) {
            if (! in_array($key, $allowedAttributes)) {
                throw new InvalidArgumentException("El atributo '{$key}' no es válido para el tipo de dato '{$type}'.");
            }
        }
        
        // Validaciones específicas
        if ($type === 'enum' && !isset($attribute['options'])) {
            throw new InvalidArgumentException("El tipo de dato 'enum' requiere el atributo 'options' (array de valores).");
        }
    }

    /**
     * Genera la línea de código para un atributo de migración.
     *
     * @param array $attribute
     * @return string
     */
    protected function getSchemaLineForAttribute(array $attribute): string
    {
        $type = $attribute['type'];
        $columnMethod = Str::camel($type) . 'Column';
        
        if (method_exists($this, $columnMethod)) {
            return $this->{$columnMethod}($attribute);
        }
        
        // Si no hay un método especializado, generar una definición genérica
        $name = $attribute['name'] ?? '';
        $definition = "\$table->{$type}('{$name}')";
        return $this->addModifiersToDefinition($definition, $attribute);
    }

    /**
     * Genera la definición de una columna de tipo string.
     *
     * @param array $attribute
     * @return string
     */
    protected function stringColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $length = $attribute['length'] ?? null;
        $definition = $length ? "\$table->string('{$name}', {$length})" : "\$table->string('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    /**
     * Genera la definición de una columna de tipo text.
     *
     * @param array $attribute
     * @return string
     */
    protected function textColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->text('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }
    
    /**
     * Genera la definición de una columna de tipo integer.
     *
     * @param array $attribute
     * @return string
     */
    protected function integerColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->integer('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    /**
     * Genera la definición de una columna de tipo boolean.
     *
     * @param array $attribute
     * @return string
     */
    protected function booleanColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->boolean('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }
    
    /**
     * Genera la definición de una columna de tipo unsignedBigInteger.
     *
     * @param array $attribute
     * @return string
     */
    protected function unsignedBigIntegerColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->unsignedBigInteger('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    /**
     * Genera la definición de una columna de tipo enum.
     *
     * @param array $attribute
     * @return string
     */
    protected function enumColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $options = json_encode($attribute['options']);
        $definition = "\$table->enum('{$name}', {$options})";
        
        return $this->addModifiersToDefinition($definition, $attribute);
    }

    /**
     * Genera la definición de una columna de tipo foreignId (constrained).
     *
     * @param array $attribute
     * @return string
     */
    protected function foreignIdColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->foreignId('{$name}')";
        
        if (isset($attribute['on'])) {
            $definition .= "->constrained('{$attribute['on']}')";
            if (isset($attribute['onDelete'])) {
                $definition .= "->onDelete('{$attribute['onDelete']}')";
            }
            if (isset($attribute['onUpdate'])) {
                $definition .= "->onUpdate('{$attribute['onUpdate']}')";
            }
        }
        
        return $this->addModifiersToDefinition($definition, $attribute, ['nullable', 'after']);
    }

    /**
     * Genera la definición de una columna de tipo foreign (manual).
     *
     * @param array $attribute
     * @return string
     */
    protected function foreignColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $references = $attribute['references'] ?? 'id';
        $on = $attribute['on'] ?? '';
        
        if (empty($on)) {
            throw new InvalidArgumentException("El tipo de dato 'foreign' requiere el atributo 'on' (nombre de la tabla).");
        }
        
        $definition = "\$table->foreign('{$name}')->references('{$references}')->on('{$on}')";

        if (isset($attribute['onDelete'])) {
            $definition .= "->onDelete('{$attribute['onDelete']}')";
        }
        if (isset($attribute['onUpdate'])) {
            $definition .= "->onUpdate('{$attribute['onUpdate']}')";
        }
        
        return $this->addModifiersToDefinition($definition, $attribute, ['nullable', 'after']);
    }

    /**
     * Agrega modificadores comunes a la definición de la columna.
     *
     * @param string $definition
     * @param array $attribute
     * @param array|null $allowedModifiers
     * @return string
     */
    protected function addModifiersToDefinition(string $definition, array $attribute, array $allowedModifiers = null): string
    {
        $type = $attribute['type'];
        $allowedModifiers = $allowedModifiers ?? self::VALID_ATTRIBUTES[$type];

        if (isset($attribute['nullable']) && in_array('nullable', $allowedModifiers) && $attribute['nullable']) {
            $definition .= "->nullable()";
        }
        if (isset($attribute['unique']) && in_array('unique', $allowedModifiers) && $attribute['unique']) {
            $definition .= "->unique()";
        }
        if (isset($attribute['default']) && in_array('default', $allowedModifiers)) {
            $defaultValue = is_string($attribute['default']) ? "'{$attribute['default']}'" : $attribute['default'];
            $definition .= "->default({$defaultValue})";
        }
        if (isset($attribute['after']) && in_array('after', $allowedModifiers)) {
            $definition .= "->after('{$attribute['after']}')";
        }

        return $definition;
    }
}