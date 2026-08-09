<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Innodite\LaravelModuleMaker\Support\Disk;
use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\PrintsHeader;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Innodite\LaravelModuleMaker\Commands\Concerns\RehearsesChanges;
use Illuminate\Support\Facades\File;

/**
 * PublishFrontendCommand — Publica el frontend Vue 3 del bridge Innodite
 *
 * Uso:
 *   php artisan innodite:publish-frontend
 *   php artisan innodite:publish-frontend --force   # sobreescribir existentes
 *
 * Publica en resources/js/Composables/
 *   - useModuleContext.js  → Rutas conscientes de contexto
 *   - usePermissions.js    → Validación de permisos con doble estrategia
 *   - useAvisos.js         → Mensajes y confirmaciones, sin alert() ni confirm()
 *
 * Publica en resources/js/Components/
 *   - InnoditeAviso.vue    → Pinta los avisos y las confirmaciones
 *   - InnoditeModal.vue    → La ventana de crear/ver/editar: tamaños, rejilla y pasos
 *
 * Los dos grupos se publican juntos porque la vista generada importa de ambos: publicar solo los
 * composables deja la pantalla con imports de componentes que no existen, y al revés igual.
 */
class PublishFrontendCommand extends Command
{
    use RehearsesChanges;
    use PrintsHeader;
    use ReportsFailures;

    protected $signature = 'innodite:publish-frontend
        {--force : Publica sin pedir confirmación, sobreescribiendo lo que ya exista}
        {--dry-run : Ensayo: enseña lo que publicaría, sin escribir nada}';

    protected $description = 'Publica los Composables y Componentes Vue 3 del bridge Innodite en resources/js/.';

    /** Carpeta de stubs → destino en el proyecto. El orden es el del listado en pantalla. */
    private const GRUPOS = ['Composables', 'Components'];

    public function handle(): int
    {
        // El ensayo se enciende antes de nada y se apaga pase lo que pase: el interruptor es
        // del proceso, así que dejarlo puesto convertiría el siguiente comando en un ensayo
        // que nadie pidió.
        $this->startRehearsal();

        try {
            return $this->ejecutar();
        } finally {
            $this->reportRehearsal();
        }
    }

    private function ejecutar(): int
    {
        $this->cabecera('Publicación del frontend');

        // ── Pre-flight: resources/js/ existe ─────────────────────────────────
        $jsPath = resource_path('js');

        if (!File::isDirectory($jsPath)) {
            $this->fallo(
                'no existe resources/js/ en este proyecto.',
                'monta el frontend antes — php artisan breeze:install vue · o bien '
                . 'composer require inertiajs/inertia-laravel',
                'El bridge se publica DENTRO de resources/js: sin esa carpeta no hay dónde ponerlo.'
            );
            return self::FAILURE;
        }

        // ── Verificar @inertiajs/vue3 en package.json ─────────────────────────
        $this->checkPackageJson();
        $this->newLine();

        // ── Publicar composables y componentes ────────────────────────────────
        $published = 0;
        $skipped   = 0;

        foreach (self::GRUPOS as $grupo) {
            $stubsPath   = __DIR__ . "/../../stubs/resources/js/{$grupo}";
            $destinoPath = resource_path("js/{$grupo}");

            if (!File::isDirectory($stubsPath)) {
                $this->fallo(
                    "no está la carpeta de stubs del paquete: {$stubsPath}",
                    'reinstala el paquete — composer reinstall innodite/laravel-module-maker',
                    'Esa carpeta viaja dentro del paquete: si falta, la instalación quedó a medias.'
                );
                return self::FAILURE;
            }

            if (!File::isDirectory($destinoPath)) {
                Disk::makeDirectory($destinoPath, 0755, true);
                $this->components->twoColumnDetail('Directorio creado', $destinoPath);
            }

            $this->line("  <fg=cyan>{$grupo}</>");

            foreach (File::files($stubsPath) as $stub) {
                $filename = $stub->getFilename();
                $dest     = "{$destinoPath}/{$filename}";

                if (File::exists($dest) && !$this->option('force')) {
                    $this->components->twoColumnDetail(
                        $filename,
                        '<fg=yellow>Ya existe — usa --force para sobreescribir</>'
                    );
                    $skipped++;
                    continue;
                }

                Disk::copy($stub->getPathname(), $dest);
                $this->components->twoColumnDetail($filename, '<fg=green>Publicado</>');
                $published++;
            }

            $this->newLine();
        }

        if ($published > 0) {
            $this->components->info("{$published} archivo(s) publicado(s) correctamente.");
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
            $this->line('     <comment>php artisan innodite:doctor</comment>');
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
