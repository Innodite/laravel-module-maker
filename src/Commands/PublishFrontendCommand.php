<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * PublishFrontendCommand — Publica los Composables Vue 3 del bridge Innodite
 *
 * Uso:
 *   php artisan innodite:publish-frontend
 *   php artisan innodite:publish-frontend --force   # sobreescribir existentes
 *
 * Publica en: resources/js/Composables/
 *   - useModuleContext.js  → Rutas conscientes de contexto
 *   - usePermissions.js   → Validación de permisos con doble estrategia
 */
class PublishFrontendCommand extends Command
{
    protected $signature = 'innodite:publish-frontend
        {--force : Sobreescribir archivos existentes sin confirmación}';

    protected $description = 'Publica los Composables Vue 3 del bridge Innodite en resources/js/Composables/.';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=blue;options=bold>Innodite ModuleMaker — Publicación de Frontend</>');
        $this->newLine();

        // ── Pre-flight: resources/js/ existe ─────────────────────────────────
        $jsPath = resource_path('js');

        if (!File::isDirectory($jsPath)) {
            $this->components->error("No se encontró resources/js/.");
            $this->newLine();
            $this->line('  ¿Está configurado el frontend? Opciones:');
            $this->line('    <comment>php artisan breeze:install vue</comment>');
            $this->line('    <comment>composer require inertiajs/inertia-laravel</comment>');
            return self::FAILURE;
        }

        // ── Verificar @inertiajs/vue3 en package.json ─────────────────────────
        $this->checkPackageJson();
        $this->newLine();

        // ── Publicar composables ──────────────────────────────────────────────
        $composablesPath = resource_path('js/Composables');
        $stubsPath       = __DIR__ . '/../../stubs/resources/js/Composables';

        if (!File::isDirectory($stubsPath)) {
            $this->components->error("Directorio de stubs no encontrado: {$stubsPath}");
            return self::FAILURE;
        }

        if (!File::isDirectory($composablesPath)) {
            File::makeDirectory($composablesPath, 0755, true);
            $this->components->twoColumnDetail('Directorio creado', $composablesPath);
        }

        $published = 0;
        $skipped   = 0;

        foreach (File::files($stubsPath) as $stub) {
            $filename = $stub->getFilename();
            $dest     = "{$composablesPath}/{$filename}";

            if (File::exists($dest) && !$this->option('force')) {
                $this->components->twoColumnDetail(
                    $filename,
                    '<fg=yellow>Ya existe — usa --force para sobreescribir</>'
                );
                $skipped++;
                continue;
            }

            File::copy($stub->getPathname(), $dest);
            $this->components->twoColumnDetail($filename, '<fg=green>Publicado</>');
            $published++;
        }

        $this->newLine();

        if ($published > 0) {
            $this->components->info("{$published} composable(s) publicado(s) correctamente.");
        }

        if ($skipped > 0) {
            $this->components->warn("{$skipped} archivo(s) omitido(s). Usa --force para sobreescribir.");
        }

        // ── Instrucciones de activación ───────────────────────────────────────
        if ($published > 0) {
            $this->newLine();
            $this->line('  <fg=cyan>Próximos pasos para activar el bridge:</>');
            $this->newLine();
            $this->line('  <fg=white;options=bold>1.</> Registra el middleware en <comment>bootstrap/app.php</comment>:');
            $this->newLine();
            $this->line('  <fg=gray>  ->withMiddleware(function (Middleware $middleware) {</>');
            $this->line('  <fg=gray>      $middleware->appendToGroup(\'web\', [</>');
            $this->line('  <fg=gray>          \Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge::class,</>');
            $this->line('  <fg=gray>      ]);</>');
            $this->line('  <fg=gray>  })</>');
            $this->newLine();
            $this->line('  <fg=white;options=bold>2.</> Ejecuta el diagnóstico para verificar el contrato de datos:');
            $this->line('     <comment>php artisan innodite:check-env</comment>');
            $this->newLine();
        }

        return self::SUCCESS;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function checkPackageJson(): void
    {
        $packageJsonPath = base_path('package.json');

        if (!File::exists($packageJsonPath)) {
            $this->components->warn('package.json no encontrado. No se pudo verificar dependencias JS.');
            return;
        }

        $content = json_decode(File::get($packageJsonPath), true);

        if (!is_array($content)) {
            $this->components->warn('package.json no es válido.');
            return;
        }

        $deps = array_merge(
            $content['dependencies']    ?? [],
            $content['devDependencies'] ?? []
        );

        $this->line('  <fg=cyan>Verificando dependencias JavaScript:</>');

        foreach (['@inertiajs/vue3', 'vue'] as $dep) {
            if (isset($deps[$dep])) {
                $this->components->twoColumnDetail($dep, "<fg=green>OK — {$deps[$dep]}</>");
            } else {
                $this->components->warn("{$dep} no encontrado en package.json.");
                if ($dep === '@inertiajs/vue3') {
                    $this->line('  Ejecuta: <comment>npm install @inertiajs/vue3</comment>');
                }
            }
        }
    }
}
