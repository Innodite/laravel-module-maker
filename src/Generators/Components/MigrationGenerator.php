<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use InvalidArgumentException;

/**
 * Clase para generar archivos de migración de Laravel de forma dinámica.
 * * Esta versión incluye validación de atributos para evitar errores en la
 * generación de la migración y soporta más tipos de datos comunes, así como la
 * creación de índices simples y compuestos.
 */
class MigrationGenerator extends AbstractComponentGenerator
{
    // Constantes para nombres de directorios y archivos de stub
    protected const MIGRATION_DIRECTORY = 'Database/Migrations';
    protected const MIGRATION_STUB_FILE = 'migration.stub';
    protected const TIMESTAMP_FORMAT = 'Y_m_d_His';

    protected string $migrationName;
    protected array $attributes;
    protected array $indexes;

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
        'char' => ['name', 'type', 'length', 'nullable', 'unique', 'default', 'after'],
        'uuid' => ['name', 'type', 'nullable', 'unique', 'default', 'after'],
        'integer' => ['name', 'type', 'nullable', 'unique', 'default', 'after'],
        'tinyInteger' => ['name', 'type', 'nullable', 'unique', 'default', 'after'],
        'smallInteger' => ['name', 'type', 'nullable', 'unique', 'default', 'after'],
        'mediumInteger' => ['name', 'type', 'nullable', 'unique', 'default', 'after'],
        'bigInteger' => ['name', 'type', 'nullable', 'unique', 'default', 'after'],
        'boolean' => ['name', 'type', 'nullable', 'default', 'after'],
        'timestamp' => ['name', 'type', 'nullable', 'default', 'after'],
        'date' => ['name', 'type', 'nullable', 'default', 'after'],
        'dateTime' => ['name', 'type', 'nullable', 'default', 'after'],
        'time' => ['name', 'type', 'nullable', 'default', 'after'],
        'year' => ['name', 'type', 'nullable', 'default', 'after'],
        'json' => ['name', 'type', 'nullable', 'default', 'after'],
        'jsonb' => ['name', 'type', 'nullable', 'default', 'after'],
        'decimal' => ['name', 'type', 'total', 'places', 'nullable', 'unique', 'default', 'after'],
        'double' => ['name', 'type', 'total', 'places', 'nullable', 'unique', 'default', 'after'],
        'float' => ['name', 'type', 'total', 'places', 'nullable', 'unique', 'default', 'after'],
        'enum' => ['name', 'type', 'options', 'nullable', 'default', 'after'],
        'unsignedBigInteger' => ['name', 'type', 'nullable', 'unique', 'default', 'after'],
        'foreignId' => ['name', 'type', 'on', 'onDelete', 'onUpdate', 'nullable', 'after'],
        'foreign' => ['name', 'type', 'references', 'on', 'onDelete', 'onUpdate', 'nullable', 'after'],
        'softDeletes' => ['type', 'name'],
        'softDeletesTz' => ['type', 'name'],
        'bigIncrements' => ['type', 'name'],
        'increments' => ['type', 'name'],
        'morphs' => ['type', 'name', 'index'],
        'nullableMorphs' => ['type', 'name', 'index'],
    ];

    /**
     * Define los tipos de índices válidos y sus atributos.
     *
     * @var array
     */
    protected const VALID_INDEX_TYPES = [
        'index',
        'unique',
        'primary',
        'fulltext',
        'spatial',
    ];

    public function __construct(
        string $moduleName,
        string $modulePath,
        bool $isClean,
        string $migrationName,
        array $attributes = [],
        array $indexes = [],
        array $componentConfig = []
    ) {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->migrationName = Str::studly($migrationName);
        $this->attributes = $attributes;
        $this->indexes = $indexes;
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
        $tableSchema = $this->getMigrationSchema($this->attributes, $this->indexes);

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

        return $currentTimestamp . sprintf('_%06d', self::$migrationCounter);
    }

    /**
     * Genera el esquema de la tabla de migración a partir de los atributos e índices.
     *
     * @param array $attributes
     * @param array $indexes
     * @return string
     */
    protected function getMigrationSchema(array $attributes, array $indexes): string
    {
        $schemaLines = [];

        // Genera las columnas primero
        $schemaLines = array_merge($schemaLines, $this->getMigrationColumns($attributes));

        // Luego, genera los índices
        $schemaLines = array_merge($schemaLines, $this->getMigrationIndexes($indexes));

        // Unimos las líneas con un salto de línea y la indentación correcta
        // Usamos una indentación estándar de 12 espacios para mayor claridad
        return implode("\n            ", $schemaLines);
    }

    /**
     * Genera las líneas de código para las columnas de la migración.
     *
     * @param array $attributes
     * @return array
     */
    protected function getMigrationColumns(array $attributes): array
    {
        $schemaLines = [];
        
        // Se asume que no siempre la tabla tendrá un ID, pero es lo más común.
        $hasId = false;
        foreach ($attributes as $attribute) {
            if (isset($attribute['type']) && in_array($attribute['type'], ['increments', 'bigIncrements', 'id'])) {
                $hasId = true;
                break;
            }
        }
        if (!$hasId) {
            $schemaLines[] = "\$table->id();";
        }

        foreach ($attributes as $attribute) {
            if (($attribute['type'] ?? null) === 'relationship') {
                continue;
            }

            // Validación: asegura que el tipo y los atributos sean válidos
            $this->validateAttribute($attribute);

            // Se añade el punto y coma aquí, al final de cada línea de columna.
            $schemaLines[] = $this->getSchemaLineForAttribute($attribute) . ";";
        }

        // Se asume que la mayoría de las tablas tienen timestamps.
        $hasTimestamps = false;
        foreach ($attributes as $attribute) {
            if (isset($attribute['type']) && in_array($attribute['type'], ['timestamp', 'timestamps'])) {
                $hasTimestamps = true;
                break;
            }
        }
        if (!$hasTimestamps) {
            $schemaLines[] = "\$table->timestamps();";
        }
        
        return $schemaLines;
    }

    /**
     * Genera las líneas de código para los índices de la migración.
     *
     * @param array $indexes
     * @return array
     */
    protected function getMigrationIndexes(array $indexes): array
    {
        $schemaLines = [];
        foreach ($indexes as $index) {
            if (!isset($index['columns']) || empty($index['columns'])) {
                throw new InvalidArgumentException("Un índice debe tener un array 'columns' no vacío.");
            }

            $type = $index['type'] ?? 'index';
            if (!in_array($type, self::VALID_INDEX_TYPES)) {
                throw new InvalidArgumentException("Tipo de índice '{$type}' no válido.");
            }

            // Manejo de índices simples y compuestos de forma consistente.
            $columns = is_array($index['columns']) ? $index['columns'] : [$index['columns']];
            
            // Si es un índice de una sola columna, se usa la sintaxis simple.
            if (count($columns) === 1) {
                $schemaLines[] = "\$table->{$type}('{$columns[0]}');";
            } else {
                // Si es un índice compuesto, se usa el array de columnas.
                $columnsString = "['" . implode("', '", $columns) . "']";
                $schemaLines[] = "\$table->{$type}({$columnsString});";
            }
        }
        return $schemaLines;
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
        
        if (in_array($type, ['decimal', 'double', 'float']) && (!isset($attribute['total']) || !isset($attribute['places']))) {
            throw new InvalidArgumentException("Los tipos 'decimal', 'double' y 'float' requieren los atributos 'total' y 'places'.");
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
        
        // Los helpers como softDeletes, morphs, increments no tienen un nombre de columna, así que se manejan diferente.
        $helperTypes = ['softDeletes', 'softDeletesTz', 'bigIncrements', 'increments', 'morphs', 'nullableMorphs', 'id'];
        if (in_array($type, $helperTypes)) {
            $methodName = Str::camel($type);
            $definition = "\$table->{$methodName}()";
            if (isset($attribute['name'])) {
                $definition = "\$table->{$methodName}('{$attribute['name']}')";
            }
            return $definition;
        }

        // Para tipos de datos que requieren un nombre de columna
        if (!isset($attribute['name'])) {
            throw new InvalidArgumentException("El tipo de dato '{$type}' requiere un atributo 'name'.");
        }
        
        $columnMethod = Str::camel($type) . 'Column';
        
        if (method_exists($this, $columnMethod)) {
            return $this->{$columnMethod}($attribute);
        }
        
        // Si no hay un método especializado, generar una definición genérica
        $name = $attribute['name'] ?? '';
        $definition = "\$table->{$type}('{$name}')";
        return $this->addModifiersToDefinition($definition, $attribute);
    }
    
    //---------------------------------------------------------
    // Métodos para generar columnas específicas
    //---------------------------------------------------------

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
     * Genera la definición de una columna de tipo char.
     *
     * @param array $attribute
     * @return string
     */
    protected function charColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $length = $attribute['length'] ?? 255;
        $definition = "\$table->char('{$name}', {$length})";

        return $this->addModifiersToDefinition($definition, $attribute);
    }
    
    /**
     * Genera la definición de una columna de tipo uuid.
     *
     * @param array $attribute
     * @return string
     */
    protected function uuidColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->uuid('{$name}')";

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
     * Genera la definición de una columna de tipo bigInteger.
     *
     * @param array $attribute
     * @return string
     */
    protected function bigIntegerColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->bigInteger('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    /**
     * Genera la definición de una columna de tipo decimal.
     *
     * @param array $attribute
     * @return string
     */
    protected function decimalColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $total = $attribute['total'];
        $places = $attribute['places'];
        $definition = "\$table->decimal('{$name}', {$total}, {$places})";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    /**
     * Genera la definición de una columna de tipo float.
     *
     * @param array $attribute
     * @return string
     */
    protected function floatColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $total = $attribute['total'] ?? 8;
        $places = $attribute['places'] ?? 2;
        $definition = "\$table->float('{$name}', {$total}, {$places})";
        
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
     * Genera la definición de una columna de tipo date.
     *
     * @param array $attribute
     * @return string
     */
    protected function dateColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->date('{$name}')";
        return $this->addModifiersToDefinition($definition, $attribute);
    }
    
    /**
     * Genera la definición de una columna de tipo datetime.
     *
     * @param array $attribute
     * @return string
     */
    protected function dateTimeColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->dateTime('{$name}')";
        return $this->addModifiersToDefinition($definition, $attribute);
    }

    /**
     * Genera la definición de una columna de tipo time.
     *
     * @param array $attribute
     * @return string
     */
    protected function timeColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->time('{$name}')";
        return $this->addModifiersToDefinition($definition, $attribute);
    }
    
    /**
     * Genera la definición de una columna de tipo year.
     *
     * @param array $attribute
     * @return string
     */
    protected function yearColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->year('{$name}')";
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
