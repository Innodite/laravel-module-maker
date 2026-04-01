<?php

declare(strict_types=1);

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
     * Genera los archivos de test según el contexto:
     *
     * - Tests/Feature/{contextFolder}/{className}Test.php  (siempre, con contexto)
     * - Tests/Unit/{contextFolder}/{className}ServiceTest.php  (siempre, con contexto)
     * - Tests/Support/{contextFolder}/{className}Support.php  (solo Central)
     *
     * Sin contexto (fallback): genera un único test en Tests/Unit.
     *
     * @return void
     */
    public function generate(): void
    {
        $contextKey    = $this->componentConfig['context'] ?? null;
        $contextFolder = $this->getContextFolder();

        // ── Sin contexto definido: comportamiento legacy ───────────────────────
        if ($contextKey === null || $contextFolder === '') {
            $testDir = $this->getComponentBasePath() . '/Tests/Unit';
            $this->ensureDirectoryExists($testDir);

            $stub = $this->getStubContent('test.stub', $this->isClean, [
                'namespace' => "Modules\\{$this->moduleName}\\Tests\\Unit",
                'testName'  => $this->testName,
                'module'    => $this->moduleName,
            ]);

            $this->putFile(
                "{$testDir}/{$this->testName}.php",
                $stub,
                "Test {$this->testName}.php creado en Modules/{$this->moduleName}/Tests/Unit"
            );
            return;
        }

        $contextFolderPath = $contextFolder;
        $contextNamespace  = str_replace('/', '\\', $contextFolderPath);
        $moduleNamespace   = "Modules\\{$this->moduleName}";
        $className         = $this->getClassPrefix() . $this->moduleName;

        // ── 1. Feature test ────────────────────────────────────────────────────
        $featureDir = $this->getComponentBasePath() . '/Tests/Feature/' . $contextFolderPath;
        $this->ensureDirectoryExists($featureDir);

        $featureStub = $this->getStubContent('test.stub', $this->isClean, [
            'namespace' => "{$moduleNamespace}\\Tests\\Feature\\{$contextNamespace}",
            'testName'  => $className . 'Test',
            'module'    => $this->moduleName,
        ]);

        $this->putFile(
            "{$featureDir}/{$className}Test.php",
            $featureStub,
            "Feature test {$className}Test.php creado en Modules/{$this->moduleName}/Tests/Feature/{$contextFolderPath}"
        );

        // ── 2. Unit test ───────────────────────────────────────────────────────
        $unitDir = $this->getComponentBasePath() . '/Tests/Unit/' . $contextFolderPath;
        $this->ensureDirectoryExists($unitDir);

        $unitStub = $this->getStubContent('test-unit.stub', $this->isClean, [
            'moduleNamespace' => $moduleNamespace,
            'contextFolder'   => $contextNamespace,
            'className'       => $className,
            'moduleName'      => $this->moduleName,
        ]);

        $this->putFile(
            "{$unitDir}/{$className}ServiceTest.php",
            $unitStub,
            "Unit test {$className}ServiceTest.php creado en Modules/{$this->moduleName}/Tests/Unit/{$contextFolderPath}"
        );

        // ── 3. Support (solo Central) ──────────────────────────────────────────
        $isCentral = ($contextKey === 'central' || ($this->getClassPrefix() === 'Central'));
        if ($isCentral) {
            $supportDir = $this->getComponentBasePath() . '/Tests/Support/' . $contextFolderPath;
            $this->ensureDirectoryExists($supportDir);

            $supportStub = $this->getStubContent('test-support.stub', $this->isClean, [
                'moduleNamespace' => $moduleNamespace,
                'contextFolder'   => $contextNamespace,
                'className'       => $className,
                'moduleName'      => $this->moduleName,
            ]);

            $this->putFile(
                "{$supportDir}/{$className}Support.php",
                $supportStub,
                "Support test {$className}Support.php creado en Modules/{$this->moduleName}/Tests/Support/{$contextFolderPath}"
            );
        }
    }
}
