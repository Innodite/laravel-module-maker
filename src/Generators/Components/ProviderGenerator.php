<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;

/**
 * Genera el Service Provider del módulo con los bindings de Interface → Implementación.
 *
 * En modo dinámico (con componentes), genera un binding por cada componente
 * usando los namespaces correctos según el contexto de cada uno.
 *
 * En modo limpio, genera un binding base usando el nombre del módulo.
 */
class ProviderGenerator extends AbstractComponentGenerator
{
    /**
     * Lista de componentes con su configuración para generar los bindings.
     * Cada elemento debe tener al menos 'name' y opcionalmente 'context'.
     *
     * @var array<int, array>
     */
    protected array $components;

    /**
     * @param  string  $moduleName       Nombre del módulo
     * @param  string  $modulePath       Ruta absoluta al directorio del módulo
     * @param  bool    $isClean          true = stubs clean, false = stubs dynamic
     * @param  array   $components       Lista de componentes del módulo (para modo dinámico)
     * @param  array   $componentConfig  Configuración adicional
     */
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
     * Genera el archivo del Service Provider con todos los bindings del módulo.
     *
     * @return void
     */
    public function generate(): void
    {
        $providerDir = $this->getComponentBasePath() . '/Providers';
        $this->ensureDirectoryExists($providerDir);

        [$modelImports, $modelBindings] = $this->buildImportsAndBindings();

        $stub = $this->getStubContent('provider.stub', $this->isClean, [
            'module'        => $this->moduleName,
            'modelImports'  => trim($modelImports),
            'modelBindings' => rtrim($modelBindings),
        ]);

        $this->putFile(
            "{$providerDir}/{$this->moduleName}ServiceProvider.php",
            $stub,
            "Provider creado: Modules/{$this->moduleName}/Providers/{$this->moduleName}ServiceProvider.php"
        );
    }

    /**
     * Construye las líneas de imports y bindings para el provider.
     * En modo dinámico usa los componentes con su contexto.
     * En modo limpio usa el nombre del módulo sin contexto.
     *
     * Cuando el contexto de un componente es 'tenant', se usa la clave 'tenant'
     * para resolver el tenant específico mediante ContextResolver::resolveTenant(),
     * obteniendo así el namespace_path y class_prefix correctos.
     *
     * @return array{0: string, 1: string}  [imports, bindings]
     */
    private function buildImportsAndBindings(): array
    {
        $imports  = '';
        $bindings = '';

        $componentList = (! $this->isClean && ! empty($this->components))
            ? $this->components
            : [['name' => $this->moduleName, 'context' => $this->componentConfig['context'] ?? null, 'tenant' => $this->componentConfig['tenant'] ?? null]];

        foreach ($componentList as $component) {
            $modelName  = Str::studly($component['name']);
            $contextKey = $component['context'] ?? null;
            $tenantKey  = $component['tenant'] ?? null;

            // Resolver el contexto efectivo: si es 'tenant', usar el tenant específico
            $effectiveCtx = null;
            if ($contextKey === 'tenant' && $tenantKey !== null) {
                try {
                    $effectiveCtx = \Innodite\LaravelModuleMaker\Support\ContextResolver::resolveTenant($tenantKey);
                } catch (\InvalidArgumentException) {
                }
            } elseif ($contextKey !== null) {
                try {
                    $effectiveCtx = \Innodite\LaravelModuleMaker\Support\ContextResolver::resolve($contextKey);
                } catch (\InvalidArgumentException) {
                }
            }

            $classPrefix = $effectiveCtx['class_prefix'] ?? '';
            $nsPath      = $effectiveCtx['namespace_path'] ?? '';

            $serviceClass = $classPrefix . "{$modelName}Service";
            $serviceIface = $serviceClass . 'Interface';
            $repoClass    = $classPrefix . "{$modelName}Repository";
            $repoIface    = $repoClass . 'Interface';

            $serviceNs = $nsPath
                ? "Modules\\{$this->moduleName}\\Services\\{$nsPath}"
                : "Modules\\{$this->moduleName}\\Services";
            $repoNs = $nsPath
                ? "Modules\\{$this->moduleName}\\Repositories\\{$nsPath}"
                : "Modules\\{$this->moduleName}\\Repositories";

            $imports .= "use {$serviceNs}\\Contracts\\{$serviceIface};\n";
            $imports .= "use {$serviceNs}\\{$serviceClass};\n";
            $imports .= "use {$repoNs}\\Contracts\\{$repoIface};\n";
            $imports .= "use {$repoNs}\\{$repoClass};\n";

            $bindings .= "        \$this->app->bind(\n";
            $bindings .= "            {$serviceIface}::class,\n";
            $bindings .= "            {$serviceClass}::class\n";
            $bindings .= "        );\n";
            $bindings .= "        \$this->app->bind(\n";
            $bindings .= "            {$repoIface}::class,\n";
            $bindings .= "            {$repoClass}::class\n";
            $bindings .= "        );\n";
        }

        return [$imports, $bindings];
    }
}
