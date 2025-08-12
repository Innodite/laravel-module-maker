<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Illuminate\Support\Facades\File;

class SeederGenerator extends AbstractComponentGenerator
{
    protected const SEEDER_PATH_SUFFIX = "/Database/Seeders";
    protected const STUB_MAIN_SEEDER = 'main_seeder.stub';
    protected const STUB_TABLE_SEEDER = 'table_seeder.stub';
    protected const DEFAULT_FACTORY_COUNT = 50;

    protected string $seederName;
    protected string $modelName;
    protected string $moduleName;

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $seederName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        
        $this->seederName = Str::studly($seederName);
        $this->modelName = $componentConfig['name'] ?? Str::studly(str_replace('Seeder', '', $this->seederName));
        $this->moduleName = Str::studly($moduleName);
    }

    public function generate(): void
    {
        $seederDir = $this->getComponentBasePath() . self::SEEDER_PATH_SUFFIX;
        $this->ensureDirectoryExists($seederDir);

        // Genera el seeder de la tabla específica
        $this->generateTableSeeder($seederDir);
    }

    protected function generateTableSeeder(string $seederDir): void
    {
        $seederFile = "{$seederDir}/{$this->seederName}.php";

        $stub = $this->getStubContent(self::STUB_TABLE_SEEDER, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Database\\Seeders",
            'seederName' => $this->seederName,
            'modelName' => $this->modelName,
            'moduleName'=>$this->moduleName,
            'factoryCount' => self::DEFAULT_FACTORY_COUNT,
        ]);

        $this->putFile($seederFile, $stub, "Seeder de tabla {$this->seederName} creado en '{$seederFile}'.");
    }
}