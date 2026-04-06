<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\ModuleAuditor;

/**
 * ModuleCheckCommand — Diagnóstico de integridad del entorno ModuleMaker v3.0.0
 *
 * Uso:
 *   php artisan innodite:module-check
 *
 * Verifica:
 *   1. Integridad del archivo contexts.json (existencia, JSON válido, claves requeridas)
 *   2. Permisos de escritura en Modules/, routes/ y storage/logs/
 *   3. Colisiones de nombres entre módulos existentes y sus ServiceProviders
 */
class ModuleCheckCommand extends Command
{
    protected $signature = 'innodite:module-check';

    protected $description = 'Diagnóstico de integridad del entorno ModuleMaker v3.0.0.';

    // ─── Entry point ──────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=blue;options=bold>Innodite ModuleMaker — Diagnóstico del Entorno v3.0.0</>');
        $this->newLine();

        $allPassed = true;

        $allPassed = $this->checkContextsJson()     && $allPassed;
        $this->newLine();
        $allPassed = $this->checkWritePermissions() && $allPassed;
        $this->newLine();
        $allPassed = $this->checkNameCollisions()   && $allPassed;
        $this->newLine();
        $this->checkAuditLog();
        $this->newLine();

        if ($allPassed) {
            $this->components->info('Todos los diagnósticos pasaron correctamente.');
            return self::SUCCESS;
        }

