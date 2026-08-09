<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Components\ProjectSeederGenerator;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\TenancyPackage;

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
        {--mode= : Modo del proyecto: single-app | multitenant-shared | multitenant-per-tenant}
        {--tenancy= : Paquete de tenencia del proyecto (solo multitenant): stancl | none}';

    protected $description = 'Configura el paquete: elige el modo del proyecto y crea module-maker-config/ en el project root.';

    public function handle(): void
    {
        $this->info("Iniciando configuración de laravel-module-maker...");
        $this->newLine();

        // ── El modo, lo primero ───────────────────────────────────────────────
        // La norma dice que el modo se ELIGE AL INSTALAR, no que se teclee después en un archivo
        // de configuración. Y va primero porque decide la forma de todo lo demás: si se pregunta al
        // final, lo que ya se generó nació con la estructura de otro modo.
        $mode = $this->configureMode();

        // ── El paquete de tenencia, si el modo lo pide ────────────────────────
        // Va inmediatamente después del modo y por el mismo motivo: decide la envoltura de cada
        // archivo de rutas que se genere, y preguntarlo más tarde deja escritas las rutas de los
        // primeros módulos sin ella.
        $this->configureTenancyPackage($mode);

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

        // ── Seeders de despliegue del proyecto ────────────────────────────────
        // Son del proyecto y no de un módulo —uno, o dos en multitenant—, así que se escriben al
        // instalar: existen antes que el primer módulo, y `make-module` solo añade la entrada de
        // cada subfuncionalidad al orden que estos leen.
        $desplegadores = $this->publishDeploySeeders($mode);

        // ── DatabaseSeeder ────────────────────────────────────────────────────
        $this->modifyDatabaseSeeder($desplegadores);

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
    private function configureMode(): ?ModuleMode
    {
        $mode = $this->resolveMode();

        if ($mode === null) {
            $this->warn('Sin modo elegido no se genera nada, así que este paso no se puede omitir.');
            $this->line('  Vuelve a ejecutar el comando, o pásalo directo: <comment>--mode=single-app</comment>');

            return null;
        }

        $this->line("  Modo elegido: <comment>{$mode->label()}</comment>");

        $this->persistMode($mode);

        return $mode;
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

    // ─── El paquete de tenencia del proyecto ──────────────────────────────────

    /**
     * Pregunta con qué paquete de tenencia corre el proyecto — solo si el modo tiene tenants.
     *
     * En una aplicación única no se pregunta porque no hay nada que envolver: ni dominios centrales
     * que separar ni tenant que identificar. Preguntarlo igual sería pedir una decisión que no
     * cambia un solo archivo generado.
     */
    private function configureTenancyPackage(?ModuleMode $mode): void
    {
        if ($mode === null || ! $mode->hasContextAxis()) {
            return;
        }

        $elegido = $this->resolveTenancyPackage();

        if ($elegido === null) {
            // Sin elegir NO se bloquea la instalación, y ahí está la diferencia con el modo: el modo
            // decide la forma de cada archivo y no tiene respuesta correcta, mientras que aquí la
            // ausencia tiene una salida honesta —escribir las rutas sin envoltura, con la nota que
            // dice dónde va—. El proyecto arranca y el hueco queda a la vista.
            $this->warn('  Sin paquete de tenencia declarado, las rutas generadas saldrán sin envoltura.');
            $this->line('  Cada archivo dirá dónde va y qué haría stancl. Para declararlo: '
                . '<comment>--tenancy=stancl</comment>');

            return;
        }

        $this->line("  Paquete de tenencia: <comment>{$elegido->label()}</comment>");

        $this->persistEnvKey('MODULE_MAKER_TENANCY_PACKAGE', $elegido->value, 'paquete de tenencia');
    }

    /** @return TenancyPackage|null  null si no se pudo determinar y no hay con quién hablar */
    private function resolveTenancyPackage(): ?TenancyPackage
    {
        $opcion = trim((string) $this->option('tenancy'));

        if ($opcion !== '') {
            $elegido = TenancyPackage::tryFrom($opcion);

            if ($elegido === null) {
                $this->error("FALLA: el paquete de tenencia '{$opcion}' todavía no está soportado.");
                $this->line('  · FIX: usa uno de estos — '
                    . implode(' · ', array_column(TenancyPackage::cases(), 'value'))
                    . '. Con «none» las rutas salen sin envoltura y el archivo dice dónde va.');

                return null;
            }

            return $elegido;
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        $etiquetas = [];

        foreach (TenancyPackage::cases() as $caso) {
            $etiquetas[$caso->value] = $caso->label();
        }

        $this->line('  ¿Con qué paquete de tenencia corre el proyecto? Decide la envoltura de las '
            . 'rutas generadas.');

        $respuesta = $this->choice('  Paquete de tenencia', $etiquetas, null, null, false);

        // choice() devuelve la etiqueta cuando las claves son strings; se recupera el valor.
        return TenancyPackage::tryFrom($respuesta)
            ?? TenancyPackage::tryFrom((string) array_search($respuesta, $etiquetas, true));
    }

    // ─── Escritura en el .env ─────────────────────────────────────────────────

    /**
     * Escribe el modo en el `.env`, y si no puede lo dice — nunca anuncia un éxito que no ocurrió.
     *
     * Esa última parte es la lección de A15: el comando anunciaba «DatabaseSeeder modificado» aunque
     * el reemplazo no hubiera encajado, y el usuario se quedaba creyendo que estaba configurado.
     */
    private function persistMode(ModuleMode $mode): void
    {
        $this->persistEnvKey('MODULE_MAKER_MODE', $mode->value, 'modo');
    }

    /**
     * Deja una clave escrita en el `.env` del proyecto, o dice exactamente qué línea añadir.
     *
     * Es el mismo procedimiento para las dos decisiones que se toman al instalar —el modo y el
     * paquete de tenencia—, y por eso está escrito una vez: dos copias del mismo trámite acaban
     * respondiendo distinto al `.env` que ya declaraba otra cosa, que es justo el caso delicado.
     *
     * @param  string  $clave  Nombre de la variable de entorno
     * @param  string  $valor  Valor a dejar escrito
     * @param  string  $queEs  Cómo se llama en los mensajes ('modo', 'paquete de tenencia')
     */
    private function persistEnvKey(string $clave, string $valor, string $queEs): void
    {
        $envPath = base_path('.env');
        $linea   = "{$clave}={$valor}";

        if (! File::exists($envPath)) {
            $this->warn("  No hay .env en la raíz del proyecto, así que el {$queEs} no se ha escrito.");
            $this->line("  Añade esta línea a tu .env:  <comment>{$linea}</comment>");

            return;
        }

        $contenido = File::get($envPath);

        if (preg_match("/^{$clave}=(.*)$/m", $contenido, $actual) === 1) {
            $valorActual = trim($actual[1]);

            if ($valorActual === $valor) {
                $this->line("  El .env ya declaraba ese {$queEs}: no se toca nada.");

                return;
            }

            if (! $this->confirm("  El .env dice '{$valorActual}'. ¿Cambiarlo a '{$valor}'?", false)) {
                $this->warn("  El {$queEs} se queda como estaba.");

                return;
            }

            File::put($envPath, preg_replace("/^{$clave}=.*$/m", $linea, $contenido));
            $this->info("  ✅ .env actualizado: {$linea}");

            return;
        }

        File::put($envPath, rtrim($contenido, "\n") . "\n\n{$linea}\n");
        $this->info("  ✅ Escrito en .env: {$linea}");
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
     * Escribe los seeders de despliegue del proyecto y devuelve sus nombres de clase.
     *
     * Sin modo elegido no se escribe ninguno: la forma del despliegue depende del modo —uno en una
     * aplicación única, dos en multitenant—, y escribir el que no era deja al proyecto con un archivo
     * que no se sobreescribe nunca.
     *
     * **El modo llega por parámetro, no se relee de la configuración.** Acaba de escribirse en el
     * `.env`, y el `.env` se lee al arrancar: preguntarle a `ModuleMode::current()` en esta misma
     * ejecución devolvería el valor anterior —o ninguno, en una instalación nueva—, y el instalador
     * escribiría el despliegue de otro modo justo el día que se elige.
     *
     * @return array<int, string>
     */
    protected function publishDeploySeeders(?ModuleMode $mode): array
    {
        if ($mode === null) {
            $this->warn('   Sin modo elegido no se escriben los seeders de despliegue.');
            $this->line('   Elige el modo y vuelve a ejecutar este comando.');

            return [];
        }

        return (new ProjectSeederGenerator($mode, $this))->generate();
    }

    /**
     * Engancha los seeders de despliegue al `DatabaseSeeder.php` del proyecto.
     *
     * No hace falta importarlos: viven en `database/seeders/`, el mismo namespace que el propio
     * `DatabaseSeeder`.
     *
     * **En multitenant se engancha solo el central**, y eso es a propósito. `db:seed` corre contra
     * una base de datos; el despliegue de un tenant se ejecuta **una vez por tenant**, dentro del
     * contexto de cada uno, y eso lo orquesta el paquete de tenancy del proyecto, no un `call()` en
     * un archivo. Enganchar aquí el de tenant lo lanzaría contra la base central.
     *
     * @param  array<int, string>  $desplegadores
     */
    protected function modifyDatabaseSeeder(array $desplegadores): void
    {
        if ($desplegadores === []) {
            return;
        }

        $principal = $desplegadores[0];
        $callLine  = "        \$this->call({$principal}::class);";

        $seederPath = database_path('seeders/DatabaseSeeder.php');

        if (! File::exists($seederPath)) {
            $this->warn('   No hay database/seeders/DatabaseSeeder.php, así que no se enganchó nada.');
            $this->line("   Añade esta línea dentro de su run():  <comment>{$callLine}</comment>");

            return;
        }

        $seederContent = File::get($seederPath);

        if (str_contains($seederContent, "{$principal}::class")) {
            $this->warn('   DatabaseSeeder.php ya llama al despliegue. No se realizaron cambios.');

            return;
        }

        if (str_contains($seederContent, 'InnoditeModuleSeeder')) {
            // El enganche de la v3: recorría Modules/*/Database/Seeders/ por orden alfabético del
            // sistema de archivos. En la v4 los seeders viven un par de carpetas más adentro y el
            // orden lo declara el desarrollador, así que ahí ya no encontraba nada.
            $this->warn('   DatabaseSeeder.php llama a InnoditeModuleSeeder, que ya no existe.');
            $this->line('   Quita esa línea y su import; el despliegue lo hace ahora '
                . "<comment>{$principal}</comment>.");
        }

        $comment  = '        // Despliegue del proyecto: esquema, datos y permisos, en el orden'
            . ' declarado en config/make-module.php';
        $anclaje  = "public function run(): void\n    {\n";
        $reemplazo = "{$anclaje}{$comment}\n{$callLine}\n";

        if (! str_contains($seederContent, $anclaje)) {
            // A15: nunca se anuncia un éxito que no ocurrió. El run() del proyecto puede estar escrito
            // de otra forma —sin tipo de retorno, con atributos encima—, y ahí el reemplazo no encaja.
            $this->warn('   No reconocí el run() de DatabaseSeeder.php, así que no se tocó.');
            $this->line("   Añade esta línea dentro de su run():  <comment>{$callLine}</comment>");

            return;
        }

        File::put($seederPath, str_replace($anclaje, $reemplazo, $seederContent));

        $this->info("✅ DatabaseSeeder.php llama ahora a {$principal}.");
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
