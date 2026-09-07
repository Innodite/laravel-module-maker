<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Genera y mantiene el Service Provider del módulo.
 *
 * Comportamiento:
 *   - Primera ejecución: crea Providers/{Module}ServiceProvider.php con los bindings iniciales.
 *   - Ejecuciones posteriores (añadir entidades): lee el archivo existente e inyecta
 *     los nuevos imports y bindings usando marcadores, sin sobreescribir lo existente.
 *
 * Estructura de Contracts (v3.0.0):
 *   Services/Contracts/{Context}/{Interface}     ← NO Services/{Context}/Contracts/
 *   Repositories/Contracts/{Context}/{Interface}
 */
class ProviderGenerator extends AbstractComponentGenerator
{
    /** Marcador donde se inyectan nuevos bindings */
    private const BINDINGS_MARKER = '// {{BINDINGS_END}}';

    /** Marcador donde se inyectan nuevos use statements */
    private const IMPORTS_MARKER = '// {{IMPORTS_END}}';

    /**
     * Lista de componentes con su configuración para generar los bindings.
     *
     * @var array<int, array>
     */
    protected array $components;

    public function __construct(
        string $moduleName,
        string $modulePath,
        bool $isClean,
        array $components = [],
        array $componentConfig = []
    ) {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->components = $components;
    }

    /**
     * Genera o actualiza el Service Provider del módulo.
     *
     * Si el archivo ya existe, inyecta los nuevos bindings usando marcadores.
     * Si no existe, lo crea desde el stub con los bindings iniciales.
     *
     * @return void
     */
    public function generate(): void
    {
        $providerDir  = $this->providerDirectory();
        $providerFile = "{$providerDir}/{$this->providerClass()}.php";

        $this->ensureDirectoryExists($providerDir);

        [$imports, $bindings] = $this->buildImportsAndBindings();

        if (File::exists($providerFile)) {
            $this->injectIntoExisting($providerFile, $imports, $bindings);
        } else {
            $this->createFromStub($providerFile, $imports, $bindings);
        }
    }


    /**
     * El cuerpo del `boot()` del módulo — vacío salvo que alguien tenga algo que poner ahí.
     *
     * **Qué problema resuelve.** Un módulo recién generado no aparece en ningún menú, así que nadie
     * puede llegar a él más que escribiendo su dirección. Engancharlo es una línea; el problema es
     * que esa línea se escribe en el vocabulario del menú **del proyecto**, y este paquete no lo
     * conoce ni puede conocerlo — es público, y ese vocabulario no lo es.
     *
     * Las dos salidas evidentes fallan. Escribir el enganche a lo que se suponga que hay produce un
     * proveedor que llama a una clase inexistente, y eso **revienta en el arranque**: no es un
     * módulo que no se ve, es la aplicación entera que no responde, incluido el `artisan` con el que
     * se arreglaría. Dejarlo comentado como ejemplo no rompe nada, pero publica igualmente el nombre
     * de esa clase en un repositorio abierto.
     *
     * Así que el paquete no escribe nada y **abre el hueco**: si el proyecto —o una biblioteca
     * instalada— aporta un `provider-boot.stub`, su contenido entra aquí. Si no lo aporta nadie, que
     * es el caso normal, el `boot()` sale vacío y el módulo funciona exactamente igual.
     *
     * @return string  El cuerpo, ya con sus placeholders resueltos
     */
    private function buildBootBody(): string
    {
        $aportado = $this->getOptionalStubContent('provider-boot.stub', [
            'moduleName'    => $this->moduleName,
            'functionality' => $this->getFunctionality(),
        ], $this->componentConfig['context'] ?? null);

        if ($aportado === null) {
            return '//';
        }

        // El stub aportado se escribe sin sangrar; aquí dentro va a dos niveles.
        $lineas = explode("\n", trim($aportado));

        return implode("\n", array_map(
            fn (string $linea, int $i) => $i === 0 || trim($linea) === '' ? $linea : '        ' . $linea,
            $lineas,
            array_keys($lineas)
        ));
    }

    // ─── Creación inicial ─────────────────────────────────────────────────────

