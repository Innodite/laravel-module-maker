<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * CheckEnvCommand — Diagnóstico del "Contrato de Datos" del bridge Innodite
 *
 * Verifica que todos los elementos necesarios para que el bridge
 * Frontend-Backend funcione correctamente estén presentes.
 *
 * Uso:
 *   php artisan innodite:check-env
 *
 * Verificaciones:
 *   1. Modelo User — Spatie HasRoles o InnoditeUserPermissions implementado
 *   2. HandleInertiaRequests — auth.permissions y auth.context compartidos
 *   3. InnoditeContextBridge — registrado en el stack middleware web
 */
class CheckEnvCommand extends Command
{
    protected $signature = 'innodite:check-env';

    protected $description = 'Verifica el "Contrato de Datos" del bridge Frontend-Backend de Innodite.';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=blue;options=bold>Innodite ModuleMaker — Verificación del Contrato de Datos</>');
        $this->newLine();

        $allPassed = true;

        $allPassed = $this->checkUserModel()             && $allPassed;
        $this->newLine();
        $allPassed = $this->checkHandleInertiaRequests() && $allPassed;
        $this->newLine();
        $this->checkMiddlewareRegistration();
        $this->newLine();

        if ($allPassed) {
            $this->components->info('El contrato de datos está completo. El bridge está listo para usar.');
        } else {
            $this->components->warn('Faltan elementos del contrato. Aplica los bloques de código sugeridos arriba.');
        }

