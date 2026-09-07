<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Innodite\LaravelModuleMaker\Support\Disk;
use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\PrintsHeader;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Innodite\LaravelModuleMaker\Commands\Concerns\RehearsesChanges;
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
    use RehearsesChanges;
    use PrintsHeader;
    use ReportsFailures;

    protected $signature = 'innodite:module-setup
        {--mode= : Modo del proyecto: single-app | multitenant-shared | multitenant-per-tenant}
        {--tenancy= : Paquete de tenencia del proyecto (solo multitenant): stancl | none}
        {--frontend= : Contra qué se generan las vistas: default | innodite}
        {--dry-run : Ensayo: enseña lo que instalaría, sin escribir nada}';

    protected $description = 'Configura el paquete: elige el modo del proyecto y crea module-maker-config/ en el project root.';

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

    /**
     * Devuelve código de salida, como los otros ocho.
     *
     * Devolvía `void`, y en consola eso significa «éxito» siempre: `innodite:module-setup && …`
     * encadenaba lo siguiente aunque la instalación se hubiera detenido por falta de modo o de
     * paquete de tenencia. Un instalador que no puede fallar es un instalador en el que no se puede
     * confiar dentro de un script.
     */
    private function ejecutar(): int
    {
        $this->cabecera('Instalación en el proyecto');

        // ── El modo, lo primero ───────────────────────────────────────────────
        // La norma dice que el modo se ELIGE AL INSTALAR, no que se teclee después en un archivo
        // de configuración. Y va primero porque decide la forma de todo lo demás: si se pregunta al
        // final, lo que ya se generó nació con la estructura de otro modo.
        $mode = $this->configureMode();

        // Y sin modo se para aquí. Antes seguía adelante: creaba las carpetas, publicaba los stubs y
        // terminaba anunciando «Configuración completa» sobre un proyecto que no había elegido modo
        // —justo lo que la regla 5 prohíbe—. Ahora el código de salida lo dice también, que es lo
        // único que lee un script.
        if ($mode === null) {
            return self::FAILURE;
        }

        // ── El paquete de tenencia, si el modo lo pide ────────────────────────
        // Va inmediatamente después del modo y por el mismo motivo: decide la envoltura de cada
        // archivo de rutas que se genere, y preguntarlo más tarde deja escritas las rutas de los
        // primeros módulos sin ella.
        //
        // En multitenant es OBLIGATORIO y detiene la instalación, igual que el modo. La alternativa
        // —dejar que la configuración se quede sin declarar y confiar en que alguien la escriba
        // después— apuesta a que el usuario lea la documentación antes de generar su primer módulo.
        // No la lee: genera, ve archivos escritos y sigue.
        if (! $this->configureTenancyPackage($mode)) {
            return self::FAILURE;
        }

        // ── Carpeta de módulos ────────────────────────────────────────────────
        // Las rutas salen de la configuración, no de base_path(): son las MISMAS que leen los
        // generadores. Instalar en un sitio mientras se genera y se leen stubs de otro es la
        // familia de fallo de siempre —dos mitades que dejan de coincidir—, y aquí el síntoma es
        // desconcertante: el usuario edita un stub publicado y el paquete sigue usando el suyo.
        $modulesPath = config('make-module.module_path');
        $this->ensureDirectory($modulesPath, "Modules/");

        // ── El frontend: contra qué se generan las vistas ─────────────────────
        // No configura nada del proyecto —eso no es de este paquete—: solo decide con qué
        // componentes se escriben las pantallas que genere.
        $this->configureFrontend();

        // ── Carpeta de configuración (project root) ───────────────────────────
        $configPath = config('make-module.config_path');
        $this->ensureDirectory($configPath, "module-maker-config/");

        // ── config/make-module.php ────────────────────────────────────────────
        $this->publishPackageConfig();

        // ── Los stubs NO se publican al instalar ──────────────────────────────
        //
        // ⛔ Publicarlos aquí dejaba 37 copias congeladas en cada proyecto, y una copia no se
        // actualiza nunca más: quien instale una versión nueva del paquete seguirá generando con
        // las plantillas del día que instaló, sin un solo aviso. Personalizar stubs es una decisión
        // deliberada y por eso tiene su propio comando: `innodite:publish-stubs`.

        // ── contexts.json, solo donde hay contextos ───────────────────────────
        //
        // En aplicación única el catálogo salía con `{"contexts": {}}` — un archivo vacío que había
        // que explicar cada vez y que no decide nada. Donde no hay eje de contexto no hay catálogo.
        if ($mode?->hasContextAxis()) {
            $this->publishContextsJson($configPath, $mode);
        }

        // ── Seeders de despliegue del proyecto ────────────────────────────────
        // Son del proyecto y no de un módulo —uno, o dos en multitenant—, así que se escriben al
        // instalar: existen antes que el primer módulo, y `make-module` solo añade la entrada de
        // cada subfuncionalidad al orden que estos leen.
        $desplegadores = $this->publishDeploySeeders($mode);

        // ── DatabaseSeeder ────────────────────────────────────────────────────
        $this->modifyDatabaseSeeder($desplegadores);

        $this->newLine();
        $this->hecho("Configuración completa.");

        if ($mode?->hasContextAxis()) {
            $this->line("  → Edita <comment>module-maker-config/contexts.json</comment> con los contextos de tu proyecto.");
        }

        $this->line("  → Ejecuta: <comment>php artisan innodite:make-module NombreModulo</comment>");
        $this->newLine();
        $this->line("  <fg=gray>¿Quieres personalizar lo que se genera? <comment>php artisan innodite:publish-stubs</comment></>");
        $this->line("  <fg=gray>⚠️ Una plantilla copiada deja de actualizarse con el paquete: copia solo las que vayas a tocar.</>");

        return self::SUCCESS;
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
                $this->fallo(
                    "el modo '{$opcion}' no existe.",
                    'usa uno de estos — '
                    . implode(' · ', array_column(ModuleMode::cases(), 'value')),
                    'El modo decide la forma de cada archivo que se genere después.'
                );

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
     *
     * @return bool  false cuando el modo lo exige y no se pudo determinar: la instalación se detiene
     */
    private function configureTenancyPackage(?ModuleMode $mode): bool
    {
        if ($mode === null || ! $mode->hasContextAxis()) {
            return true;
        }

        $elegido = $this->resolveTenancyPackage();

        if ($elegido === null) {
            // Se pregunta y se exige, como el modo. «none» es una respuesta válida —y la que reciben
            // los proyectos que no corren stancl—, pero tiene que **elegirse**: no declarar nada
            // deja las rutas sin envoltura por omisión, y eso solo se descubre cuando el módulo ya
            // está generado y sirviéndose en el dominio equivocado.
            $this->warn('  Sin paquete de tenencia elegido no se puede envolver una sola ruta, así '
                . 'que este paso no se puede omitir en un proyecto multitenant.');
            $this->line('  Vuelve a ejecutar el comando, o pásalo directo: '
                . '<comment>--tenancy=stancl</comment> · <comment>--tenancy=none</comment> si tu '
                . 'proyecto usa otro y prefieres escribir tú la envoltura.');

            return false;
        }

        $this->line("  Paquete de tenencia: <comment>{$elegido->label()}</comment>");

        $this->persistEnvKey('MODULE_MAKER_TENANCY_PACKAGE', $elegido->value, 'paquete de tenencia');

        return true;
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

    // ─── El frontend ──────────────────────────────────────────────────────────

    /**
     * Decide contra qué biblioteca se escriben las vistas generadas.
     *
     * ⛔ **No instala ni configura nada del frontend.** Ni composables, ni componentes, ni
     * `app.js`, ni el middleware de Inertia. Este paquete GENERA; quien monta el andamiaje del
     * frontend es el proyecto, o la biblioteca de interfaz que lo instale.
     *
     * La dirección de esa dependencia va en un solo sentido y conviene tenerla clara: **la
     * biblioteca de la casa instala este paquete, y este paquete nunca la instala a ella**. Es un
     * paquete público; declararla como dependencia obligaría a todo el que lo instale a poder
     * descargar un producto que no es suyo.
     *
     * Por eso el modo `innodite` no arrastra nada: solo cambia la forma de las vistas que se
     * escriben, dando por hecho que la biblioteca ya está montada en el proyecto.
     */
    private function configureFrontend(): void
    {
        $elegido = $this->resolveFrontend();

        $this->persistEnvKey('MODULE_MAKER_FRONTEND', $elegido, 'frontend');

        $descripcion = $elegido === 'innodite'
            ? 'con los componentes de la biblioteca de la casa'
            : 'autónomas, sin depender de ninguna biblioteca';

        $this->components->twoColumnDetail('Vistas generadas', "<fg=green>{$elegido}</> — {$descripcion}");

        if ($elegido === 'innodite') {
            $this->line('  <fg=gray>El andamiaje del frontend lo instala la biblioteca, no este paquete.</>');
        }

        $this->newLine();
    }

    /** El valor pedido por opción, o preguntado — con `default` como respuesta segura. */
    private function resolveFrontend(): string
    {
        $opcion = $this->option('frontend');

        if (is_string($opcion) && $opcion !== '') {
            if (in_array($opcion, ['default', 'innodite'], true)) {
                return $opcion;
            }

            $this->fallo(
                "«{$opcion}» no es un frontend soportado.",
                'usa <comment>default</comment> o <comment>innodite</comment>.',
                'Se continúa con «default», que no depende de ninguna biblioteca.'
            );

            return 'default';
        }

        // A diferencia del modo, aquí SÍ hay respuesta por defecto y no se detiene la instalación:
        // elegir mal el frontend produce vistas que hay que reescribir, no una estructura equivocada
        // multiplicada por cada módulo. Y `default` funciona en cualquier proyecto.
        if (! $this->input->isInteractive()) {
            return 'default';
        }

        $respuesta = $this->choice(
            '  ¿Con qué componentes se generan las vistas?',
            ['default', 'innodite'],
            'default',
            null,
            false
        );

        return $respuesta === 'innodite' ? 'innodite' : 'default';
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

            // Si el valor llegó por bandera, el usuario ya decidió: preguntar otra vez solo tiene
            // sentido cuando hay alguien delante. Sin terminal —CI, scripts, --no-interaction— la
            // respuesta silenciosa era «no», así que el modo pedido con --mode se ignoraba y el
            // comando terminaba anunciando «Configuración completa» de todas formas.
            $porBandera = $this->option('mode') !== null || $this->option('tenancy') !== null;

            if (! $porBandera && ! $this->confirm("  El .env dice '{$valorActual}'. ¿Cambiarlo a '{$valor}'?", false)) {
                $this->warn("  El {$queEs} se queda como estaba.");

                return;
            }

            if (
                $porBandera && $this->input->isInteractive()
                && ! $this->confirm("  El .env dice '{$valorActual}'. ¿Cambiarlo a '{$valor}'?", true)
            ) {
                $this->warn("  El {$queEs} se queda como estaba.");

                return;
            }

            Disk::put($envPath, preg_replace("/^{$clave}=.*$/m", $linea, $contenido));
            $this->hecho("  ✅ .env actualizado: {$linea}");

            return;
        }

        Disk::put($envPath, rtrim($contenido, "\n") . "\n\n{$linea}\n");
        $this->hecho("  ✅ Escrito en .env: {$linea}");
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
    /**
     * Publica `config/make-module.php` en el proyecto, si todavía no está.
     *
     * **Lo publica el instalador y no un `vendor:publish` que nadie ejecuta.** El modo y el paquete
     * de tenencia viven en el `.env`, así que el paquete arranca sin este archivo — pero el **orden
     * de despliegue** es un array y no cabe en una variable de entorno. Sin él, el propio despliegue
     * manda «añádelas a `deploy` en config/make-module.php» y ese archivo no existe en ninguna
     * parte: hay que ir a buscarlo a `vendor/` y copiarlo a mano, sabiendo que está ahí.
     *
     * ⛔ No sobreescribe. Dentro está el orden de despliegue del proyecto, que crece con cada
     * subfuncionalidad: reinstalar no puede llevárselo.
     */
    protected function publishPackageConfig(): void
    {
        $destino = config_path('make-module.php');

        if (File::exists($destino)) {
            $this->warn('   config/make-module.php ya existe. No se sobreescribió.');

            return;
        }

        Disk::copy(dirname(__DIR__, 2) . '/config/make-module.php', $destino);
        $this->hecho('✅ Configuración publicada en: config/make-module.php');
        $this->line('   Ahí declaras el <comment>orden de despliegue</comment> de las subfuncionalidades.');
    }

    protected function publishStubs(string $configPath): void
    {
        $packageStubsPath = dirname(__DIR__, 2) . '/stubs/contextual';

        // La carpeta destino es la que LEE el resolutor de stubs (nivel 2), no una derivada de
        // $configPath: si el proyecto reconfigura `stubs.path`, publicar en otro sitio deja al
        // usuario editando archivos que nadie lee.
        $destPath = config('make-module.stubs.path') . '/contextual';

        if (!File::isDirectory($packageStubsPath)) {
            $this->warn("   No se encontró stubs/contextual/ en el paquete. Creando carpeta vacía...");
            Disk::makeDirectory($destPath, 0755, true, true);
            return;
        }

        if (File::isDirectory($destPath)) {
            $this->warn("   stubs/contextual/ ya existe. No se sobreescribió. Edítalo manualmente si necesitas cambios.");
            return;
        }

        Disk::copyDirectory($packageStubsPath, $destPath);
        $this->hecho("✅ Stubs publicados en: module-maker-config/stubs/contextual/");
    }

    /**
     * Publica el archivo contexts.json en module-maker-config/.
     *
     * @param  string  $configPath  Ruta a module-maker-config/ en el project root
     * @return void
     */
    protected function publishContextsJson(string $configPath, ?ModuleMode $mode = null): void
    {
        // ⭐ La plantilla depende del MODO, y no hacerlo así rompía toda pantalla de un proyecto de
        // una sola aplicación: el archivo llegaba con `central`, `shared` y dos inquilinos, y el
        // renderizador de vistas —que construye su mapa de prefijos desde aquí— exigía que cada
        // componente empezara por uno de ellos. En single-app ese prefijo no existe.
        $plantilla = $mode !== null && ! $mode->hasContextAxis()
            ? 'contexts-single-app.json'
            : 'contexts.json';

        $source      = dirname(__DIR__, 2) . '/stubs/' . $plantilla;
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

        Disk::copy($source, $destination);
        $this->hecho("✅ contexts.json publicado en: module-maker-config/contexts.json");
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

        Disk::put($seederPath, str_replace($anclaje, $reemplazo, $seederContent));

        $this->hecho("✅ DatabaseSeeder.php llama ahora a {$principal}.");
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
            Disk::makeDirectory($path, 0755, true);
            $this->hecho("✅ Carpeta creada: {$label}");
        }
    }

    /**
     * Un hecho consumado, o lo que sería si esto no fuese un ensayo.
     *
     * El instalador imprimía «✅ Escrito en .env», «✅ Seeder creado» y «Configuración completa»
     * también con `--dry-run` puesto, y solo al final aclaraba que no había tocado nada. Un resumen
     * honesto detrás de doce líneas que afirman lo contrario no repara nada: quien lee las primeras
     * ya se lo creyó. Es la misma familia de defecto que esta fase estuvo cerrando — una pieza
     * afirmando algo que no es cierto.
     */
    private function hecho(string $mensaje): void
    {
        if ((bool) $this->option('dry-run')) {
            $this->line("  <fg=gray>· (ensayo) {$mensaje}</>");

            return;
        }

        $this->info($mensaje);
    }
}