    /**
     * Crea el Provider desde el stub con los bindings iniciales.
     *
     * @param  string  $filePath  Ruta absoluta al archivo a crear
     * @param  string  $imports   Bloque de `use` statements
     * @param  string  $bindings  Bloque de `$this->app->bind(...)` calls
     * @return void
     */
    private function createFromStub(string $filePath, string $imports, string $bindings): void
    {
        $stub = $this->getStubContent('provider.stub', $this->isClean, [
            'namespace'      => $this->providerNamespace(),
            'providerName'   => $this->providerClass(),
            'modelImports'   => trim($imports),
            'modelBindings'  => rtrim($bindings),
            'importsMarker'  => self::IMPORTS_MARKER,
            'bindingsMarker' => self::BINDINGS_MARKER,
            'bootBody'       => $this->buildBootBody(),
        ]);

        $this->putFile(
            $filePath,
            $stub,
            'Provider creado: ' . $this->rutaVisible($filePath)
        );
    }

    // ─── Dónde vive el provider, y cómo se llama ──────────────────────────────

    /**
     * `Providers/{Contexto}/` — del MÓDULO, y uno por contexto.
     *
     * Un provider registra los bindings de las capas de su contexto: la interfaz central apunta a
     * la implementación central, y la del inquilino a la suya. Con uno solo para los dos, el
     * archivo acumulaba los bindings de ambos y era el único sitio del módulo donde los contextos
     * se mezclaban — justo el archivo que decide qué implementación se inyecta.
     *
     * No pertenece a ninguna subfuncionalidad, así que no baja a ninguna: vive al nivel del módulo,
     * junto a `Docs/`, `Routes/` y los seeders maestros.
     */
    private function providerDirectory(): string
    {
        $base   = $this->getComponentBasePath() . '/Providers';
        $folder = $this->getContextFolder();

        return $folder ? "{$base}/{$folder}" : $base;
    }

    /** El namespace que espeja esa carpeta. */
    private function providerNamespace(): string
    {
        $base  = "Modules\\{$this->moduleName}\\Providers";
        $ctxNs = $this->getContextNamespacePath();

        return $ctxNs ? "{$base}\\{$ctxNs}" : $base;
    }

    /** `CentralUserServiceProvider` en multiinquilino · `UserServiceProvider` en aplicación única. */
    private function providerClass(): string
    {
        return $this->prefixClass("{$this->moduleName}ServiceProvider");
    }

    // ─── Inyección incremental ────────────────────────────────────────────────

    /**
     * Inyecta nuevos imports y bindings en un Provider ya existente.
     * Busca los marcadores {{IMPORTS_END}} y {{BINDINGS_END}} para insertar.
     *
     * @param  string  $filePath  Ruta absoluta al Provider existente
     * @param  string  $imports   Nuevos `use` statements a añadir
     * @param  string  $bindings  Nuevas líneas `$this->app->bind(...)` a añadir
     * @return void
     */
    private function injectIntoExisting(string $filePath, string $imports, string $bindings): void
    {
        $content = File::get($filePath);
        $modified = false;

        // ── Inyectar imports ──────────────────────────────────────────────────
        $newImports = $this->filterNewLines($imports, $content);
        if ($newImports !== '') {
            if (str_contains($content, self::IMPORTS_MARKER)) {
                $content  = str_replace(self::IMPORTS_MARKER, trim($newImports) . "\n" . self::IMPORTS_MARKER, $content);
                $modified = true;
            } else {
                // Fallback: insertar después del último `use ...;`
                $content  = preg_replace(
                    '/(use [^;]+;)(?=(?:(?!use [^;]+;)[\s\S])*$)/',
                    "$1\n" . trim($newImports),
                    $content,
                    1
                );
                $modified = true;
            }
        }

        // ── Inyectar bindings ─────────────────────────────────────────────────
        $newBindings = $this->filterNewLines($bindings, $content);
        if ($newBindings !== '') {
            if (str_contains($content, self::BINDINGS_MARKER)) {
                $content  = str_replace(self::BINDINGS_MARKER, trim($newBindings) . "\n        " . self::BINDINGS_MARKER, $content);
                $modified = true;
            }
        }

        if ($modified) {
            // Por putFile, no por File::put: aquí se reescribe un archivo que YA existe en el
            // proyecto. Si la inyección lo dejara sin cerrar, escribirlo rompería el provider
            // del módulo entero — no solo el archivo nuevo de turno.
            $this->putFile(
                $filePath,
                $content,
                "Provider actualizado con nuevos bindings: {$this->moduleName}ServiceProvider.php"
            );
        } else {
            $this->info("   Provider sin cambios (bindings ya registrados).");
        }
    }

