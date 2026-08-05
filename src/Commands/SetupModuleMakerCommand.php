<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

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
    protected $signature = 'innodite:module-setup
        {--mode= : Modo del proyecto: single-app | multitenant-shared | multitenant-per-tenant}';

    protected $description = 'Configura el paquete: elige el modo del proyecto y crea module-maker-config/ en el project root.';

    public function handle(): void
    {
        $this->info("Iniciando configuración de laravel-module-maker...");
        $this->newLine();

        // ── El modo, lo primero ───────────────────────────────────────────────
        // La norma dice que el modo se ELIGE AL INSTALAR, no que se teclee después en un archivo
        // de configuración. Y va primero porque decide la forma de todo lo demás: si se pregunta al
        // final, lo que ya se generó nació con la estructura de otro modo.
        $this->configureMode();

        // ── Carpeta de módulos ────────────────────────────────────────────────
        // Las rutas salen de la configuración, no de base_path(): son las MISMAS que leen los
        // generadores. Instalar en un sitio mientras se genera y se leen stubs de otro es la
        // familia de fallo de siempre —dos mitades que dejan de coincidir—, y aquí el síntoma es
        // desconcertante: el usuario edita un stub publicado y el paquete sigue usando el suyo.
        $modulesPath = config('make-module.module_path');
        $this->ensureDirectory($modulesPath, "Modules/");

        // ── Carpeta de configuración (project root) ───────────────────────────
        $configPath = config('make-module.config_path');
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
        $this->line("  → Ejecuta: <comment>php artisan innodite:make-module NombreModulo SubFuncionalidad</comment>");
    }

    // ─── El modo del proyecto ─────────────────────────────────────────────────

    /**
     * Pregunta el modo y lo deja escrito, o dice exactamente qué escribir.
     *
     * Los tres modos son igual de legítimos y la elección da forma a **cada archivo generado**: el
     * eje de contexto, el prefijo de las clases, la conexión del modelo y el middleware de cada ruta.
     * Por eso no hay valor por defecto y por eso se pregunta aquí — adivinar produce una estructura
     * equivocada multiplicada por cada módulo del proyecto, y eso solo se descubre tarde.
     */
    private function configureMode(): void
    {
        $mode = $this->resolveMode();

        if ($mode === null) {
            $this->warn('Sin modo elegido no se genera nada, así que este paso no se puede omitir.');
            $this->line('  Vuelve a ejecutar el comando, o pásalo directo: <comment>--mode=single-app</comment>');

            return;
        }

        $this->line("  Modo elegido: <comment>{$mode->label()}</comment>");

        $this->persistMode($mode);
    }

    /** @return ModuleMode|null  null si no se pudo determinar y no hay con quién hablar */
    private function resolveMode(): ?ModuleMode
    {
        $opcion = trim((string) $this->option('mode'));

        if ($opcion !== '') {
            $elegido = ModuleMode::tryFrom($opcion);

            if ($elegido === null) {
                $this->error("El modo '{$opcion}' no existe. Son: "
                    . implode(' · ', array_column(ModuleMode::cases(), 'value')));

                return null;
            }

            return $elegido;
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        $etiquetas = [];

        foreach (ModuleMode::cases() as $caso) {
            $etiquetas[$caso->value] = $caso->label();
        }

        $this->line('  ¿Qué tipo de proyecto es? Decide la forma de todo lo que se genere.');

        $respuesta = $this->choice('  Modo', $etiquetas, null, null, false);

        // choice() devuelve la etiqueta cuando las claves son strings; se recupera el valor.
        return ModuleMode::tryFrom($respuesta)
            ?? ModuleMode::tryFrom((string) array_search($respuesta, $etiquetas, true));
    }

    /**
     * Escribe el modo en el `.env`, y si no puede lo dice — nunca anuncia un éxito que no ocurrió.
     *
     * Esa última parte es la lección de A15: el comando anunciaba «DatabaseSeeder modificado» aunque
     * el reemplazo no hubiera encajado, y el usuario se quedaba creyendo que estaba configurado.
     */
    private function persistMode(ModuleMode $mode): void
    {
        $envPath = base_path('.env');
        $linea   = 'MODULE_MAKER_MODE=' . $mode->value;

        if (! File::exists($envPath)) {
            $this->warn('  No hay .env en la raíz del proyecto, así que el modo no se ha escrito.');
            $this->line("  Añade esta línea a tu .env:  <comment>{$linea}</comment>");

            return;
        }

        $contenido = File::get($envPath);

        if (preg_match('/^MODULE_MAKER_MODE=(.*)$/m', $contenido, $actual) === 1) {
            $valorActual = trim($actual[1]);

            if ($valorActual === $mode->value) {
                $this->line('  El .env ya declaraba ese modo: no se toca nada.');

                return;
            }

            if (! $this->confirm("  El .env dice '{$valorActual}'. ¿Cambiarlo a '{$mode->value}'?", false)) {
                $this->warn('  El modo se queda como estaba.');

                return;
            }

            File::put($envPath, preg_replace('/^MODULE_MAKER_MODE=.*$/m', $linea, $contenido));
            $this->info("  ✅ .env actualizado: {$linea}");

            return;
        }

        File::put($envPath, rtrim($contenido, "\n") . "\n\n{$linea}\n");
        $this->info("  ✅ Modo escrito en .env: {$linea}");
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

        // La carpeta destino es la que LEE el resolutor de stubs (nivel 2), no una derivada de
        // $configPath: si el proyecto reconfigura `stubs.path`, publicar en otro sitio deja al
        // usuario editando archivos que nadie lee.
        $destPath = config('make-module.stubs.path') . '/contextual';

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
