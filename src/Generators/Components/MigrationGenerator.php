<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use InvalidArgumentException;
use Carbon\Carbon;

/**
 * Clase para generar archivos de migración de Laravel de forma dinámica.
 *
 *
 */
class MigrationGenerator extends AbstractComponentGenerator
{
    use HasStubs;

    protected const MIGRATION_DIRECTORY = 'Database/Migrations';
    protected const MIGRATION_STUB_FILE = 'migration.stub';

    protected string $migrationName;
    protected array $attributes;
    protected array $indexes;

    /**
     * Define los atributos y modificadores válidos para cada tipo de dato.
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
        'foreignId' => ['name', 'type', 'on', 'onDelete', 'onUpdate', 'nullable', 'after', 'constrained'],
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
        // v3.0.0: las migraciones viven en Database/Migrations/{Context}/
        // Usar buildPath() garantiza la subcarpeta de contexto correcta
        $migrationDirectoryPath = $this->buildPath('Database/Migrations');
        $this->ensureDirectoryExists($migrationDirectoryPath);

        $tableName   = $this->tableName($this->migrationName);
        $tableSchema = $this->getMigrationSchema($this->attributes, $this->indexes);

        // Idempotencia: si ya existe una migración para esta tabla en este contexto, no duplicar.
        // Se buscan las dos formas del nombre —con y sin `_final`— para que un módulo generado con
        // la v3 no reciba una segunda migración de la misma tabla al regenerarse.
        $existingFiles = array_merge(
            glob("{$migrationDirectoryPath}/*_create_{$tableName}_table.php") ?: [],
            glob("{$migrationDirectoryPath}/*_create_{$tableName}_table_final.php") ?: []
        );

        if (!empty($existingFiles)) {
            $this->warn("Migración para '{$tableName}' ya existe en " . basename(dirname($migrationDirectoryPath)) . "/Database/Migrations. Se omite la generación.");

            // Aunque no se escriba migración nueva, el trait se reescribe: la lista se deriva de la
            // carpeta, así que tiene que reflejar lo que hay AHORA. Si se saltara este paso, un
            // módulo con una migración añadida a mano quedaría con una lista que no la nombra — dos
            // mitades separándose otra vez, y esta vez en el despliegue.
            $this->writeMigrationsListTrait($migrationDirectoryPath);

            return;
        }

        // Timestamp con microsegundos para evitar colisiones entre archivos del mismo contexto.
        //
        // El sufijo `_final` no es decoración (R22b): dice que ESTE archivo lleva el esquema
        // completo de la tabla, no un delta. Con él, la carpeta de la subfuncionalidad se lee de un
        // golpe —cada `_final` es una tabla— y los cambios posteriores van como delta con guardia en
        // el trait `InlineAlters`, que llega en F-5. Sin el sufijo no se distingue la migración que
        // crea la tabla de las que la modifican, y el orden de la carpeta deja de significar nada.
        $uniqueTimestamp = Carbon::now()->format('Y_m_d_Hisu');
        $fileName        = "{$uniqueTimestamp}_create_{$tableName}_table_final.php";

        // IMPORTANTE: Se pasa el tableName pero NO un className.
        // El stub usa clases anónimas (return new class extends Migration {})
        // para evitar colisiones cuando dos contextos migran la misma tabla.
        $stubContent = $this->getStubContent(self::MIGRATION_STUB_FILE, $this->isClean, [
            'tableName'   => $tableName,
            'columns'     => $tableSchema,
        ]);

        $contextFolder = $this->getContextFolder();
        $contextLabel  = $contextFolder ? "Database/Migrations/{$contextFolder}" : 'Database/Migrations';
        $this->putFile("{$migrationDirectoryPath}/{$fileName}", $stubContent, "Migración '{$tableName}' creada en Modules/{$this->moduleName}/{$contextLabel}");

        $this->writeMigrationsListTrait($migrationDirectoryPath);
    }

    /**
     * Escribe el trait `MigrationsList` de la subfuncionalidad — la lista ordenada, en código.
     *
     * Sustituye al manifiesto JSON (P2), y el motivo no es estético: un JSON es un segundo sitio que
     * describe lo que ya dice la carpeta, no viaja con el módulo cuando alguien lo copia a otro
     * proyecto, y **se desincroniza en silencio**. El trait es código, viaja con el módulo, y aquí
     * se **deriva de la carpeta** en cada generación, así que no puede quedar desfasado.
     *
     * Vive con las otras cinco piezas de seeder —en `Database/Seeders/{Ctx}/{SubFunc}/`, no en
     * `Migrations/`— porque es una de las seis (norma §6, `SeederNames`). Y es el seeder quien
     * ejecuta las migraciones (**R22**): nadie corre `migrate` a mano.
     */
    protected function writeMigrationsListTrait(string $migrationDirectoryPath): void
    {
        $subFeature = $this->getSubFeatureFolder();

        if ($subFeature === '') {
            return;   // sin subfuncionalidad no hay grupo de seis piezas al que pertenecer
        }

        $traitName = SeederNames::piece($this->getClassPrefix(), $this->moduleName, $subFeature, 'MigrationsList');
        $seederDir = $this->buildPath('Database/Seeders');

        $this->ensureDirectoryExists($seederDir);

        $archivos = glob("{$migrationDirectoryPath}/*.php") ?: [];
        sort($archivos);   // el orden del despliegue es el de los nombres: el timestamp manda

        $moduleRoot = dirname($this->getComponentBasePath());
        $rutas      = array_map(
            static fn (string $ruta): string => "            '" . str_replace(
                '\\',
                '/',
                'Modules/' . ltrim(substr($ruta, strlen($moduleRoot)), '/\\')
            ) . "',",
            $archivos
        );

        $lista = $rutas === [] ? '' : "\n" . implode("\n", $rutas) . "\n        ";

        $stub = $this->getStubContent('migrations-list.stub', $this->isClean, [
            'namespace'   => $this->buildNamespace('Database\\Seeders'),
            'traitName'   => $traitName,
            'subFeature'  => $subFeature,
            'migrations'  => $lista,
        ]);

        $this->putFile(
            "{$seederDir}/{$traitName}.php",
            $stub,
            "Lista de migraciones creada: {$traitName}.php"
        );

        $this->writeInlineAltersTrait($seederDir, $subFeature);
    }

