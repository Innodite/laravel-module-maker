<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class SeederGenerator extends AbstractComponentGenerator
{
    protected string $seederName;

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $seederName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->seederName = Str::studly($seederName);
    }

    /**
     * Genera el archivo del seeder.
     *
     * @return void
     */
    public function generate(): void
    {
        $seederDir = $this->getComponentBasePath() . "/Database/Seeders";
        $this->ensureDirectoryExists($seederDir);

        $stubFile = 'seeder.stub';
        $stub = $this->getStubContent($stubFile, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Database\\Seeders",
            'seederName' => $this->seederName,
        ]);

        $this->putFile("{$seederDir}/{$this->seederName}Seeder.php", $stub, "Seeder {$this->seederName}Seeder.php creado en Modules/{$this->moduleName}/Database/Seeders");
    }
}
