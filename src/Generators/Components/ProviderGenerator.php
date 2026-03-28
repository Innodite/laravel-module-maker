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
            'modelBindings' => trim($modelBindings),
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
     * @return array{0: string, 1: string}  [imports, bindings]
     */
    private function buildImportsAndBindings(): array
    {
        $imports  = '';
        $bindings = '';

        $componentList = (! $this->isClean && ! empty($this->components))
            ? $this->components
            : [['name' => $this->moduleName, 'context' => $this->componentConfig['context'] ?? null]];

        foreach ($componentList as $component) {
            $modelName  = Str::studly($component['name']);
            $contextKey = $component['context'] ?? null;

            // Derivar namespaces según el contexto del componente
            [$serviceNs, $repoNs] = $this->resolveNamespacesForComponent($modelName, $contextKey);

            $serviceClass    = ($contextKey ? $this->resolveClassPrefix($contextKey) : '') . "{$modelName}Service";
            $serviceIface    = $serviceClass . 'Interface';
            $repoClass       = ($contextKey ? $this->resolveClassPrefix($contextKey) : '') . "{$modelName}Repository";
            $repoIface       = $repoClass . 'Interface';

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

    /**
     * Resuelve los namespaces de Service y Repository para un componente según su contexto.
     *
     * @param  string       $modelName   Nombre del modelo en StudlyCase
     * @param  string|null  $contextKey  Clave del contexto o null
     * @return array{0: string, 1: string}  [serviceNamespace, repositoryNamespace]
     */
    private function resolveNamespacesForComponent(string $modelName, ?string $contextKey): array
    {
        if ($contextKey === null) {
            return [
                "Modules\\{$this->moduleName}\\Services",
                "Modules\\{$this->moduleName}\\Repositories",
            ];
        }

        try {
            $ctx      = \Innodite\LaravelModuleMaker\Support\ContextResolver::resolve($contextKey);
            $nsPath   = $ctx['namespace_path'] ?? '';
            $serviceNs = $nsPath
                ? "Modules\\{$this->moduleName}\\Services\\{$nsPath}"
                : "Modules\\{$this->moduleName}\\Services";
            $repoNs = $nsPath
                ? "Modules\\{$this->moduleName}\\Repositories\\{$nsPath}"
                : "Modules\\{$this->moduleName}\\Repositories";

            return [$serviceNs, $repoNs];
        } catch (\InvalidArgumentException) {
            return [
                "Modules\\{$this->moduleName}\\Services",
                "Modules\\{$this->moduleName}\\Repositories",
            ];
        }
    }

    /**
     * Retorna el prefijo de clase para un contexto dado.
     *
     * @param  string  $contextKey  Clave del contexto
     * @return string
     */
    private function resolveClassPrefix(string $contextKey): string
    {
        try {
            $ctx = \Innodite\LaravelModuleMaker\Support\ContextResolver::resolve($contextKey);
            return $ctx['class_prefix'] ?? '';
        } catch (\InvalidArgumentException) {
            return '';
        }
    }
}