    /**
     * Filtra las líneas que ya existen en el archivo para no duplicar imports/bindings.
     *
     * @param  string  $block    Bloque de texto a filtrar
     * @param  string  $content  Contenido actual del archivo
     * @return string  Solo las líneas que no existen ya en el archivo
     */
    private function filterNewLines(string $block, string $content): string
    {
        $lines = array_filter(
            explode("\n", $block),
            fn (string $line) => trim($line) !== '' && !str_contains($content, trim($line))
        );

        return implode("\n", $lines);
    }

    // ─── Construcción de imports y bindings ───────────────────────────────────

    /**
     * Construye los bloques de imports (use statements) y bindings ($this->app->bind).
     *
     * Los namespaces salen de `namespaceForComponent()`, el mismo cálculo que usa cada generador
     * para decidir DÓNDE escribe su archivo. Se armaban aquí a mano, y desde que la
     * subfuncionalidad es carpeta en todas las capas faltaba ese último tramo: el
     * provider importaba `…\Services\InvoiceService` mientras el archivo estaba en
     * `…\Services\Invoice\InvoiceService`. PHP válido, cero placeholders, y el módulo entero sin
     * arrancar — el binding revienta al resolver el servicio. La misma forma de fallo que B13,
     * B15 y B17: dos mitades que dejan de coincidir.
     *
     * Estructura de Contracts (v3.0.0): {Capa}/Contracts/{Contexto}/{SubFuncionalidad}/
     *
     * @return array{0: string, 1: string}  [imports, bindings]
     */
    private function buildImportsAndBindings(): array
    {
        $imports  = '';
        $bindings = '';

        $usaLista = ! $this->isClean && ! empty($this->components);

        $componentList = $usaLista
            ? $this->components
            : [['name' => $this->moduleName, 'context' => $this->componentConfig['context'] ?? null, 'context_id' => $this->componentConfig['context_id'] ?? null]];

        foreach ($componentList as $component) {
            $modelName = Str::studly($component['name']);

            // La subfuncionalidad decide la última carpeta de cada capa. En la vía dinámica el
            // provider se instancia ANTES de que ModuleGenerator la rellene, así que se deriva
            // igual que allí: del nombre del componente.
            $component['subFeature'] ??= $usaLista ? $modelName : $this->getSubFeatureFolder();

            $classPrefix = $this->classPrefixFor($component);

            $serviceClass = $classPrefix . "{$modelName}Service";
            $serviceIface = $serviceClass . 'Interface';
            $repoClass    = $classPrefix . "{$modelName}Repository";
            $repoIface    = $repoClass . 'Interface';

            $serviceContractsNs = $this->namespaceForComponent('Services', $component, contracts: true);
            $serviceImplNs      = $this->namespaceForComponent('Services', $component);
            $repoContractsNs    = $this->namespaceForComponent('Repositories', $component, contracts: true);
            $repoImplNs         = $this->namespaceForComponent('Repositories', $component);

            $imports .= "use {$serviceContractsNs}\\{$serviceIface};\n";
            $imports .= "use {$serviceImplNs}\\{$serviceClass};\n";
            $imports .= "use {$repoContractsNs}\\{$repoIface};\n";
            $imports .= "use {$repoImplNs}\\{$repoClass};\n";

            $bindings .= "        \$this->app->bind({$serviceIface}::class, {$serviceClass}::class);\n";
            $bindings .= "        \$this->app->bind({$repoIface}::class, {$repoClass}::class);\n";
        }

        return [$imports, $bindings];
    }
}
