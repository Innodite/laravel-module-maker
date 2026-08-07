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

        // Y la base que lee ese manifiesto: donde vive la derivación de rutas, permisos y usuarios.
        $this->writeBase();

        // Las piezas del contrato, en el orden de la cascada: primero el andamiaje, después el
        // esquema. Si el andamiaje falla, lo demás falla por lo mismo y no informa de nada nuevo.
        $this->writeScaffoldTest();
        $this->writeSchemaTest();
        $this->writePermissionsTest();
        $this->writeDeploymentTest();
        $this->writeHttpTest();
        $this->writeVueTest();

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
        $this->escribirPiezaDelGrupo('test-contract.stub', 'Contract', 'Contrato de pruebas', [
            'connection'       => $this->connectionLiteral(),
            'tables'           => $this->tablesLiteral(),
            'routePrefix'      => $this->routeNamePrefix(),
            'routeUri'         => $this->getFunctionality(),
            'permissionSeeder' => '\\' . $this->buildNamespace('Database\\Seeders') . '\\'
                . SeederNames::piece(
                    $this->getClassPrefix(),
                    $this->moduleName,
                    $this->getSubFeatureFolder(),
                    'Permissions'
                ),
            'viewActions'      => $this->viewActionsLiteral(),
            'scaffold'         => $this->scaffoldLiteral($this->getSubFeatureFolder()),
        ]);
    }

    /**
     * Escribe la base del grupo — `{Prefijo}{SubFunc}TestCase` —, si no está ya.
     *
     * Es la pieza que hace que las otras cinco no repitan la derivación: rutas del router, permiso
     * del middleware, permisos canónicos del seeder, usuarios con y sin permiso. Vive en la misma
     * carpeta que ellas porque es del grupo, no del módulo.
     */
    protected function writeBase(): void
    {
        $this->escribirPiezaDelGrupo('test-base.stub', 'TestCase', 'Base de pruebas', [
            'routePrefix' => $this->routeNamePrefix(),
        ]);
    }

    /**
     * Tema 0 — el andamiaje: las piezas existen **y su contenido cumple** (R77).
     */
    protected function writeScaffoldTest(): void
    {
        $this->escribirPiezaDelGrupo('test-scaffold.stub', 'ScaffoldTest', 'Prueba del andamiaje');
    }

    /**
     * Temas 1 y 2 — el esquema: las tablas y las columnas del contrato, recorridas.
     */
    protected function writeSchemaTest(): void
    {
        $this->escribirPiezaDelGrupo('test-schema.stub', 'SchemaTest', 'Prueba del esquema');
    }

    /**
     * Temas 3, 4 y 5 — los permisos: únicos por ruta, existentes en la base, y aplicados.
     */
    protected function writePermissionsTest(): void
    {
        $this->escribirPiezaDelGrupo('test-permissions.stub', 'PermissionsTest', 'Prueba de permisos');
    }

    /**
     * Tema 8 — el despliegue, ejecutado: levanta, es idempotente, no destruye, y en producción también.
     */
    protected function writeDeploymentTest(): void
    {
        $this->escribirPiezaDelGrupo('test-deployment.stub', 'DeploymentTest', 'Prueba del despliegue');
    }

    /**
     * Tema 7 — el comportamiento por HTTP: el único sin techo, y con el borde de R34.
     *
     * La prueba de aislamiento entre tenants **solo se escribe en multitenant**, y no por ahorrar
     * líneas: en una aplicación sin tenants no hay otro inquilino del que aislarse, así que esa
     * prueba no podría fallar nunca — y una prueba que no puede fallar es ruido que se acaba
     * ignorando. Lo decide el modo, como todo lo demás.
     */
    protected function writeHttpTest(): void
    {
        $this->escribirPiezaDelGrupo('test-http.stub', 'HttpTest', 'Prueba de comportamiento', [
            'pruebaDeAislamiento' => $this->mode()->hasContextAxis() ? $this->pruebaDeAislamiento() : '',
        ]);
    }

    /**
     * Tema 6 — la vista: una acción sin permiso no se dibuja.
     *
     * Son **dos** archivos y viven fuera de `Tests/`, en `resources/js/__tests__/`, porque los
     * ejecuta Vitest y no PHPUnit: el manifiesto del lado JS —que el de PHP no se puede leer desde
     * JavaScript— y la prueba del componente de listado, que es donde están las cuatro acciones.
     */
    protected function writeVueTest(): void
    {
        $subFeature = $this->getSubFeatureFolder();

        if ($subFeature === '') {
            return;
        }

        $dir = $this->rutaDePruebasJs();

        $this->ensureDirectoryExists($dir);

        $componente    = $this->getClassPrefix() . $subFeature . 'Index';
        $contractJs    = $this->getClassPrefix() . $subFeature . 'Contract';

        $piezas = [
            "{$contractJs}.js" => ['test-contract-js.stub', [
                'viewActionsJs' => $this->viewActionsJsLiteral(),
                'vueComponent'  => $componente,
                'subFeature'    => $subFeature,
            ]],
            "{$componente}.test.js" => ['test-vue.stub', [
                'contractJsName'   => $contractJs,
                'vueComponent'     => $componente,
                'rutaAlComponente' => $this->rutaRelativaAlComponente($componente),
                'subFeature'       => $subFeature,
            ]],
        ];

        foreach ($piezas as $archivo => [$stub, $placeholders]) {
            $destino = "{$dir}/{$archivo}";

            if (File::exists($destino)) {
                $this->warn("Prueba de vista '{$archivo}' ya existe. Se omite para no pisar lo que tenga dentro.");

                continue;
            }

            $this->putFile(
                $destino,
                $this->getStubContent($stub, $this->isClean, $placeholders),
                "Prueba de vista '{$archivo}' creada en Modules/{$this->moduleName}/resources/js/__tests__."
            );
        }
    }

    /** `resources/js/__tests__/{Ctx}/{SubFunc}` — el espejo de la carpeta de la vista. */
    protected function rutaDePruebasJs(): string
    {
        $contexto = $this->getContextFolder();

        return $this->getComponentBasePath() . '/resources/js/__tests__'
            . ($contexto ? "/{$contexto}" : '')
            . '/' . $this->getSubFeatureFolder();
    }

    /**
     * De la carpeta de la prueba a la del componente, en saltos hacia arriba.
     *
     * Se cuenta, no se escribe a mano: en multitenant el contexto puede tener dos segmentos
     * —`Tenant/Shared`— y un `../..` fijo dejaría el import apuntando al vacío. Un import roto en
     * JavaScript no lo ve ningún chequeo de PHP.
     */
    protected function rutaRelativaAlComponente(string $componente): string
    {
        $contexto  = $this->getContextFolder();
        $segmentos = 1 + ($contexto === '' ? 0 : count(explode('/', $contexto))) + 1;

        return str_repeat('../', $segmentos) . 'Pages'
            . ($contexto ? "/{$contexto}" : '')
            . '/' . $this->getSubFeatureFolder()
            . "/{$componente}.vue";
    }

    /**
     * `accion: { permiso, ancla }` como objeto JavaScript, derivado del mismo sitio que el de PHP.
     */
    protected function viewActionsJsLiteral(): string
    {
        $prefijo       = $this->permissionPrefix();
        $funcionalidad = $this->getFunctionality();

        $lineas = array_map(
            static fn (array $accion): string => "\n    {$accion['action']}: { permiso: '"
                . SubFeaturePermissions::permissionName($prefijo, $funcionalidad, $accion['permission'])
                . "', ancla: '{$accion['element']}' },",
            SubFeaturePermissions::VIEW_ACTIONS,
        );

        return '{' . implode('', $lineas) . "\n}";
    }

    /**
     * La prueba de aislamiento entre tenants, obligatoria en multitenant.
     *
     * Sin ella el multitenant **no está probado**: todo lo demás puede estar en verde y aun así un
     * usuario de un inquilino leer los registros de otro cambiando un identificador en la URL.
     */
    protected function pruebaDeAislamiento(): string
    {
        return <<<'PHP'

    /**
     * Qué prueba: que un registro de otro contexto no se devuelve por esta ruta.
     * Resultado esperado: la respuesta no trae el identificador ajeno.
     * Por qué existe: **sin esta prueba el multitenant no está probado.** Todo lo demás puede estar
     *   en verde y un usuario seguir leyendo los datos de otro inquilino cambiando el id de la URL,
     *   que es la peor forma de fallar: silenciosa, y con datos de un tercero.
     */
    public function test_no_se_devuelve_un_registro_de_otro_contexto(): void
    {
        $ajeno = (string) Str::ulid();

        $respuesta = $this->llamarAccion('show', ['id' => $ajeno]);

        $this->assertStringNotContainsString(
            $ajeno,
            (string) $respuesta->getContent(),
            'FALLA: la respuesta devuelve un identificador que no pertenece a este contexto. · FIX: '
            . 'comprueba que el repository consulta la conexión del contexto y filtra por su tenant; '
            . 'una consulta sin ese filtro cruza inquilinos sin dar un solo error.'
        );
    }
PHP;
    }

    /**
     * El molde común de las piezas del grupo: mismo sitio, mismo nombre compuesto, misma regla de
     * no sobreescribir.
     *
     * Se escribe una vez y no seis porque las seis piezas comparten exactamente esas cuatro
     * decisiones, y son justo las que no pueden divergir: si una pieza cayera en otra carpeta o se
     * llamara de otra forma, el grupo dejaría de ser un grupo — y el comando que lo ejecuta en
     * cascada no la encontraría.
     *
     * **Ninguna se sobreescribe.** Dentro de un test vive lo que el desarrollador añadió: el caso de
     * negocio, la regla propia, el escenario que costó una tarde reproducir. Regenerar el módulo no
     * puede llevárselo por delante.
     *
     * @param  array<string, string>  $extra  Placeholders propios de la pieza
     */
    protected function escribirPiezaDelGrupo(string $stub, string $sufijo, string $etiqueta, array $extra = []): void
    {
        $subFeature = $this->getSubFeatureFolder();

        if ($subFeature === '') {
            // Sin subfuncionalidad no hay grupo al que pertenecer: mismo criterio que aplican el
            // generador de migraciones con sus dos traits y el de seeders con sus cuatro piezas.
            return;
        }

        $dir = $this->buildPath('Tests/Feature');

        $this->ensureDirectoryExists($dir);

        $prefijo = $this->getClassPrefix();
        $nombre  = $prefijo . $subFeature . $sufijo;
        $destino = "{$dir}/{$nombre}.php";

        if (File::exists($destino)) {
            $this->warn("{$etiqueta} '{$nombre}' ya existe. Se omite para no pisar lo que tenga dentro.");

            return;
        }

        $comunes = [
            'namespace'       => $this->buildNamespace('Tests\\Feature'),
            'subFeature'      => $subFeature,
            'contractName'    => $prefijo . $subFeature . 'Contract',
            'baseName'        => $prefijo . $subFeature . 'TestCase',
            'scaffoldName'    => $prefijo . $subFeature . 'ScaffoldTest',
            'schemaName'      => $prefijo . $subFeature . 'SchemaTest',
            'permissionsName' => $prefijo . $subFeature . 'PermissionsTest',
            'deploymentName'  => $prefijo . $subFeature . 'DeploymentTest',
            'httpName'        => $prefijo . $subFeature . 'HttpTest',
        ];

        $this->putFile(
            $destino,
            $this->getStubContent($stub, $this->isClean, $extra + $comunes),
            "{$etiqueta} '{$nombre}' creada en Modules/{$this->moduleName}/Tests/Feature."
        );
    }

    /**
     * El prefijo del **nombre** de las rutas de esta subfuncionalidad: `invoices.`, `central.billings.`
     *
     * Se compone igual que lo compone el generador de rutas —`route_name` del contexto más la
     * funcionalidad más el punto— y no de otra forma: si las dos mitades divergen, el filtro del
     * contrato no encuentra ninguna ruta y los temas 3-5 recorren un conjunto vacío. Una prueba que
     * recorre la nada **pasa**, y esa es la peor manera de fallar.
     */
    protected function routeNamePrefix(): string
    {
        $contexto = $this->mode()->hasContextAxis() ? ($this->getContext()['route_name'] ?? '') : '';

        return $contexto . $this->getFunctionality() . '.';
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
