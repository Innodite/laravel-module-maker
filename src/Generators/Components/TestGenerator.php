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

        // ── Sin contexto NI subfuncionalidad: comportamiento legacy ───────────
        // La condición era «sin contexto», y eso convertía single-app en un caso degradado: como
        // ahí el contexto siempre está vacío, un proyecto sin tenants caía en el camino legacy y
        // recibía una estructura recortada. No es un fallback, es un modo de primera clase — lo
        // que decide es si hay subfuncionalidad, que la hay siempre que se genere de verdad.
        if ($contextFolder === '' && $this->getSubFeatureFolder() === '') {
            $testDir = $this->getComponentBasePath() . '/Tests/Unit';
            $this->ensureDirectoryExists($testDir);

            $stub = $this->getStubContent('test.stub', $this->isClean, [
                'namespace' => "Modules\\{$this->moduleName}\\Tests\\Unit",
                'testName'  => $this->testName,
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
        $featureDir = $this->buildPath('Tests/Feature');
        $this->ensureDirectoryExists($featureDir);

        $featureStub = $this->getStubContent('test.stub', $this->isClean, [
            'namespace' => $this->buildNamespace('Tests\\Feature'),
            'testName'  => $className . 'Test',
        ]);

        $this->putFile(
            "{$featureDir}/{$className}Test.php",
            $featureStub,
            "Feature test {$className}Test.php creado en Modules/{$this->moduleName}/Tests/Feature/{$contextFolderPath}"
        );

        // ── 2. Unit test ───────────────────────────────────────────────────────
        $unitDir = $this->buildPath('Tests/Unit');
        $this->ensureDirectoryExists($unitDir);

        $unitStub = $this->getStubContent('test-unit.stub', $this->isClean, [
            'namespace' => $this->buildNamespace('Tests\\Unit'),
            'className' => $className,
        ]);

        $this->putFile(
            "{$unitDir}/{$className}ServiceTest.php",
            $unitStub,
            "Unit test {$className}ServiceTest.php creado en Modules/{$this->moduleName}/Tests/Unit/{$contextFolderPath}"
        );

        // ── 3. Support (solo Central) ──────────────────────────────────────────
        // En multitenant el soporte de pruebas vive en central; en single-app no hay otro
        // contexto que pueda tenerlo, asi que le corresponde igual.
        $llevaSoporte = ! $this->mode()->hasContextAxis()
            || $contextKey === 'central'
            || $this->getClassPrefix() === 'Central';

        if ($llevaSoporte) {
            $supportDir = $this->buildPath('Tests/Support');
            $this->ensureDirectoryExists($supportDir);

            $supportStub = $this->getStubContent('test-support.stub', $this->isClean, [
                'namespace'  => $this->buildNamespace('Tests\\Support'),
                'className'  => $className,
                'moduleName' => $this->moduleName,
            ]);

            $this->putFile(
                "{$supportDir}/{$className}Support.php",
                $supportStub,
                "Support test {$className}Support.php creado en Modules/{$this->moduleName}/Tests/Support/{$contextFolderPath}"
            );
        }
    }
}