    /**
     * Escribe el trait `InlineAlters` — el segundo registro de R22b.
     *
     * La norma pide **una sola `_final` por tabla** con el esquema completo, para que una
     * instalación nueva levante la tabla como está hoy sin replicar su historial. Pero un proyecto
     * que ya desplegó esa tabla no puede recibir un `create` otra vez: necesita el **delta**. De ahí
     * el doble registro — el mismo cambio escrito en los dos sitios.
     *
     * Nace **vacío**, y eso es lo correcto: un módulo recién generado no tiene cambios posteriores,
     * su esquema entero está en la `_final`. Lo que sí lleva es la estructura y el patrón con
     * guardia documentado dentro, para que el primer delta se escriba bien. Emitir deltas inventados
     * sería B3 otra vez: una pieza que aparenta contenido y no hace nada.
     *
     * No se reescribe si ya existe: dentro vive código que escribió el desarrollador.
     */
    protected function writeInlineAltersTrait(string $seederDir, string $subFeature): void
    {
        $traitName = SeederNames::piece($this->getClassPrefix(), $this->moduleName, $subFeature, 'InlineAlters');
        $destino   = "{$seederDir}/{$traitName}.php";

        if (File::exists($destino)) {
            return;
        }

        $tableName = $this->tableName($this->migrationName);

        $stub = $this->getStubContent('inline-alters.stub', $this->isClean, [
            'namespace'  => $this->buildNamespace('Database\\Seeders'),
            'traitName'  => $traitName,
            'subFeature' => $subFeature,
            'tableName'  => $tableName,
        ]);

        $this->putFile($destino, $stub, "Deltas de esquema creados: {$traitName}.php");
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

        $schemaLines = array_merge($schemaLines, $this->getMigrationColumns($attributes));

        $schemaLines = array_merge($schemaLines, $this->getFilteredMigrationIndexes($attributes, $indexes));

        // Se ha ajustado la indentación para que sea de 4 espacios.
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

        // La clave primaria es ULID (R10). El autoincremental es enumerable: con un `id` en la URL
        // se recorre la tabla entera probando números, y en un multitenant eso cruza inquilinos.
        //
        // Y la pone **el generador, no el stub**. Hasta ahora la escribían los dos —el stub traía
        // `$table->id();` fijo y esta línea inyectaba otro— y la migración salía con la columna
        // DUPLICADA: `duplicate column name: id` en cuanto alguien la ejecutaba. Ninguna migración
        // generada por el paquete se podía correr. Misma forma que B13, B15 y B18: dos mitades que
        // asumen cada una que la otra no lo hace. Ahora el stub solo interpola `{{{ columns }}}`.
        $declaraPropiaClave = false;

        foreach ($attributes as $attribute) {
            if (isset($attribute['type']) && in_array($attribute['type'], ['increments', 'bigIncrements', 'id', 'ulid', 'uuid'], true)) {
                $declaraPropiaClave = true;
                break;
            }
        }

        if (! $declaraPropiaClave) {
            $schemaLines[] = "\$table->ulid('id')->primary();";
        }

        foreach ($attributes as $attribute) {
            if (($attribute['type'] ?? null) === 'relationship') {
                continue;
            }

            $this->validateAttribute($attribute);

            $schemaLines[] = $this->getSchemaLineForAttribute($attribute) . ";";
        }

        // Borrado lógico en toda tabla generada (R69 · R70): eliminar y restaurar tienen que estar
        // siempre, y un `delete` que borra de verdad no se puede deshacer cuando el usuario se
        // equivoca. El modelo recibe `SoftDeletes` en la misma pasada — si una mitad lo lleva y la
        // otra no, la columna existe y nadie la usa, o el modelo filtra por una columna que no está.
        $declaraBorradoLogico = false;

        foreach ($attributes as $attribute) {
            if (($attribute['name'] ?? null) === 'deleted_at' || ($attribute['type'] ?? null) === 'softDeletes') {
                $declaraBorradoLogico = true;
                break;
            }
        }

        if (! $declaraBorradoLogico) {
            $schemaLines[] = "\$table->softDeletes();";
        }

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
     * Genera las líneas de código para los índices, filtrando los redundantes.
     *
     * @param array $attributes
     * @param array $indexes
     * @return array
     */
    protected function getFilteredMigrationIndexes(array $attributes, array $indexes): array
    {
        $schemaLines = [];
        $indexedColumns = [];

        foreach ($attributes as $attribute) {
            if (isset($attribute['name']) && isset($attribute['type'])) {
                if (
                    (isset($attribute['unique']) && $attribute['unique'] === true) ||
                    ($attribute['type'] === 'foreignId' && isset($attribute['constrained']) && $attribute['constrained'] === true)
                ) {
                    $indexedColumns[] = $attribute['name'];
                }
            }
        }

        foreach ($indexes as $index) {
            if (!isset($index['columns']) || empty($index['columns'])) {
                throw new InvalidArgumentException("Un índice debe tener un array 'columns' no vacío.");
            }

            $type = $index['type'] ?? 'index';
            if (!in_array($type, self::VALID_INDEX_TYPES)) {
                throw new InvalidArgumentException("Tipo de índice '{$type}' no válido.");
            }

            $columns = is_array($index['columns']) ? $index['columns'] : [$index['columns']];

            if (count($columns) === 1 && in_array($columns[0], $indexedColumns)) {
                continue;
            }

            if (count($columns) === 1) {
                $schemaLines[] = "\$table->{$type}('{$columns[0]}');";
            } else {
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

        $helperTypes = ['softDeletes', 'softDeletesTz', 'bigIncrements', 'increments', 'morphs', 'nullableMorphs', 'id'];
        if (in_array($type, $helperTypes)) {
            $methodName = Str::camel($type);
            $definition = "\$table->{$methodName}()";
            if (isset($attribute['name'])) {
                $definition = "\$table->{$methodName}('{$attribute['name']}')";
            }
            return $definition;
        }

        if (!isset($attribute['name'])) {
            throw new InvalidArgumentException("El tipo de dato '{$type}' requiere un atributo 'name'.");
        }

        $columnMethod = Str::camel($type) . 'Column';

        if (method_exists($this, $columnMethod)) {
            return $this->{$columnMethod}($attribute);
        }

        $name = $attribute['name'] ?? '';
        $definition = "\$table->{$type}('{$name}')";
        return $this->addModifiersToDefinition($definition, $attribute);
    }

    //---------------------------------------------------------
    // Métodos para generar columnas específicas
    //---------------------------------------------------------

    protected function stringColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $length = $attribute['length'] ?? null;
        $definition = $length ? "\$table->string('{$name}', {$length})" : "\$table->string('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function charColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $length = $attribute['length'] ?? 255;
        $definition = "\$table->char('{$name}', {$length})";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function uuidColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->uuid('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function textColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->text('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function integerColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->integer('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function bigIntegerColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->bigInteger('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function decimalColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $total = $attribute['total'];
        $places = $attribute['places'];
        $definition = "\$table->decimal('{$name}', {$total}, {$places})";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function floatColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $total = $attribute['total'] ?? 8;
        $places = $attribute['places'] ?? 2;
        $definition = "\$table->float('{$name}', {$total}, {$places})";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function booleanColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->boolean('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function dateColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->date('{$name}')";
        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function dateTimeColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->dateTime('{$name}')";
        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function timeColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->time('{$name}')";
        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function yearColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->year('{$name}')";
        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function unsignedBigIntegerColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->unsignedBigInteger('{$name}')";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    protected function enumColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $options = json_encode($attribute['options']);
        $definition = "\$table->enum('{$name}', {$options})";

        return $this->addModifiersToDefinition($definition, $attribute);
    }

    /**
     * Clave foránea — `foreignUlid`, para que apunte a la clave que las tablas llevan de verdad.
     *
     * Si la PK es ULID (R10) y la FK sigue siendo `foreignId` —un entero—, la restricción no se
     * puede crear: los tipos no casan. Es el mismo par que la clave primaria, un escalón más abajo.
     */
    protected function foreignIdColumn(array $attribute): string
    {
        $name = $attribute['name'];
        $definition = "\$table->foreignUlid('{$name}')";

        if (isset($attribute['on']) && isset($attribute['constrained']) && $attribute['constrained']) {
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
