<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Innodite\LaravelModuleMaker\Support\SubFeaturePermissions;

class TestGenerator extends AbstractComponentGenerator
{
    /**
     * Las columnas que toda tabla generada tiene, pase lo que pase con el negocio.
     *
     * Van al contrato porque son las que el patrón exige: la clave ULID (R10), las marcas de tiempo
     * y el borrado lógico (R69 · R70). Si alguna desaparece de una migración, el tema 2 lo dice.
     */
    private const COLUMNAS_DEL_PATRON = ['id', 'created_at', 'updated_at', 'deleted_at'];

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

        // El manifiesto del grupo de pruebas. Va antes que nada porque es lo que las piezas leen:
        // sin él, cada una tendría que volver a declarar las tablas, las acciones y el andamiaje —
        // que es exactamente la duplicación que R76 prohíbe.
        $this->writeContract();

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

    // ─── El manifiesto del grupo de pruebas (R76) ─────────────────────────────

    /**
     * Escribe `{Prefijo}{SubFunc}Contract.php` en la carpeta del grupo, si no está ya.
     *
     * **No se sobreescribe**, y por el mismo motivo que los seeders: dentro vive lo que el
     * desarrollador declaró —una tabla dependiente, una columna del contrato, una pieza propia— y
     * regenerar el módulo no puede llevárselo por delante.
     *
     * Sin subfuncionalidad no hay grupo al que pertenecer: mismo criterio que aplican el generador de
     * migraciones con sus dos traits y el de seeders con sus cuatro piezas.
     */
    protected function writeContract(): void
    {
        $subFeature = $this->getSubFeatureFolder();

        if ($subFeature === '') {
            return;
        }

        $dir = $this->buildPath('Tests/Feature');

        $this->ensureDirectoryExists($dir);

        $contractName = $this->getClassPrefix() . $subFeature . 'Contract';
        $destino      = "{$dir}/{$contractName}.php";

        if (File::exists($destino)) {
            $this->warn("Contrato '{$contractName}' ya existe. Se omite para no pisar lo que declare dentro.");

            return;
        }

        $stub = $this->getStubContent('test-contract.stub', $this->isClean, [
            'namespace'        => $this->buildNamespace('Tests\\Feature'),
            'contractName'     => $contractName,
            'subFeature'       => $subFeature,
            'connection'       => $this->connectionLiteral(),
            'tables'           => $this->tablesLiteral(),
            'routePrefix'      => $this->getFunctionality(),
            'permissionSeeder' => '\\' . $this->buildNamespace('Database\\Seeders') . '\\'
                . SeederNames::piece($this->getClassPrefix(), $this->moduleName, $subFeature, 'Permissions'),
            'viewActions'      => $this->viewActionsLiteral(),
            'scaffold'         => $this->scaffoldLiteral($subFeature),
        ]);

        $this->putFile(
            $destino,
            $stub,
            "Contrato de pruebas '{$contractName}' creado en Modules/{$this->moduleName}/Tests/Feature."
        );
    }

    /** La conexión, tal cual va en la constante: `null` o `'central'`. La misma que el modelo. */
    protected function connectionLiteral(): string
    {
        $conexion = $this->connectionKey();

        return $conexion === null ? 'null' : "'{$conexion}'";
    }

    /**
     * `'tabla' => ['columna', …]` para cada tabla de la subfuncionalidad.
     *
     * Las columnas son las que el patrón garantiza más las declaradas al generar. El desarrollador
     * añade aquí las que el contrato necesite: el tema 2 las recorre sin tocarse.
     */
    protected function tablesLiteral(): string
    {
        $declaradas = array_values(array_filter(array_map(
            static fn ($atributo): string => is_array($atributo) ? (string) ($atributo['name'] ?? '') : '',
            $this->componentConfig['attributes'] ?? [],
        )));

        $columnas = array_values(array_unique(array_merge(
            ['id'],
            $declaradas,
            array_slice(self::COLUMNAS_DEL_PATRON, 1),
        )));

        $lista = implode(', ', array_map(static fn (string $c): string => "'{$c}'", $columnas));

        return "\n        '" . $this->tableName() . "' => [{$lista}],\n    ";
    }

