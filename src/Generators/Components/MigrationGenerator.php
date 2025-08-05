<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class MigrationGenerator extends AbstractComponentGenerator
{
    protected string $migrationName;
    protected array $attributes;

    // Propiedad estática para mantener un contador incremental por segundo
    protected static int $migrationCounter = 0;
    protected static ?string $lastTimestamp = null;

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $migrationName, array $attributes = [], array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->migrationName = Str::studly($migrationName);
        $this->attributes = $attributes;
    }

    /**
     * Genera el archivo de migración.
     *
     * @return void
     */
    public function generate(): void
    {
        $migrationDir = $this->getComponentBasePath() . "/Database/Migrations";
        $this->ensureDirectoryExists($migrationDir);

        $stubFile = 'migration.stub';
        $tableName = Str::snake(Str::plural($this->migrationName));
        $className = 'Create' . Str::studly($tableName) . 'Table';
        $tableSchema = $this->getMigrationSchema($this->attributes);

        // Generar un timestamp único para la migración
        $timestamp = $this->getUniqueMigrationTimestamp();

        $stub = $this->getStubContent($stubFile, $this->isClean, [
            'className' => $className,
            'tableName' => $tableName,
            'tableSchema' => $tableSchema,
        ]);

        $this->putFile("{$migrationDir}/{$timestamp}_create_{$tableName}_table.php", $stub, "Migración '{$tableName}' creada en Modules/{$this->moduleName}/Database/Migrations");
    }

    /**
     * Genera un timestamp único para el archivo de migración.
     * Esto asegura que las migraciones creadas en el mismo segundo tengan un orden.
     *
     * @return string
     */
    protected function getUniqueMigrationTimestamp(): string
    {
        $currentTimestamp = date('Y_m_d_His');

        if (self::$lastTimestamp !== $currentTimestamp) {
            self::$migrationCounter = 0;
            self::$lastTimestamp = $currentTimestamp;
        } else {
            self::$migrationCounter++;
        }

        // Formato para asegurar que el sufijo tenga 2 dígitos (00, 01, 02...)
        return $currentTimestamp . sprintf('_%02d', self::$migrationCounter);
    }

    /**
     * Genera el esquema de la tabla para la migración.
     *
     * @param array $attributes
     * @return string
     */
    protected function getMigrationSchema(array $attributes): string
    {
        if (empty($attributes)) {
            return "\$table->id();\n            \$table->timestamps();";
        }

        $schema = "\$table->id();\n";
        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $type = $attribute['type'];
            $nullable = $attribute['nullable'] ?? false;
            $length = $attribute['length'] ?? null;
            $references = $attribute['references'] ?? null;
            $on = $attribute['on'] ?? null;

            if ($type === 'relationship') {
                continue;
            }

            if ($type === 'foreignId') {
                $schema .= "            \$table->foreignId('{$name}')";
                if ($references && $on) {
                    $schema .= "->constrained('{$on}')";
                }
                if ($nullable) {
                    $schema .= "->nullable()";
                }
                $schema .= ";\n";
            } else {
                $schema .= "            \$table->{$type}('{$name}'";
                if ($length) {
                    $schema .= ", {$length}";
                }
                $schema .= ")";
                if ($nullable) {
                    $schema .= "->nullable()";
                }
                $schema .= ";\n";
            }
        }
        $schema .= "            \$table->timestamps();";

        return $schema;
    }
}
