<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class TestContextConfigService
{
    public function getModulesBasePath(): string
    {
        return (string) config('make-module.module_path', base_path('Modules'));
    }

    public function getContextsPath(): string
    {
        return (string) config('make-module.contexts_path', base_path('module-maker-config/contexts.json'));
    }

    public function getTestConfigPath(string $module): string
    {
        return $this->ensureTestsDirectory($module) . '/test-config.json';
    }

    public function ensureTestsDirectory(string $module): string
    {
        $testsPath = $this->getModulesBasePath() . "/{$module}/Tests";
        File::ensureDirectoryExists($testsPath);

        return $testsPath;
    }

    /**
    * @return array<string, array{key:string,label:string,folder:string,group:string,db_connection:?string,db_database:?string,enabled:bool,seeder:?string,env:array<string,string>}>
     */
    public function getContextDefinitions(): array
    {
        $contextsPath = $this->getContextsPath();

        if (!File::exists($contextsPath)) {
            throw new RuntimeException("No se encontró contexts.json en: {$contextsPath}");
        }

        $decoded = json_decode((string) File::get($contextsPath), true);

        if (!is_array($decoded) || !is_array($decoded['contexts'] ?? null)) {
            throw new RuntimeException('El archivo contexts.json no tiene una estructura válida.');
        }

        $definitions = [];

        foreach ($decoded['contexts'] as $group => $items) {
            if (!is_array($items)) {
                continue;
            }

            // Detectar si es array asociativo (contexto único) vs array indexado (lista)
            $isAssociative = array_keys($items) !== range(0, count($items) - 1);
            
            // Array asociativo → contexto único (central, shared, tenant_shared)
            if ($isAssociative) {
                $item   = $items;
                $folder = str_replace('\\', '/', (string) ($item['folder'] ?? ''));
                
                if ($folder === '') {
                    continue;
                }

                $key = $this->resolveContextKey((string) $group, $item, $folder);

                $definitions[$key] = [
                    'key' => $key,
                    'label' => (string) ($item['id'] ?? Str::headline($key)),
                    'folder' => $folder,
                    'group' => (string) $group,
                    'db_connection' => null,
                    'db_database' => null,
                    'enabled' => true,
                    'seeder' => null,
                    'env' => [],
                ];
                
                continue;
            }
            
            // Array indexado → múltiples tenants
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $folder = str_replace('\\', '/', (string) ($item['folder'] ?? ''));
                if ($folder === '') {
                    continue;
                }

                $key = $this->resolveContextKey((string) $group, $item, $folder);

                $definitions[$key] = [
                    'key' => $key,
                    'label' => (string) ($item['id'] ?? Str::headline($key)),
                    'folder' => $folder,
                    'group' => (string) $group,
                    'db_connection' => null,
                    'db_database' => null,
                    'enabled' => true,
                    'seeder' => null,
                    'env' => [],
                ];
            }
        }

        ksort($definitions);

        return $definitions;
    }

    /**
     * @return array{_readme:string,contexts:array<string, array<string,mixed>>}
     */
    public function getMergedModuleTestConfig(string $module): array
    {
        $definitions = $this->getContextDefinitions();
        $configPath = $this->getTestConfigPath($module);
        $existing = File::exists($configPath)
            ? $this->loadExistingConfig($configPath)
            : ['_readme' => '', 'contexts' => []];

        $mergedContexts = [];

        foreach ($definitions as $key => $definition) {
            $existingContext = $existing['contexts'][$key] ?? [];
            if (!is_array($existingContext)) {
                $existingContext = [];
            }

            $existingContext = $this->normalizeLegacyContextValues($key, $existingContext);

            $mergedContexts[$key] = array_merge($definition, $existingContext);
            $mergedContexts[$key]['env'] = is_array($mergedContexts[$key]['env'] ?? null)
                ? $mergedContexts[$key]['env']
                : [];
        }

        foreach ($existing['contexts'] as $key => $context) {
            if (!array_key_exists($key, $mergedContexts) && is_array($context)) {
                $mergedContexts[$key] = $context;
                $mergedContexts[$key]['env'] = is_array($mergedContexts[$key]['env'] ?? null)
                    ? $mergedContexts[$key]['env']
                    : [];
            }
        }

        ksort($mergedContexts);

        return [
            '_readme' => 'Configuración de tests por contexto. Generado por innodite:test-sync. Debes definir db_connection y db_database en cada contexto que quieras ejecutar, además de seeder, enabled y env si aplica.',
            'contexts' => $mergedContexts,
        ];
    }

    /**
     * @return array{_readme:string,contexts:array<string, array<string,mixed>>}
     */
    public function syncModuleTestConfig(string $module): array
    {
        $config = $this->getMergedModuleTestConfig($module);
        $configPath = $this->getTestConfigPath($module);

        File::put(
            $configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        @chmod($configPath, 0666);

        return $config;
    }

    /**
     * @return array{_readme:string,contexts:array<string, array<string,mixed>>}
     */
    public function loadModuleTestConfig(string $module): array
    {
        $configPath = $this->getTestConfigPath($module);

        if (!File::exists($configPath)) {
            return [
                '_readme' => '',
                'contexts' => [],
            ];
        }

        return $this->loadExistingConfig($configPath);
    }

    public function normalizeContextKey(string $value): string
    {
        $value = str_replace(['\\', '/', '-'], '_', $value);

        return Str::snake($value);
    }

    /**
     * @return array<int,string>
     */
    public function getTenantMarkers(): array
    {
        $definitions = $this->getContextDefinitions();
        $markers = [];

        foreach ($definitions as $definition) {
            if (($definition['group'] ?? null) !== 'tenant') {
                continue;
            }

            $folder = str_replace('\\', '/', (string) ($definition['folder'] ?? ''));
            $marker = trim((string) basename($folder));
            if ($marker !== '') {
                $markers[] = $marker;
            }
        }

        return array_values(array_unique($markers));
    }

    /**
     * @param array<string,mixed> $item
     */
    private function resolveContextKey(string $group, array $item, string $folder): string
    {
        if (in_array($group, ['central', 'shared', 'tenant_shared'], true)) {
            return $group;
        }

        // Prioridad 1: ID del contexto (arquitectura v3.5.0)
        $id = trim((string) ($item['id'] ?? ''));
        if ($id !== '') {
            return $this->normalizeContextKey($id);
        }

        // Prioridad 2: permission_prefix (legacy)
        $permissionPrefix = trim((string) ($item['permission_prefix'] ?? ''));
        if ($permissionPrefix !== '') {
            return $this->normalizeContextKey($permissionPrefix);
        }

        // Fallback: basename del folder
        return $this->normalizeContextKey((string) basename($folder));
    }

    /**
     * @return array{_readme:string,contexts:array<string, array<string,mixed>>}
     */
    private function loadExistingConfig(string $configPath): array
    {
        $decoded = json_decode((string) File::get($configPath), true);

        if (!is_array($decoded)) {
            return ['_readme' => '', 'contexts' => []];
        }

        return [
            '_readme' => (string) ($decoded['_readme'] ?? ''),
            'contexts' => is_array($decoded['contexts'] ?? null) ? $decoded['contexts'] : [],
        ];
    }

    /**
     * Limpia defaults heredados de versiones previas del generador.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function normalizeLegacyContextValues(string $contextKey, array $context): array
    {
        if (
            $contextKey === 'central'
            && (($context['db_connection'] ?? null) === 'sqlite')
            && (($context['db_database'] ?? null) === ':memory:')
        ) {
            $context['db_connection'] = null;
            $context['db_database'] = null;
        }

        return $context;
    }
}