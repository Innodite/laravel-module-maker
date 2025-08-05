<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class TestGenerator extends AbstractComponentGenerator
{
    protected string $testName;

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $testName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->testName = Str::studly($testName);
    }

    /**
     * Genera el archivo de test.
     *
     * @return void
     */
    public function generate(): void
    {
        $testDir = $this->getComponentBasePath() . "/Tests/Unit";
        $this->ensureDirectoryExists($testDir);

        $stubFile = 'test.stub';
        $stub = $this->getStubContent($stubFile, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Tests\\Unit",
            'testName' => $this->testName,
            'module' => $this->moduleName,
        ]);

        $this->putFile("{$testDir}/{$this->testName}.php", $stub, "Test {$this->testName}.php creado en Modules/{$this->moduleName}/Tests/Unit");
    }
}