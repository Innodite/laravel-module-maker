<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\TestContextConfigService;
use RuntimeException;
use Throwable;

class TestSyncCommand extends Command
{
    protected $signature = 'innodite:test-sync
        {module? : Nombre del módulo a sincronizar}
        {--all : Sincroniza el archivo Tests/test-config.json de todos los módulos}';

    protected $description = 'Genera o sincroniza el archivo Tests/test-config.json a partir de module-maker-config/contexts.json.';

    public function handle(): int
    {
        $service = new TestContextConfigService();

        try {
            $modules = $this->resolveModules($service);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($modules as $module) {
            $config = $service->syncModuleTestConfig($module);
            $configPath = $service->getTestConfigPath($module);

            $this->components->info("Sincronizado: {$module}");
            $this->line('  Archivo:   ' . $configPath);
            $this->line('  Contextos: ' . count($config['contexts']));
            $this->newLine();
        }

        $this->components->info('test-sync completado correctamente.');

        return self::SUCCESS;
    }

    /**
     * @return array<int,string>
     */
    private function resolveModules(TestContextConfigService $service): array
    {
        $modulesPath = $service->getModulesBasePath();

        if (!File::isDirectory($modulesPath)) {
            throw new RuntimeException("No existe la carpeta de módulos: {$modulesPath}");
        }

        if ($this->option('all')) {
            return array_map(static fn (string $path): string => basename($path), File::directories($modulesPath));
        }

        $module = trim((string) $this->argument('module'));
        if ($module === '') {
            throw new RuntimeException('Debes indicar un módulo o usar --all.');
        }

        if (!File::isDirectory("{$modulesPath}/{$module}")) {
            throw new RuntimeException("El módulo '{$module}' no existe.");
        }

        return [$module];
    }
}