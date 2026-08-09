<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Innodite\LaravelModuleMaker\Services\ModuleAuditor;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\PackageVersion;
use Throwable;

/**
 * One diagnostic: is this project ready?
 *
 *     php artisan innodite:doctor
 *     php artisan innodite:doctor --continuar
 *
 * **Why the two old commands became one.** `innodite:module-check` inspected the generator's own
 * environment — contexts.json, write permissions, name collisions, the event log. `innodite:check-env`
 * inspected the host project's contract — `HasRoles` on User, `auth.permissions` shared through Inertia,
 * the bridge middleware registered. Both were useful and neither name said what it did: module-check
 * looks at no module, and check-env looks at no generator environment. Renaming one of them would have
 * left it a single hyphen away from the other.
 *
 * The developer was never choosing between two diagnostics anyway. They want one answer — *can I
 * generate, and will what I generate work?* — so there is one command that answers it.
 *
 * **Why in stages, and why the first one cuts.** The order is dependency, not taste: the generator has
 * to be installed and able to write before there is anything generated for the host project to serve.
 * A project with no contexts.json has not run `innodite:module-setup`, and asking it whether it shares
 * `auth.context` describes a problem it does not have yet. So stage two does not run, and the command
 * says which checks it skipped instead of letting silence read as a pass. `--continuar` runs both.
 *
 * Inside a stage there is no cut: its checks are siblings, not links in a chain. Write permissions do
 * not depend on contexts.json being valid, and hiding them would cost a second round trip to learn
 * something the first run already knew.
 *
 * **Why stage two matters most.** It is the check that would have caught B24 — the phase-3 defect where
 * the generated screen loaded perfectly and without a single button, because the project never shared
 * the permissions the buttons are drawn from. Everything was correct on both sides of a contract nobody
 * verified.
 */
class DoctorCommand extends Command
{
    use ReportsFailures;

    protected $signature = 'innodite:doctor
        {--continuar : Comprueba el contrato del proyecto aunque el entorno del generador falle}';

    protected $description = 'Diagnostica el entorno del generador y el contrato del proyecto, en cascada.';

    /** What stage two would have checked, named so the cut does not read as a pass. */
    private const CONTRATO_DEL_PROYECTO = [
        'El modelo User resuelve permisos (Spatie HasRoles o InnoditeUserPermissions)',
        'HandleInertiaRequests comparte auth.permissions y auth.context',
        'InnoditeContextBridge está registrado en el grupo web',
    ];

    /** Resolved once: every check that depends on the mode reads it from here. */
    private ?ModuleMode $modo = null;

    public function handle(): int
    {
        $this->cabecera();

        $entornoOk = $this->entornoDelGenerador();

        if (! $entornoOk && ! $this->option('continuar')) {
            $this->cortar();

            return self::FAILURE;
        }

        $contratoOk = $this->contratoDelProyecto();

        return $this->cerrar($entornoOk && $contratoOk);
    }

    // ─── La cabecera ──────────────────────────────────────────────────────────

    /**
     * The header states the version it is actually running.
     *
     * It used to say `v3.0.0`, typed by hand in three places, while the package shipped 3.6. The first
     * line of a diagnostic is what gets copied into the bug report, so a stale literal there sends
     * everybody to the wrong tag. Now it asks Composer — see {@see PackageVersion}.
     */
    private function cabecera(): void
    {
        $this->newLine();
        $this->line(
            '  <fg=blue;options=bold>Innodite ModuleMaker — Diagnóstico del proyecto</>'
            . '  <fg=gray>v' . PackageVersion::current() . '</>'
        );
        $this->newLine();
    }

    // ─── Etapa 1 · El entorno del generador ───────────────────────────────────

    private function entornoDelGenerador(): bool
    {
        $this->line('  <fg=blue;options=bold>Etapa 1 · El entorno del generador</>');
        $this->newLine();

        $ok = $this->comprobarModo();
        $this->newLine();
        $ok = $this->comprobarContextsJson() && $ok;
        $this->newLine();
        $ok = $this->comprobarPermisosDeEscritura() && $ok;
        $this->newLine();
        $ok = $this->comprobarColisiones() && $ok;
        $this->newLine();
        $this->mostrarLogDeEventos();
        $this->newLine();

        return $ok;
    }