    /**
     * `'accion' => 'permiso_de_vista'`, derivado del mismo sitio que los emite el seeder.
     *
     * Se compone con `SubFeaturePermissions` y no a mano: cuando el paquete gane una acción de
     * vista, este manifiesto la trae sin tocarse — y si se escribiera aquí aparte, sería la tercera
     * lista de permisos del módulo.
     */
    protected function viewActionsLiteral(): string
    {
        $prefijo       = $this->permissionPrefix();
        $funcionalidad = $this->getFunctionality();

        $lineas = array_map(
            static fn (array $accion): string => "\n        '{$accion['action']}' => '"
                . SubFeaturePermissions::permissionName($prefijo, $funcionalidad, $accion['permission']) . "',",
            SubFeaturePermissions::VIEW_ACTIONS,
        );

        return implode('', $lineas) . "\n    ";
    }

    /**
     * Las piezas que la subfuncionalidad necesita para levantarse: clases por FQCN, archivos por ruta.
     *
     * El tema 0 distingue las dos formas —`class_exists()` contra `glob()`— porque una migración no
     * es una clase con nombre y una vista tampoco. Ambas se declaran relativas a la raíz del
     * proyecto, igual que las rutas del `MigrationsList`.
     */
    protected function scaffoldLiteral(string $subFeature): string
    {
        $prefijo  = $this->getClassPrefix();
        $clase    = $prefijo . $subFeature;
        $seeders  = '\\' . $this->buildNamespace('Database\\Seeders') . '\\';

        $piezas = [
            'migration'          => "'" . $this->rutaEnElModulo('Database/Migrations') . "/*_final.php'",
            'migrations_list'    => $seeders . SeederNames::piece($prefijo, $this->moduleName, $subFeature, 'MigrationsList') . '::class',
            'stage_seeder'       => $seeders . SeederNames::piece($prefijo, $this->moduleName, $subFeature, 'Stage') . '::class',
            'production_seeder'  => $seeders . SeederNames::piece($prefijo, $this->moduleName, $subFeature, 'Production') . '::class',
            'permissions_seeder' => 'self::PERMISSION_SEEDER',
            'model'              => '\\' . $this->buildNamespace('Models') . "\\{$clase}::class",
            'repository'         => '\\' . $this->buildNamespace('Repositories') . "\\{$clase}Repository::class",
            'service'            => '\\' . $this->buildNamespace('Services') . "\\{$clase}Service::class",
            'controller'         => '\\' . $this->buildNamespace('Http\\Controllers') . "\\{$clase}Controller::class",
            'form_request'       => '\\' . $this->buildNamespace('Http\\Requests') . "\\{$clase}StoreRequest::class",
            'view'               => "'" . $this->rutaEnElModulo('resources/js/Pages') . "/{$clase}Index.vue'",
        ];

        $ancho  = max(array_map('strlen', array_keys($piezas)));
        $lineas = [];

        foreach ($piezas as $nombre => $valor) {
            $lineas[] = "\n        '{$nombre}'" . str_repeat(' ', $ancho - strlen($nombre)) . " => {$valor},";
        }

        return implode('', $lineas) . "\n    ";
    }

    /**
     * La ruta de una carpeta del módulo, relativa a la raíz del proyecto.
     *
     * Relativa y no absoluta porque así la reciben `glob(base_path(…))` y `migrate --path`: es la
     * misma forma que ya usan las rutas del `MigrationsList`, y por la misma razón — el módulo
     * puede copiarse a otro proyecto y la ruta sigue significando lo mismo.
     */
    protected function rutaEnElModulo(string $tipo): string
    {
        $contexto   = $this->getContextFolder();
        $subFeature = $this->getSubFeatureFolder();

        return "Modules/{$this->moduleName}/{$tipo}"
            . ($contexto ? "/{$contexto}" : '')
            . ($subFeature ? "/{$subFeature}" : '');
    }
}
