<?php

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Comando de instalación del paquete v3.0.0.
 *
 * Crea la estructura en el project root:
 *   module-maker-config/
 *   ├── contexts.json
 *   └── stubs/
 *       └── contextual/    ← stubs personalizables (override del paquete)
 *
 * Crea la carpeta de módulos:
 *   Modules/
 */
class SetupModuleMakerCommand extends Command
{
    protected $signature = 'innodite:module-setup';

    protected $description = 'Configura el paquete v3.0.0: crea module-maker-config/ en el project root y publica stubs y contexts.json.';

    public function handle(): void
    {
        $this->info("Iniciando configuración de laravel-module-maker v3.0.0...");
        $this->newLine();

        // ── Carpeta de módulos ────────────────────────────────────────────────
        $modulesPath = base_path('Modules');
        $this->ensureDirectory($modulesPath, "Modules/");

        // ── Carpeta de configuración (project root) ───────────────────────────
        $configPath = base_path('module-maker-config');
        $this->ensureDirectory($configPath, "module-maker-config/");

        // ── Stubs ─────────────────────────────────────────────────────────────
        $this->publishStubs($configPath);

        // ── contexts.json ─────────────────────────────────────────────────────
        $this->publishContextsJson($configPath);

        // ── DatabaseSeeder ────────────────────────────────────────────────────
        $this->modifyDatabaseSeeder();

        $this->newLine();
        $this->info("Configuración completa.");
        $this->line("  → Edita <comment>module-maker-config/contexts.json</comment> con los contextos de tu proyecto.");
        $this->line("  → Personaliza stubs en <comment>module-maker-config/stubs/contextual/</comment>.");
        $this->line("  → Ejecuta: <comment>php artisan innodite:make-module NombreModulo</comment>");
    }

    /**
     * Publica los stubs contextual/ del paquete en module-maker-config/stubs/contextual/.
     * Si la carpeta del usuario ya existe, no sobreescribe (el usuario puede tener customizaciones).
     *
     * → TAREA DELEGABLE A AGENTE OPERATIVO:
     *   La creación individual de cada archivo .stub dentro de stubs/contextual/
     *   puede ser procesada por un agente operativo usando la instrucción:
     *   "Copia los archivos de stubs/contextual/ del paquete a
     *    module-maker-config/stubs/contextual/ en el proyecto del usuario,
     *    sin sobreescribir archivos existentes."
     *
     * @param  string  $configPath  Ruta a module-maker-config/ en el project root
     * @return void
     */
    protected function publishStubs(string $configPath): void
    {
        $packageStubsPath = dirname(__DIR__, 2) . '/stubs/contextual';
        $destPath         = "{$configPath}/stubs/contextual";

        if (!File::isDirectory($packageStubsPath)) {
            $this->warn("   No se encontró stubs/contextual/ en el paquete. Creando carpeta vacía...");
            File::makeDirectory($destPath, 0755, true, true);
            return;
        }

        if (File::isDirectory($destPath)) {
            $this->warn("   stubs/contextual/ ya existe. No se sobreescribió. Edítalo manualmente si necesitas cambios.");
            return;
        }

        File::copyDirectory($packageStubsPath, $destPath);
        $this->info("✅ Stubs publicados en: module-maker-config/stubs/contextual/");
    }

    /**
     * Publica el archivo contexts.json en module-maker-config/.
     *
     * @param  string  $configPath  Ruta a module-maker-config/ en el project root
     * @return void
     */
    protected function publishContextsJson(string $configPath): void
    {
        $source      = dirname(__DIR__, 2) . '/stubs/contexts.json';
        $destination = "{$configPath}/contexts.json";

        if (File::exists($destination)) {
            $this->warn("   contexts.json ya existe. No se sobreescribió.");
            $this->line("   Edítalo manualmente en: <comment>module-maker-config/contexts.json</comment>");
            return;
        }

        if (!File::exists($source)) {
            $this->error("No se encontró el template contexts.json en el paquete.");
            return;
        }

        File::copy($source, $destination);
        $this->info("✅ contexts.json publicado en: module-maker-config/contexts.json");
        $this->line("   Edita este archivo para configurar los contextos (Central, Shared, Tenants).");
    }

    /**
     * Modifica el DatabaseSeeder.php del proyecto para incluir los seeders de módulos.
     *
     * @return void
     */
    protected function modifyDatabaseSeeder(): void
    {
        $seederPath = database_path('seeders/DatabaseSeeder.php');

        if (!File::exists($seederPath)) {
            $this->warn("   DatabaseSeeder.php no encontrado. Asegúrate de que el proyecto está inicializado.");
            return;
        }

        $seederContent = File::get($seederPath);
        $callLine      = "        \$this->call(InnoditeModuleSeeder::class);";
        $useStatement  = "use Innodite\\LaravelModuleMaker\\Database\\Seeders\\InnoditeModuleSeeder;";

        if (str_contains($seederContent, $useStatement) && str_contains($seederContent, $callLine)) {
            $this->warn("   DatabaseSeeder.php ya está configurado. No se realizaron cambios.");
            return;
        }

        if (!str_contains($seederContent, $useStatement)) {
            $seederContent = str_replace(
                "use Illuminate\\Database\\Seeder;",
                "use Illuminate\\Database\\Seeder;\n{$useStatement}",
                $seederContent
            );
        }

        if (!str_contains($seederContent, $callLine)) {
            $comment       = "        // Generado por LaravelModuleMaker — ejecuta seeders de todos los módulos";
            $seederContent = str_replace(
                "public function run(): void\n    {\n",
                "public function run(): void\n    {\n{$comment}\n{$callLine}\n",
                $seederContent
            );
        }

        File::put($seederPath, $seederContent);
        $this->info("✅ DatabaseSeeder.php modificado para incluir los seeders de módulos.");
    }

    /**
     * Crea un directorio si no existe y reporta el resultado.
     *
     * @param  string  $path   Ruta absoluta
     * @param  string  $label  Etiqueta para el mensaje de consola
     * @return void
     */
    private function ensureDirectory(string $path, string $label): void
    {
        if (File::exists($path)) {
            $this->line("   <comment>{$label}</comment> ya existe.");
        } else {
            File::makeDirectory($path, 0755, true);
            $this->info("✅ Carpeta creada: {$label}");
        }
    }
}