        return $allPassed ? self::SUCCESS : self::FAILURE;
    }

    // ─── Verificación 1: Modelo User ─────────────────────────────────────────

    private function checkUserModel(): bool
    {
        $this->line('  <fg=cyan;options=bold>1. Modelo User — soporte de permisos</>');

        $userFile = $this->findUserModel();

        if ($userFile === null) {
            $this->components->error('No se encontró el modelo User en app/Models/User.php, app/User.php ni en Modules/*/Models/.');
            return false;
        }

        $content = File::get($userFile);

        // Detección Spatie Permission
        if (str_contains($content, 'HasRoles') || str_contains($content, 'Spatie\\Permission')) {
            $this->components->twoColumnDetail('Spatie\\Permission\\Traits\\HasRoles', '<fg=green>OK — detectado</>');
            return true;
        }

        // Detección InnoditeUserPermissions
        if (str_contains($content, 'InnoditeUserPermissions') && str_contains($content, 'getInnoditePermissions')) {
            $this->components->twoColumnDetail('InnoditeUserPermissions', '<fg=green>OK — implementado</>');
            return true;
        }

        // Ninguna opción encontrada
        $this->components->error('El modelo User no tiene soporte de permisos compatible con Innodite.');
        $this->newLine();
        $this->line('  <fg=yellow>Opción A — Instala Spatie Permission (recomendado):</>');
        $this->displayCodeBlock("composer require spatie/laravel-permission\nphp artisan vendor:publish --provider=\"Spatie\\Permission\\PermissionServiceProvider\"");
        $this->line('  Luego añade el trait al modelo User:');
        $this->displayCodeBlock(
            "use Spatie\\Permission\\Traits\\HasRoles;\n\n"
            . "class User extends Authenticatable\n"
            . "{\n"
            . "    use HasRoles;\n"
            . "}"
        );

        $this->newLine();
        $this->line('  <fg=yellow>Opción B — Implementa InnoditeUserPermissions (sin dependencias):</>');
        $this->displayCodeBlock(
            "use Innodite\\LaravelModuleMaker\\Contracts\\InnoditeUserPermissions;\n\n"
            . "class User extends Authenticatable implements InnoditeUserPermissions\n"
            . "{\n"
            . "    public function getInnoditePermissions(): array\n"
            . "    {\n"
            . "        return \$this->permissions->pluck('name')->toArray();\n"
            . "    }\n"
            . "}"
        );

        return false;
    }

    // ─── Verificación 2: HandleInertiaRequests ────────────────────────────────

    private function checkHandleInertiaRequests(): bool
    {
        $this->line('  <fg=cyan;options=bold>2. HandleInertiaRequests — datos compartidos</>');

        $middlewareFile = app_path('Http/Middleware/HandleInertiaRequests.php');

        if (!File::exists($middlewareFile)) {
            $this->components->warn('HandleInertiaRequests.php no encontrado. ¿Está instalado Inertia?');
            $this->line('  Ejecuta: <comment>composer require inertiajs/inertia-laravel</comment>');
            $this->line('  Luego:   <comment>php artisan inertia:middleware</comment>');
            return false;
        }

        $content = File::get($middlewareFile);
        $passed  = true;

        // Verificar auth.permissions
        if (str_contains($content, 'auth.permissions') || str_contains($content, "'permissions'")) {
            $this->components->twoColumnDetail('auth.permissions', '<fg=green>OK — encontrado</>');
        } else {
            $this->components->error('auth.permissions no está siendo compartido.');
            $this->newLine();
            $this->line('  <fg=yellow>Añade esto al método share() de HandleInertiaRequests:</>');
            $this->displayCodeBlock(
                "return array_merge(parent::share(\$request), [\n"
                . "    'auth' => [\n"
                . "        'user'        => \$request->user(),\n"
                . "        'permissions' => \$request->user()\n"
                . "            ? \$request->user()->getAllPermissions()->pluck('name') // Spatie\n"
                . "            : [],\n"
                . "    ],\n"
                . "]);"
            );
            $passed = false;
        }

        // Verificar auth.context (lo inyecta el middleware automáticamente, solo es info)
        if (str_contains($content, 'auth.context') || str_contains($content, 'InnoditeContextBridge')) {
            $this->components->twoColumnDetail('auth.context', '<fg=green>OK — detectado</>');
        } else {
            $this->components->twoColumnDetail(
                'auth.context',
                '<fg=cyan>Será inyectado por InnoditeContextBridge (middleware)</>'
            );
        }

        return $passed;
    }

    // ─── Verificación 3: Registro del middleware ──────────────────────────────

    private function checkMiddlewareRegistration(): void
    {
        $this->line('  <fg=cyan;options=bold>3. InnoditeContextBridge — registro en middleware</>');

        $filesToCheck = array_filter([
            base_path('bootstrap/app.php'),
            app_path('Http/Kernel.php'),
        ], fn ($f) => File::exists($f));

        $isRegistered = false;

        foreach ($filesToCheck as $file) {
            if (str_contains(File::get($file), 'InnoditeContextBridge')) {
                $isRegistered = true;
                break;
            }
        }

        if ($isRegistered) {
            $this->components->twoColumnDetail('InnoditeContextBridge', '<fg=green>OK — registrado</>');
            return;
        }

        $this->components->warn('InnoditeContextBridge no está registrado como middleware.');
        $this->newLine();

        if (File::exists(base_path('bootstrap/app.php'))) {
            $this->line('  <fg=yellow>Añade esto en bootstrap/app.php (Laravel 11+):</>');
            $this->displayCodeBlock(
                "->withMiddleware(function (Middleware \$middleware) {\n"
                . "    \$middleware->appendToGroup('web', [\n"
                . "        \\Innodite\\LaravelModuleMaker\\Middleware\\InnoditeContextBridge::class,\n"
                . "    ]);\n"
                . "})"
            );
        } else {
            $this->line('  <fg=yellow>Añade esto en app/Http/Kernel.php en el grupo web:</>');
            $this->displayCodeBlock(
                "'web' => [\n"
                . "    // ...\n"
                . "    \\Innodite\\LaravelModuleMaker\\Middleware\\InnoditeContextBridge::class,\n"
                . "],"
            );
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Busca el modelo User en las ubicaciones estándar de Laravel y en Modules/. */
    private function findUserModel(): ?string
    {
        $paths = [
            app_path('Models/User.php'),
            app_path('User.php'),
        ];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        // Búsqueda en Modules/*/Models/**/*User*.php (proyectos modularizados)
        $modulesGlob = glob(base_path('Modules/*/Models/*User*.php')) ?: [];
        $deepGlob    = glob(base_path('Modules/*/Models/**/*User*.php')) ?: [];

        $found = array_merge($modulesGlob, $deepGlob);

        return !empty($found) ? $found[0] : null;
    }

    /** Renderiza un bloque de código con fondo oscuro para destacarlo visualmente. */
    private function displayCodeBlock(string $code): void
    {
        $this->newLine();
        foreach (explode("\n", $code) as $line) {
            $this->line("  <fg=white;bg=default>  {$line}</>");
        }
        $this->newLine();
    }
}
