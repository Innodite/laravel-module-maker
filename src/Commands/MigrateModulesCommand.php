<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * MigrateModulesCommand — Migra módulos de v2.x a v3.0.0
 *
 * Cambios aplicados:
 *   - Renombra routes/ → Routes/
 *   - Valida estructura de carpetas v3.0.0
 *
 * Uso:
 *   php artisan innodite:migrate-modules
 *   php artisan innodite:migrate-modules --dry-run   # Solo mostrar cambios
 */
class MigrateModulesCommand extends Command
{
    protected $signature = 'innodite:migrate-modules
        {--dry-run : Mostrar cambios sin aplicarlos}';

    protected $description = 'Migra módulos de v2.x (routes/) a v3.0.0 (Routes/) para compatibilidad.';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=blue;options=bold>Innodite ModuleMaker — Migración de Módulos v2.x → v3.0.0</>');
        $this->newLine();

        $modulesPath = base_path('Modules');

        if (!File::isDirectory($modulesPath)) {
            $this->components->error('No se encontró el directorio Modules/');
            return self::FAILURE;
        }

        $modules = File::directories($modulesPath);

        if (empty($modules)) {
            $this->components->warn('No hay módulos para migrar.');
            return self::SUCCESS;
        }

        $isDryRun = $this->option('dry-run');
        $migrated = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ($modules as $modulePath) {
            $moduleName   = basename($modulePath);
            $oldRoutesDir = "{$modulePath}/routes";   // lowercase v2.x
            $newRoutesDir = "{$modulePath}/Routes";   // uppercase v3.0.0

            // Si ya usa v3.0.0 (Routes/), saltar
            if (File::isDirectory($newRoutesDir)) {
                $this->components->twoColumnDetail(
                    $moduleName,
                    '<fg=green>Ya usa v3.0.0 (Routes/)</>'
                );
                $skipped++;
                continue;
            }

            // Si no tiene routes/ (lowercase), saltar
            if (!File::isDirectory($oldRoutesDir)) {
                $this->components->twoColumnDetail(
                    $moduleName,
                    '<fg=yellow>Sin carpeta de rutas</>'
                );
                $skipped++;
                continue;
            }

            // Migrar: routes/ → Routes/
            if ($isDryRun) {
                $this->components->twoColumnDetail(
                    $moduleName,
                    '<fg=cyan>[DRY-RUN] Renombrar routes/ → Routes/</>'
                );
                $migrated++;
            } else {
                try {
                    File::move($oldRoutesDir, $newRoutesDir);
                    $this->components->twoColumnDetail(
                        $moduleName,
                        '<fg=green>✓ Migrado: routes/ → Routes/</>'
                    );
                    $migrated++;
                } catch (\Exception $e) {
                    $this->components->twoColumnDetail(
                        $moduleName,
                        '<fg=red>✗ Error: ' . $e->getMessage() . '</>'
                    );
                    $errors++;
                }
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->components->info("Vista previa completada. {$migrated} módulo(s) se migrarían.");
            $this->line('  Ejecuta sin --dry-run para aplicar los cambios.');
        } else {
            $this->components->info("Migración completada.");
            $this->line("  ✓ Migrados: {$migrated}");
            $this->line("  - Omitidos: {$skipped}");
            if ($errors > 0) {
                $this->line("  ✗ Errores:  {$errors}");
            }
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