        $this->components->warn('Se detectaron problemas. Revisa los errores anteriores.');
        return self::FAILURE;
    }

    // ─── Diagnóstico 1: contexts.json ─────────────────────────────────────────

    private function checkContextsJson(): bool
    {
        $this->line('  <fg=cyan;options=bold>1. Verificando contexts.json</>');

        $path = config('make-module.contexts_path');

        if (!File::exists($path)) {
            $this->components->error("contexts.json no encontrado en: {$path}");
            $this->line("     Ejecuta: <comment>php artisan innodite:module-setup</comment>");
            return false;
        }

        $content = File::get($path);
        $data    = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->components->error('contexts.json contiene JSON inválido: ' . json_last_error_msg());
            return false;
        }

        if (!isset($data['contexts']) || !is_array($data['contexts'])) {
            $this->components->error('contexts.json no tiene la clave raíz "contexts" (array).');
            return false;
        }

        // Verificar claves de contexto requeridas
        $requiredContexts = ['central', 'shared', 'tenant_shared', 'tenant'];
        $missingContexts  = array_diff($requiredContexts, array_keys($data['contexts']));

        if (!empty($missingContexts)) {
            $this->components->warn(
                'contexts.json incompleto. Faltan contextos: ' . implode(', ', $missingContexts)
            );
            return false;
        }

        // Verificar estructura interna de cada sub-contexto
        $requiredItemKeys = ['name', 'class_prefix', 'folder', 'namespace_path', 'route_file'];
        $errors           = [];

        foreach ($data['contexts'] as $contextKey => $items) {
            if (!is_array($items)) {
                $errors[] = "contexts.{$contextKey} debe ser un array de sub-contextos";
                continue;
            }

            // Estructura híbrida: central/shared/tenant_shared son objetos únicos (tienen 'id'),
            // tenant es un array indexado de objetos. Normalizar a array iterable.
            $subItems = isset($items['id']) ? [$items] : $items;

            foreach ($subItems as $i => $item) {
                if (!is_array($item)) {
                    $errors[] = "contexts.{$contextKey}[{$i}] debe ser un array";
                    continue;
                }
                foreach ($requiredItemKeys as $k) {
                    if (!array_key_exists($k, $item)) {
                        $errors[] = "contexts.{$contextKey}[{$i}] falta la clave '{$k}'";
                    }
                }
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->components->error($error);
            }
            return false;
        }

        $count = 0;
        foreach ($data['contexts'] as $items) {
            if (is_array($items)) {
                $count += isset($items['id']) ? 1 : count($items);
            }
        }
        $this->components->twoColumnDetail(
            basename($path),
            "<fg=green>OK — {$count} sub-contextos encontrados</>"
        );

        return true;
    }

    // ─── Diagnóstico 2: Permisos de escritura ─────────────────────────────────

    private function checkWritePermissions(): bool
    {
        $this->line('  <fg=cyan;options=bold>2. Verificando permisos de escritura</>');

        $paths = [
            'Modules/'           => base_path('Modules'),
            'routes/web.php'     => base_path('routes/web.php'),
            'routes/tenant.php'  => base_path('routes/tenant.php'),
            'routes/api.php'     => base_path('routes/api.php'),
            'storage/logs/'      => storage_path('logs'),
        ];

        $allOk = true;

        foreach ($paths as $label => $path) {
            if (!File::exists($path)) {
                $this->components->twoColumnDetail(
                    $label,
                    '<fg=yellow>No encontrado — se creará al generar el primer módulo</>'
                );
                continue;
            }

            $writable = is_writable($path);

            $this->components->twoColumnDetail(
                $label,
                $writable
                    ? '<fg=green>OK — Escribible</>'
                    : '<fg=red>ERROR — Sin permiso de escritura</>'
            );

            if (!$writable) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    // ─── Diagnóstico 3: Colisiones de nombres ─────────────────────────────────

    private function checkNameCollisions(): bool
    {
        $this->line('  <fg=cyan;options=bold>3. Verificando colisiones de nombres</>');

        $modulesPath = base_path('Modules');

        if (!File::isDirectory($modulesPath)) {
            $this->components->twoColumnDetail(
                'Modules/',
                '<fg=yellow>No existe — sin módulos generados aún</>'
            );
            return true;
        }

        $dirs       = File::directories($modulesPath);
        $collisions = [];
        $seen       = [];

        foreach ($dirs as $dir) {
            $name = basename($dir);

            // Colisiones de directorio duplicado (poco probable pero verificable)
            if (in_array($name, $seen, true)) {
                $collisions[] = "{$name} (directorio duplicado)";
            }

            $seen[] = $name;

            // Verificar que el ServiceProvider tenga namespace correcto
            $providerFile = "{$dir}/Providers/{$name}ServiceProvider.php";
            if (File::exists($providerFile)) {
                $content = File::get($providerFile);
                if (!str_contains($content, "namespace Modules\\{$name}\\Providers")) {
                    $collisions[] = "{$name} (namespace incorrecto en ServiceProvider)";
                }
            }

            // Verificar colisiones en la tabla de migraciones (nombres de migración duplicados)
            $migDir = "{$dir}/Database/Migrations";
            if (File::isDirectory($migDir)) {
                $migFiles = File::allFiles($migDir);
                $migNames = [];
                foreach ($migFiles as $migFile) {
                    $basename = $migFile->getFilenameWithoutExtension();
                    // Extraer la parte del nombre sin el timestamp
                    $migName = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $basename);
                    if ($migName && in_array($migName, $migNames, true)) {
                        $collisions[] = "{$name} → migración duplicada: {$migName}";
                    }
                    if ($migName) {
                        $migNames[] = $migName;
                    }
                }
            }
        }

        if (!empty($collisions)) {
            foreach ($collisions as $c) {
                $this->components->error("Colisión detectada: {$c}");
            }
            return false;
        }

        $count = count($dirs);
        $this->components->twoColumnDetail(
            'Módulos',
            "<fg=green>OK — {$count} módulo(s) verificado(s), sin colisiones</>"
        );

        return true;
    }

    // ─── Info adicional: Log de auditoría ─────────────────────────────────────

    private function checkAuditLog(): void
    {
        $this->line('  <fg=cyan;options=bold>4. Log de auditoría</>');

        $entries = ModuleAuditor::readLog();
        $logPath = ModuleAuditor::logPath();

        if (empty($entries)) {
            $this->components->twoColumnDetail(
                'module_maker.log',
                '<fg=yellow>Sin entradas (no se ha generado ningún módulo aún)</>'
            );
            return;
        }

        $this->components->twoColumnDetail(
            'module_maker.log',
            "<fg=green>OK — {$logPath}</>"
        );

        $this->newLine();
        $this->line('  <fg=gray>Últimas 5 operaciones:</> ');

        $last5 = array_slice($entries, -5);
        $rows  = array_map(fn ($e) => [
            $e['timestamp'] ?? '—',
            $e['event']     ?? '—',
            $e['module']    ?? $e['context_key'] ?? '—',
        ], $last5);

        $this->table(['Timestamp', 'Evento', 'Módulo / Contexto'], $rows);
    }
}
