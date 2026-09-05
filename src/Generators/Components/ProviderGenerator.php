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
        $providerDir  = $this->getComponentBasePath() . '/Providers';
        $providerFile = "{$providerDir}/{$this->moduleName}ServiceProvider.php";

        $this->ensureDirectoryExists($providerDir);

        [$imports, $bindings] = $this->buildImportsAndBindings();

        if (File::exists($providerFile)) {
            $this->injectIntoExisting($providerFile, $imports, $bindings);
        } else {
            $this->createFromStub($providerFile, $imports, $bindings);
        }
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
            'namespace'      => "Modules\\{$this->moduleName}\\Providers",
            'providerName'   => "{$this->moduleName}ServiceProvider",
            'modelImports'   => trim($imports),
            'modelBindings'  => rtrim($bindings),
            'importsMarker'  => self::IMPORTS_MARKER,
            'bindingsMarker' => self::BINDINGS_MARKER,
        ]);

        $this->putFile(
            $filePath,
            $stub,
            "Provider creado: Modules/{$this->moduleName}/Providers/{$this->moduleName}ServiceProvider.php"
        );
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
