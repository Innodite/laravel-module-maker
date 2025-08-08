<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class SeederGenerator extends AbstractComponentGenerator
{
    protected const SEEDER_PATH_SUFFIX = "/Database/Seeders";
    protected const SEEDER_FILE_SUFFIX = "Seeder.php";
    protected const STUB_FILE = 'seeder.stub';
    protected const DEFAULT_FACTORY_COUNT = 50;

    protected string $moduleName;
    protected string $seederName;
    protected string $modelName;

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $seederName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        
        $this->seederName = Str::studly($seederName);
        $this->moduleName = $moduleName;
        $this->modelName = $componentConfig['name'] ?? Str::studly(str_replace('Seeder', '', $this->seederName));
    }

    /**
     * Genera el archivo del seeder.
     *
     * @return void
     */
    public function generate(): void
    {
        $seederDir = $this->getComponentBasePath() . self::SEEDER_PATH_SUFFIX;
        $this->ensureDirectoryExists($seederDir);

        $stub = $this->getStubContent(self::STUB_FILE, $this->isClean, [
            'module' => $this->moduleName,
            'namespace' => "Modules\\{$this->moduleName}\\Database\\Seeders",
            'seederName' => $this->seederName,
            'modelName' => $this->modelName,
            'factoryCount' => self::DEFAULT_FACTORY_COUNT,
        ]);

        $this->putFile("{$seederDir}/{$this->seederName}" . self::SEEDER_FILE_SUFFIX, $stub, "Seeder {$this->seederName} creado en Modules/{$this->moduleName}/Database/Seeders");
    }
}