    /**
     * The mode, which everything else is measured against.
     *
     * It is checked first and separately because the old command did neither: it called
     * `ModuleMode::current()` in the middle of reading contexts.json, and that throws. A project with
     * no mode configured — precisely the project that needs a diagnostic — got an uncaught exception
     * instead of a diagnosis.
     */
    private function comprobarModo(): bool
    {
        $this->line('  <fg=cyan;options=bold>1. El modo del proyecto</>');

        try {
            $this->modo = ModuleMode::current();
        } catch (Throwable $e) {
            // The exception already carries FALLA/FIX and the three valid values.
            $this->components->error($e->getMessage());

            return false;
        }

        $this->components->twoColumnDetail('mode', "<fg=green>OK — {$this->modo->label()}</>");

        return true;
    }

    private function comprobarContextsJson(): bool
    {
        $this->line('  <fg=cyan;options=bold>2. El catálogo de contextos</>');

        $path = (string) config('make-module.contexts_path');

        if (! File::exists($path)) {
            $this->fallo(
                "no está contexts.json en {$path}.",
                'publícalo con php artisan innodite:module-setup, o apunta contexts_path a donde lo '
                . 'tengas.',
                'Es el catálogo del que sale cada contexto: sin él no se puede generar nada.'
            );

            return false;
        }

        $data = json_decode(File::get($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fallo(
                'contexts.json no se puede leer: ' . json_last_error_msg() . '.',
                'corrige el archivo — una coma de más al final de una lista es la causa habitual.',
                'Mientras no se entienda, ningún comando puede resolver un contexto.'
            );

            return false;
        }

        if (! isset($data['contexts']) || ! is_array($data['contexts'])) {
            $this->fallo(
                'contexts.json no tiene la clave raíz "contexts".',
                'envuelve los contextos en {"contexts": { … }} — compáralo con el que publica '
                . 'innodite:module-setup.',
                'Sin esa clave el archivo es válido como JSON y vacío como catálogo.'
            );

            return false;
        }

        $ok = $this->comprobarContextosDelModo($data['contexts']);
        $ok = $this->comprobarFormaDeCadaContexto($data['contexts']) && $ok;

        if ($ok) {
            $this->components->twoColumnDetail(
                basename($path),
                '<fg=green>OK — ' . $this->contarSubContextos($data['contexts']) . ' sub-contextos</>'
            );
        }

        return $ok;
    }

    /**
     * Which context keys are required is decided by the mode, not by a fixed list.
     *
     * A single application has no tenants to declare, and demanding them forces it to invent a fake
     * context to pass a diagnostic that does not apply to it.
     *
     * @param  array<string, mixed>  $contexts
     */
    private function comprobarContextosDelModo(array $contexts): bool
    {
        if ($this->modo === null) {
            $this->components->warn(
                'Los contextos exigidos dependen del modo, y el modo no está elegido: esa parte queda '
                . 'sin comprobar.'
            );

            return true;
        }

        $faltan = array_diff($this->modo->requiredContextKeys(), array_keys($contexts));

        if ($faltan === []) {
            return true;
        }

        $this->fallo(
            "contexts.json no declara los contextos que el modo '{$this->modo->value}' exige: "
            . implode(', ', $faltan) . '.',
            'añádelos al catálogo, o cambia el modo en config/make-module.php si el que corre no es '
            . 'el tuyo.',
            "Modo actual: {$this->modo->label()}."
        );

        return false;
    }

    /**
     * Every sub-context declares the four keys the resolver cannot work without.
     *
     * `route_file` is deliberately NOT one of them, and the old command demanded it — which made it
     * reject the very contexts.json that `innodite:module-setup` publishes, because `shared` does not
     * declare one. That absence is not an omission: it is how a context says it lives in *both* route
     * files, and {@see \Innodite\LaravelModuleMaker\Generators\Components\RouteGenerator} reads it that
     * way. A diagnostic that fails the package's own default installation teaches people to ignore the
     * diagnostic.
     *
     * The shape is hybrid on purpose: `central`, `shared` and `tenant_shared` are single objects, while
     * `tenant` is an indexed list of them. Iterating without normalising was defect ERROR-001 — a
     * TypeError on every run.
     *
     * @param  array<string, mixed>  $contexts
     */
    private function comprobarFormaDeCadaContexto(array $contexts): bool
    {
        $claves  = ['id', 'class_prefix', 'folder', 'namespace_path'];
        $errores = [];

        foreach ($contexts as $clave => $items) {
            if (! is_array($items)) {
                $errores[] = "contexts.{$clave} debe ser un array de sub-contextos";

                continue;
            }

            foreach (isset($items['id']) ? [$items] : $items as $i => $item) {
                if (! is_array($item)) {
                    $errores[] = "contexts.{$clave}[{$i}] debe ser un array";

                    continue;
                }

                foreach (array_diff($claves, array_keys($item)) as $falta) {
                    $errores[] = "contexts.{$clave}[{$i}] no tiene la clave '{$falta}'";
                }
            }
        }

        if ($errores === []) {
            return true;
        }

        $this->fallo(
            'el catálogo tiene ' . count($errores) . " entrada(s) incompletas:\n    "
            . implode("\n    ", $errores),
            'completa esas claves — cada sub-contexto necesita las cuatro: '
            . implode(', ', $claves) . '.',
            'El resolutor las lee para construir el namespace y la carpeta; sin una de ellas genera '
            . 'en un sitio que no existe.'
        );

        return false;
    }

    /** @param array<string, mixed> $contexts */
    private function contarSubContextos(array $contexts): int
    {
        $total = 0;

        foreach ($contexts as $items) {
            if (is_array($items)) {
                $total += isset($items['id']) ? 1 : count($items);
            }
        }

        return $total;
    }

    private function comprobarPermisosDeEscritura(): bool
    {
        $this->line('  <fg=cyan;options=bold>3. Permisos de escritura</>');

        $rutas = [
            'Modules/'          => $this->rutaDeModulos(),
            'routes/web.php'    => base_path('routes/web.php'),
            'routes/tenant.php' => base_path('routes/tenant.php'),
            'routes/api.php'    => base_path('routes/api.php'),
            'storage/logs/'     => storage_path('logs'),
        ];

        $ok = true;

        foreach ($rutas as $etiqueta => $ruta) {
            if (! File::exists($ruta)) {
                $this->components->twoColumnDetail(
                    $etiqueta,
                    '<fg=yellow>No existe — se creará al generar el primer módulo</>'
                );

                continue;
            }

            if (is_writable($ruta)) {
                $this->components->twoColumnDetail($etiqueta, '<fg=green>OK — escribible</>');

                continue;
            }

            $this->components->twoColumnDetail($etiqueta, '<fg=red>Sin permiso de escritura</>');
            $this->fallo(
                "no se puede escribir en {$ruta}.",
                "dale permiso al usuario que corre artisan: chmod u+w {$ruta}",
                'El generador escribe ahí. Sin permiso se detiene a mitad, con parte del módulo ya '
                . 'creada.'
            );

            $ok = false;
        }

        return $ok;
    }

    private function comprobarColisiones(): bool
    {
        $this->line('  <fg=cyan;options=bold>4. Colisiones de nombres</>');

        $modulos = $this->rutaDeModulos();

        if (! File::isDirectory($modulos)) {
            $this->components->twoColumnDetail('Modules/', '<fg=yellow>No existe — sin módulos aún</>');

            return true;
        }

        $directorios = File::directories($modulos);
        $colisiones  = [];
        $vistos      = [];

        foreach ($directorios as $directorio) {
            $nombre = basename($directorio);

            if (in_array($nombre, $vistos, true)) {
                $colisiones[] = "{$nombre} (directorio duplicado)";
            }

            $vistos[] = $nombre;

            $proveedor = "{$directorio}/Providers/{$nombre}ServiceProvider.php";

            if (File::exists($proveedor) && ! str_contains(File::get($proveedor), "namespace Modules\\{$nombre}\\Providers")) {
                $colisiones[] = "{$nombre} (namespace incorrecto en su ServiceProvider)";
            }

            $colisiones = array_merge($colisiones, $this->migracionesDuplicadas($directorio, $nombre));
        }

        if ($colisiones !== []) {
            $this->fallo(
                "hay " . count($colisiones) . " colisión(es):\n    " . implode("\n    ", $colisiones),
                'renombra uno de los dos — el módulo en contexts.json, o el archivo de migración.',
                'Dos piezas que resuelven al mismo sitio se pisan lo generado, y gana la última que '
                . 'corra.'
            );

            return false;
        }

        $this->components->twoColumnDetail(
            'Módulos',
            '<fg=green>OK — ' . count($directorios) . ' módulo(s), sin colisiones</>'
        );

        return true;
    }

    /**
     * Two migrations of the same table inside one module.
     *
     * The timestamp prefix is stripped before comparing, and it comes in two lengths: the standard six
     * digits and the microsecond variant the generator writes when it creates several in one second.
     *
     * @return array<int, string>
     */
    private function migracionesDuplicadas(string $directorio, string $modulo): array
    {
        $carpeta = "{$directorio}/Database/Migrations";

        if (! File::isDirectory($carpeta)) {
            return [];
        }

        $encontradas = [];
        $duplicadas  = [];

        foreach (File::allFiles($carpeta) as $archivo) {
            $nombre = preg_replace(
                '/^\d{4}_\d{2}_\d{2}_\d+_/',
                '',
                $archivo->getFilenameWithoutExtension()
            );

            if (! $nombre) {
                continue;
            }

            if (in_array($nombre, $encontradas, true)) {
                $duplicadas[] = "{$modulo} → migración duplicada: {$nombre}";
            }

            $encontradas[] = $nombre;
        }

        return $duplicadas;
    }

    /**
     * The event log — information, not a verdict.
     *
     * It answers "what did the generator do here, and when", which is the first thing worth knowing
     * when a module looks half-written. Nothing about it can fail a diagnostic: a project that has
     * never generated anything has an empty log and a perfectly healthy environment.
     */
    private function mostrarLogDeEventos(): void
    {
        $this->line('  <fg=cyan;options=bold>5. Log de eventos</>');

        $entradas = ModuleAuditor::readLog();

        if ($entradas === []) {
            $this->components->twoColumnDetail(
                'module_maker.log',
                '<fg=yellow>Sin entradas — no se ha generado ningún módulo todavía</>'
            );

            return;
        }

        $this->components->twoColumnDetail('module_maker.log', '<fg=green>' . ModuleAuditor::logPath() . '</>');
        $this->newLine();
        $this->line('  <fg=gray>Últimas 5 operaciones:</>');

        $this->table(
            ['Fecha', 'Evento', 'Módulo / Contexto'],
            array_map(fn ($e) => [
                $e['timestamp'] ?? '—',
                $e['event'] ?? '—',
                $e['module'] ?? $e['context_key'] ?? '—',
            ], array_slice($entradas, -5))
        );
    }

    // ─── Etapa 2 · El contrato del proyecto anfitrión ─────────────────────────

    private function contratoDelProyecto(): bool
    {
        $this->line('  <fg=blue;options=bold>Etapa 2 · El contrato del proyecto anfitrión</>');
        $this->newLine();

        $ok = $this->comprobarModeloUser();
        $this->newLine();
        $ok = $this->comprobarShareDeInertia() && $ok;
        $this->newLine();
        $ok = $this->comprobarBridgeRegistrado() && $ok;
        $this->newLine();

        return $ok;
    }

    private function comprobarModeloUser(): bool
    {
        $this->line('  <fg=cyan;options=bold>1. El modelo User resuelve permisos</>');

        $archivo = $this->buscarModeloUser();

        if ($archivo === null) {
            $this->fallo(
                'no está el modelo User: ni en app/Models/User.php, ni en app/User.php, ni en '
                . 'Modules/*/Models/.',
                'crea el modelo User de Laravel, o corre el diagnóstico desde la raíz del proyecto '
                . 'correcto.',
                'Sin User no hay a quién asignarle roles, y los permisos que genera el paquete no '
                . 'protegen nada.'
            );

            return false;
        }

        $contenido = File::get($archivo);

        if (str_contains($contenido, 'HasRoles') || str_contains($contenido, 'Spatie\\Permission')) {
            $this->components->twoColumnDetail('Spatie\\Permission — HasRoles', '<fg=green>OK — detectado</>');

            return true;
        }

        if (
            str_contains($contenido, 'InnoditeUserPermissions')
            && str_contains($contenido, 'getInnoditePermissions')
        ) {
            $this->components->twoColumnDetail('InnoditeUserPermissions', '<fg=green>OK — implementado</>');

            return true;
        }

        $this->fallo(
            'el modelo User no resuelve permisos de ninguna de las dos formas que el paquete admite.',
            'instala spatie/laravel-permission y añádele el trait HasRoles — el bloque exacto va abajo.',
            'Cada ruta generada exige un permiso por nombre: sin quien los resuelva, la pantalla '
            . 'responde 403 a todo el mundo.'
        );

        $this->newLine();
        $this->line('  <fg=yellow>Opción A — Spatie Permission (recomendada):</>');
        $this->bloqueDeCodigo(
            "composer require spatie/laravel-permission\n"
            . "php artisan vendor:publish --provider=\"Spatie\\Permission\\PermissionServiceProvider\""
        );
        $this->line('  Y el trait en el modelo:');
        $this->bloqueDeCodigo(
            "use Spatie\\Permission\\Traits\\HasRoles;\n\n"
            . "class User extends Authenticatable\n"
            . "{\n"
            . "    use HasRoles;\n"
            . "}"
        );

        $this->newLine();
        $this->line('  <fg=yellow>Opción B — InnoditeUserPermissions (sin dependencias):</>');
        $this->bloqueDeCodigo(
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

    /**
     * The half of the contract that B24 broke.
     *
     * `auth.permissions` is what the generated screen reads to decide which buttons exist. Without it
     * the page renders, the routes answer, the tests pass — and there is not a single button on it.
     */
    private function comprobarShareDeInertia(): bool
    {
        $this->line('  <fg=cyan;options=bold>2. HandleInertiaRequests comparte lo que la vista lee</>');

        $middleware = app_path('Http/Middleware/HandleInertiaRequests.php');

        if (! File::exists($middleware)) {
            $this->fallo(
                'no está app/Http/Middleware/HandleInertiaRequests.php.',
                'instálalo: composer require inertiajs/inertia-laravel && php artisan inertia:middleware',
                'Es por donde el backend le pasa a la vista quién es el usuario y qué puede hacer.'
            );

            return false;
        }

        $contenido = File::get($middleware);
        $ok        = true;

        if (str_contains($contenido, 'auth.permissions') || str_contains($contenido, "'permissions'")) {
            $this->components->twoColumnDetail('auth.permissions', '<fg=green>OK — se comparte</>');
        } else {
            $this->fallo(
                'auth.permissions no se comparte con el frontend.',
                'añádelo al share() de HandleInertiaRequests — el bloque exacto va abajo.',
                'Es el defecto B24: la pantalla generada carga perfecta y SIN UN SOLO BOTÓN, porque '
                . 'no sabe qué puede hacer el usuario.'
            );

            $this->newLine();
            $this->bloqueDeCodigo(
                "return array_merge(parent::share(\$request), [\n"
                . "    'auth' => [\n"
                . "        'user'        => \$request->user(),\n"
                . "        'permissions' => \$request->user()\n"
                . "            ? \$request->user()->getAllPermissions()->pluck('name')\n"
                . "            : [],\n"
                . "    ],\n"
                . "]);"
            );

            $ok = false;
        }

        if (str_contains($contenido, 'auth.context') || str_contains($contenido, 'InnoditeContextBridge')) {
            $this->components->twoColumnDetail('auth.context', '<fg=green>OK — detectado</>');
        } else {
            $this->components->twoColumnDetail(
                'auth.context',
                '<fg=cyan>Lo inyecta InnoditeContextBridge — se comprueba abajo</>'
            );
        }

        return $ok;
    }

    /**
     * The bridge, whose severity is the mode's to decide.
     *
     * In a multi-tenant project the generated screens read `auth.context` to know which tenant they are
     * serving, and the bridge is what puts it there: missing it is a failure. In a single application
     * there is no context axis at all (D9 forbids `--context` there), so the bridge has nothing to
     * inject and demanding it would be inventing a requirement the mode already ruled out.
     */
    private function comprobarBridgeRegistrado(): bool
    {
        $this->line('  <fg=cyan;options=bold>3. InnoditeContextBridge registrado</>');

        $archivos = array_filter(
            [base_path('bootstrap/app.php'), app_path('Http/Kernel.php')],
            fn ($archivo) => File::exists($archivo)
        );

        foreach ($archivos as $archivo) {
            if (str_contains(File::get($archivo), 'InnoditeContextBridge')) {
                $this->components->twoColumnDetail('InnoditeContextBridge', '<fg=green>OK — registrado</>');

                return true;
            }
        }

        $exigido = $this->modo !== null && $this->modo->hasContextAxis();

        if (! $exigido) {
            $this->components->twoColumnDetail(
                'InnoditeContextBridge',
                '<fg=cyan>No registrado — sin eje de contexto, no hace falta</>'
            );

            return true;
        }

        $this->fallo(
            'InnoditeContextBridge no está registrado en el grupo web.',
            'regístralo — el bloque exacto va abajo.',
            'Es quien pone auth.context: sin él la vista generada no sabe a qué tenant sirve.'
        );

        $this->newLine();

        if (File::exists(base_path('bootstrap/app.php'))) {
            $this->line('  <fg=yellow>En bootstrap/app.php (Laravel 11+):</>');
            $this->bloqueDeCodigo(
                "->withMiddleware(function (Middleware \$middleware) {\n"
                . "    \$middleware->appendToGroup('web', [\n"
                . "        \\Innodite\\LaravelModuleMaker\\Middleware\\InnoditeContextBridge::class,\n"
                . "    ]);\n"
                . "})"
            );
        } else {
            $this->line('  <fg=yellow>En app/Http/Kernel.php, en el grupo web:</>');
            $this->bloqueDeCodigo(
                "'web' => [\n"
                . "    // ...\n"
                . "    \\Innodite\\LaravelModuleMaker\\Middleware\\InnoditeContextBridge::class,\n"
                . "],"
            );
        }

        return false;
    }

    // ─── El corte y el cierre ─────────────────────────────────────────────────

    /**
     * Says what was not checked, which is the useful half of cutting.
     *
     * Cutting in silence reads as "the rest passed". What is being stated is the opposite: the rest is
     * unknown, and finding out before fixing this would not help.
     */
    private function cortar(): void
    {
        $this->components->error('Se corta aquí: el entorno del generador tiene fallos.');

        $this->line('  <fg=yellow>Sin comprobar (' . count(self::CONTRATO_DEL_PROYECTO) . '), '
            . 'porque dependen de lo anterior:</>');

        foreach (self::CONTRATO_DEL_PROYECTO as $comprobacion) {
            $this->line("    · {$comprobacion}");
        }

        $this->newLine();
        $this->line('  <fg=gray>Arregla lo de arriba y vuelve a lanzarlo. Si quieres verlo todo de una');
        $this->line('  vez: php artisan innodite:doctor --continuar</>');
        $this->newLine();
    }

    private function cerrar(bool $todoPaso): int
    {
        if ($todoPaso) {
            $this->components->info('El proyecto está listo: el generador puede escribir y lo generado '
                . 'encuentra su contrato.');

            return self::SUCCESS;
        }

        $this->components->warn('Quedan fallos. Cada uno de arriba dice qué hacer en su línea FIX.');

        return self::FAILURE;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Where the generator actually writes — configuration first, Laravel's default after.
     *
     * The old command read `base_path('Modules')` while the generator wrote to
     * `make-module.module_path`. In a normal project both are the same path and nothing shows; in one
     * that moved its modules, the diagnostic reported on an empty folder and passed.
     */
    private function rutaDeModulos(): string
    {
        $configurada = config('make-module.module_path');

        return is_string($configurada) && $configurada !== '' ? $configurada : base_path('Modules');
    }

    /** The User model, in Laravel's standard places and in modularised projects. */
    private function buscarModeloUser(): ?string
    {
        foreach ([app_path('Models/User.php'), app_path('User.php')] as $ruta) {
            if (File::exists($ruta)) {
                return $ruta;
            }
        }

        $encontrados = array_merge(
            glob(base_path('Modules/*/Models/*User*.php')) ?: [],
            glob(base_path('Modules/*/Models/**/*User*.php')) ?: []
        );

        return $encontrados !== [] ? $encontrados[0] : null;
    }

    /** A code block, indented, so the fix is copy-pasteable instead of narrated. */
    private function bloqueDeCodigo(string $codigo): void
    {
        $this->newLine();

        foreach (explode("\n", $codigo) as $linea) {
            $this->line("  <fg=white;bg=default>  {$linea}</>");
        }

        $this->newLine();
    }
}
