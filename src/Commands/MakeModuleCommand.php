<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\PrintsHeader;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Innodite\LaravelModuleMaker\Commands\Concerns\RehearsesChanges;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\ModuleGenerator;
use Innodite\LaravelModuleMaker\Services\EventLog;
use Innodite\LaravelModuleMaker\Support\ContextOption;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Innodite\LaravelModuleMaker\Support\Frontend;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\StubsDeVendor;
use Innodite\LaravelModuleMaker\Support\Ziggy;
use Throwable;

/**
 * MakeModuleCommand — Orquestador Maestro del Generador de Módulos v3.0.0
 *
 * Modos de uso:
 *   1. Módulo completo con contexto explícito:
 *        php artisan innodite:make-module User --context=central
 *
 *   2. Módulo completo con selección interactiva (sin --context):
 *        php artisan innodite:make-module User
 *
 *   3. Componentes individuales en módulo existente:
 *        php artisan innodite:make-module User --context=shared -S -R
 *
 *   4. Desde JSON de configuración dinámica:
 *        php artisan innodite:make-module User --json
 *
 * Las rutas se escriben **solo** dentro del módulo, en Modules/{Module}/Routes/. El ServiceProvider
 * del paquete las carga solo, así que el generador no toca ningún archivo del proyecto.
 */
class MakeModuleCommand extends Command
{
    use RehearsesChanges;
    use PrintsHeader;
    use ReportsFailures;

    protected $signature = 'innodite:make-module
        {name                  : Nombre de la entidad en singular (se convierte a PascalCase)}
        {--context=            : Contexto donde se genera, en multitenant: central | shared | tenant_shared | id del tenant}
        {--json                : Usa module-maker-config/{module}.json como fuente de configuración}
        {--M|model             : Solo añade el modelo}
        {--C|controller        : Solo añade el controlador}
        {--S|service           : Solo añade el servicio e interface}
        {--R|repository        : Solo añade el repositorio e interface}
        {--G|migration         : Solo añade la migración contextualizada}
        {--Q|request           : Solo añade el form request}
        {--dry-run             : Ensayo: enseña lo que haría, sin escribir nada}';

    protected $description = 'Genera un módulo completo con sus rutas contextualizadas.';

    // ─── Entry point ──────────────────────────────────────────────────────────

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
        // ── Pre-flight: validar nombre ────────────────────────────────────────
        try {
            $moduleName = $this->resolveModuleName($this->argument('name'));
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            return Command::FAILURE;
        }

        $modulePath = config('make-module.module_path') . "/{$moduleName}";

        $this->cabecera("Módulo {$moduleName}");

        // ── Modo JSON ─────────────────────────────────────────────────────────
        if ($this->option('json')) {
            return $this->handleJsonMode($moduleName);
        }

        // ── Modo componentes individuales ─────────────────────────────────────
        if ($this->hasIndividualFlags()) {
            return $this->handleComponentMode($moduleName, $modulePath);
        }

        // ── Módulo ya existe (modo completo) ──────────────────────────────────
        if (File::exists($modulePath)) {
            $this->fallo(
                "el módulo '{$moduleName}' ya existe en {$modulePath}.",
                "añádele lo que falte con innodite:add-entity {$moduleName} <Entidad>, o pide una "
                . 'capa suelta con -M -C -S -R -G -Q.',
                'Regenerarlo encima sobrescribiría lo que ya tiene escrito el proyecto.'
            );
            return Command::FAILURE;
        }

        // ── Modo completo ─────────────────────────────────────────────────────
        return $this->handleFullModule($moduleName, $modulePath);
    }

    // ─── Modos de ejecución ───────────────────────────────────────────────────

    /**
     * Genera el módulo completo: estructura + inyección de rutas.
     */
    private function handleFullModule(string $moduleName, string $modulePath): int
    {
        // Pre-flight: resolver contexto
        try {
            [$contextKey, $contextItem] = $this->resolveContext();
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            return Command::FAILURE;
        }

        $contextId     = $contextItem['id'] ?? '';
        $functionality = $this->resolveFunctionality($moduleName);

        $this->newLine();
        $this->components->info("Generando módulo <comment>{$moduleName}</comment>");
        $this->displayConfigTable($moduleName, $contextKey, $contextId, $functionality);
        $this->newLine();

        $filesGenerated = false;

        try {
            // ── Paso 1: Generar estructura de archivos (Fases 1 & 2) ──────────
            $this->components->task('Creando estructura de archivos', function () use (
                $moduleName,
                $contextKey,
                $functionality,
                $contextId,
                &$filesGenerated
            ) {
                (new ModuleGenerator($moduleName, true, null, $this))
                    ->createCleanModuleWithContext($contextKey, $functionality, $contextId);

                $filesGenerated = true;
                return true;
            });

            // Aquí había un paso 2 que escribía las rutas **otra vez**, en el `routes/web.php` del
            // proyecto. Era la vía de la v3, y declaraba `create` y `edit`: dos pantallas que la v4
            // ya no genera, porque el alta y la edición ocurren en un modal sobre el listado. Las
            // rutas buenas son las del módulo, con sus seis acciones reales y su permiso cada una,
            // y el ServiceProvider del paquete ya las carga solo.

            $this->newLine();
            $this->displaySuccess($moduleName, $contextKey, $contextId);

            // ── Auditoría ─────────────────────────────────────────────────────
            EventLog::log('module.created', [
                'module'        => $moduleName,
                'context_key'   => $contextKey,
                'context_id'    => $contextId,
                'functionality' => $functionality,
            ]);

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $hayRestos = File::exists($modulePath);

            $this->fallo(
                $e->getMessage(),
                $hayRestos
                    ? "corrige lo anterior y vuelve a generar. Lo que quedó a medias está en "
                      . "{$modulePath}: abajo se ofrece borrarlo, y sin terminal interactiva hay "
                      . 'que borrarlo a mano.'
                    : 'corrige lo anterior y vuelve a generar.',
                'La generación se detuvo a medias: lo que quedó en disco no es un módulo completo.'
            );

            // El rollback se ofrece por lo que hay EN DISCO, no por si se llegó a generar algún
            // componente. Cuando el fallo salta en el primer stub, `$filesGenerated` sigue en false
            // y la carpeta del módulo ya existe con su estructura y sus Docs dentro — que es
            // exactamente el caso que dejó dos módulos a medias en un proyecto real.
            if ($hayRestos) {
                if ($this->confirm("¿Deseas eliminar los archivos generados en '{$modulePath}'? (Rollback)")) {
                    $this->performRollback($modulePath);
                    EventLog::log('module.rollback', [
                        'module'      => $moduleName,
                        'context_key' => $contextKey ?? 'unknown',
                        'reason'      => $e->getMessage(),
                    ]);
                } else {
                    $this->components->warn("Los archivos generados se mantienen. Revisa el estado manualmente.");
                }
            }

            return Command::FAILURE;
        }
    }

    /**
     * Añade componentes individuales a un módulo existente.
     */
    private function handleComponentMode(string $moduleName, string $modulePath): int
    {
        // Si el módulo no existe, ofrecer crear la estructura base
        if (!File::exists($modulePath)) {
            $this->components->warn("El módulo '{$moduleName}' no existe.");
            if (!$this->confirm("¿Crear la estructura base antes de continuar?")) {
                return Command::FAILURE;
            }
            (new ModuleGenerator($moduleName, true, null, $this))->createFolders();
        }

        try {
            [$contextKey, $contextItem] = $this->resolveContext();
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            return Command::FAILURE;
        }

        $contextId = $contextItem['id'] ?? '';

        $componentConfig = [
            'context'    => $contextKey,
            'context_id' => $contextId,
        ];

        $flags = [
            'model'      => $this->option('model'),
            'controller' => $this->option('controller'),
            'service'    => $this->option('service'),
            'repository' => $this->option('repository'),
            'migration'  => $this->option('migration'),
            'request'    => $this->option('request'),
        ];

        $this->newLine();
        $this->components->info("Añadiendo componentes a <comment>{$moduleName}</comment> [{$contextKey}]");
        $this->newLine();

        try {
            $this->components->task('Generando componentes', function () use (
                $moduleName,
                $flags,
                $componentConfig
            ) {
                (new ModuleGenerator($moduleName, true, null, $this))
                    ->createIndividualComponents($flags, $componentConfig);
                return true;
            });

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Genera el módulo desde un archivo JSON de configuración dinámica.
     */
    private function handleJsonMode(string $moduleName): int
    {
        $configDir     = config('make-module.config_path');
        $jsonPath      = "{$configDir}/" . Str::lower($moduleName) . '.json';
        $jsonPathKebab = "{$configDir}/" . Str::kebab($moduleName) . '.json';

        if (!File::exists($jsonPath) && File::exists($jsonPathKebab)) {
            $jsonPath = $jsonPathKebab;
        }

        if (!File::exists($jsonPath)) {
            $this->fallo(
                "no hay archivo de configuración para '{$moduleName}'.",
                "escríbelo en {$jsonPath}, o genera el módulo sin --json.",
                'El modo --json toma de ahí la forma entera del módulo.'
            );
            return Command::FAILURE;
        }

        $config = json_decode(File::get($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fallo(
                "el JSON de '{$jsonPath}' no se puede leer: " . json_last_error_msg() . '.',
                'corrige el archivo y vuelve a lanzarlo.',
                'No se genera nada a medias a partir de una configuración que no se entiende.'
            );
            return Command::FAILURE;
        }

        $this->components->info("Usando configuración: <comment>{$jsonPath}</comment>");

        $resolvedName = Str::studly($config['module_name'] ?? $moduleName);

        try {
            $this->components->task('Generando módulo dinámico', function () use ($resolvedName, $config) {
                (new ModuleGenerator($resolvedName, false, $config, $this))->createDynamicModule();
                return true;
            });

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    // ─── Pre-flight: Validaciones ─────────────────────────────────────────────

    /**
     * Convierte el input a PascalCase y valida que sea un identificador PHP válido.
     *
     * Sub-proceso atómico:
     *   input "user-profile" → Str::studly() → "UserProfile"
     *   Regex: /^[A-Z][a-zA-Z0-9]+$/
     *
     * @throws \InvalidArgumentException Si el nombre no es válido
     */
    // ─── Palabras reservadas que no pueden usarse como nombre de módulo ──────────

    private const RESERVED_NAMES = [
        // PHP keywords
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do',
        'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
        'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final',
        'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if',
        'implements', 'include', 'include_once', 'instanceof', 'insteadof',
        'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'print',
        'private', 'protected', 'public', 'readonly', 'require', 'require_once',
        'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use',
        'var', 'while', 'xor', 'yield',
        // PHP built-in class names
        'exception', 'error', 'closure', 'generator', 'iterator', 'arrayaccess',
        'countable', 'stringable', 'throwable',
        // Laravel names que generarían conflictos de namespace
        'app', 'config', 'route', 'request', 'response', 'model', 'controller',
        'middleware', 'provider', 'facade', 'auth', 'event', 'job', 'mail',
        'notification', 'policy', 'rule', 'seeder', 'factory', 'migration',
    ];

    private function resolveModuleName(string $input): string
    {
        $name = Str::studly($input);

        if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $name)) {
            throw new \InvalidArgumentException(self::mensajeDeFallo(
                "'{$name}' no es un nombre de módulo válido.",
                'usa letras y números en PascalCase — User, InvoiceItem.',
                'El nombre acaba siendo clase, carpeta y espacio de nombres: lo que no sea un '
                . 'identificador de PHP no llega a cargarse.'
            ));
        }

        if (in_array(strtolower($name), self::RESERVED_NAMES, true)) {
            throw new \InvalidArgumentException(self::mensajeDeFallo(
                "'{$name}' es una palabra reservada de PHP o Laravel.",
                'usa un nombre del dominio — UserAccount, InvoiceItem.',
                'Una clase con ese nombre no se puede declarar, así que el módulo entero quedaría '
                . 'sin cargar.'
            ));
        }

        return $name;
    }

    /**
     * Resuelve el contexto desde --context o via selección interactiva.
     *
     * Orden de resolución:
     *   1. Si --context=X y X es una clave directa (central, shared, tenant_shared) → usar
     *   2. Si --context=X y X coincide con name/class_prefix de un tenant → usar ese tenant
     *   3. Si --context vacío → selección interactiva
     *   4. Si nada coincide → error con lista de disponibles
     *
     * @return array{0: string, 1: array}  [contextKey, contextItem]
     * @throws \InvalidArgumentException
     */
    private function resolveContext(): array
    {
        $mode        = ModuleMode::current();
        $allContexts = $this->loadContexts();
        $option      = trim($this->option('context') ?? '');

        // ── Lo que el modo exige de --context, antes de resolver nada ──────────
        // Las tres guardas viven en ContextOption porque `add-entity` necesita exactamente las
        // mismas: cada comando llevaba su copia de esta resolución y las guardas se habían añadido
        // solo a uno.
        ContextOption::check($mode, $option, $allContexts, $this->input->isInteractive());

        // En single-app no hay contexto que elegir. Antes se preguntaba siempre, así que una
        // aplicación única se quedaba esperando que eligieran entre central y tenant — o tenía que
        // declarar contextos falsos para pasar el diagnóstico. No es que single-app estuviera
        // «sin implementar»: estaba bloqueado.
        if (! $mode->hasContextAxis()) {
            return ['', []];
        }

        // Sin opción → selección interactiva
        if ($option === '') {
            return $this->askContextInteractive($allContexts);
        }

        // Coincidencia directa con clave de contexto
        if (isset($allContexts[$option])) {
            $item = $allContexts[$option];

            if (!is_array($item)) {
                throw new \InvalidArgumentException("Contexto '{$option}' tiene formato inválido.");
            }

            // Detectar si es array asociativo (contexto único) vs array indexado (lista)
            $isAssociative = array_keys($item) !== range(0, count($item) - 1);

            // Array asociativo → contexto único (central, shared, tenant_shared)
            if ($isAssociative) {
                return [$option, $item];
            }

            // Array indexado → múltiples variantes (ej. tenant)
            if (count($item) === 1) {
                return [$option, $item[0]];
            }

            // Múltiples variantes → preguntar cuál
            return $this->askVariant($option, $item);
        }

        // Buscar en tenants por id, class_prefix o slug
        foreach ($allContexts['tenant'] ?? [] as $item) {
            if ($this->tenantMatches($option, $item)) {
                return ['tenant', $item];
            }
        }

        // No encontrado → error descriptivo
        $available = implode(', ', array_keys($allContexts));
        $tenants   = implode(', ', array_map(
            fn ($t) => $t['id'] ?? 'unknown',
            $allContexts['tenant'] ?? []
        ));

        throw new \InvalidArgumentException(self::mensajeDeFallo(
            "el contexto '{$option}' no está en contexts.json.",
            "usa uno de estos — contextos: {$available} · tenants: {$tenants}",
            'El catálogo es la fuente: si el contexto que quieres no está, decláralo ahí primero.'
        ));
    }

    /**
     * Verifica si un tenant coincide con el input del usuario.
     * Acepta: id exacto, class_prefix, o route_prefix.
     */
    private function tenantMatches(string $option, array $item): bool
    {
        return
            strcasecmp($option, $item['id'] ?? '')           === 0 ||
            strcasecmp($option, $item['class_prefix'] ?? '') === 0 ||
            strcasecmp($option, $item['route_prefix'] ?? '') === 0;
    }

    /**
     * Selección interactiva de contexto cuando no se pasa --context.
     *
     * @return array{0: string, 1: array}
     * @throws \InvalidArgumentException
     */
    private function askContextInteractive(array $allContexts): array
    {
        $keys        = array_keys($allContexts);
        $selectedKey = $this->choice('Selecciona el contexto:', $keys, 0);
        $item        = $allContexts[$selectedKey];

        if (!is_array($item)) {
            throw new \InvalidArgumentException("El contexto '{$selectedKey}' tiene formato inválido.");
        }

        // Detectar si es array asociativo (contexto único) vs array indexado (lista)
        $isAssociative = array_keys($item) !== range(0, count($item) - 1);

        // Array asociativo → contexto único
        if ($isAssociative) {
            return [$selectedKey, $item];
        }

        // Array indexado → lista de variantes
        if (count($item) === 1) {
            return [$selectedKey, $item[0]];
        }

        return $this->askVariant($selectedKey, $item);
    }

    /**
     * Selección interactiva cuando un contexto tiene múltiples variantes.
     *
     * @return array{0: string, 1: array}
     */
    private function askVariant(string $contextKey, array $items): array
    {
        $names = array_map(fn($item) => $item['id'] ?? '?', $items);
        $selected = $this->choice("Selecciona la variante de '{$contextKey}':", $names, 0);

        foreach ($items as $item) {
            if (($item['id'] ?? '') === $selected) {
                return [$contextKey, $item];
            }
        }

        throw new \InvalidArgumentException("Variante '{$selected}' no encontrada.");
    }

    /**
     * Carga todos los contextos desde contexts.json.
     *
     * ARQUITECTURA HÍBRIDA:
     *   - central, shared, tenant_shared → objetos únicos (acceso directo)
     *   - tenant → array de objetos (múltiples instancias)
     *
     * @throws \InvalidArgumentException Si no hay contexts.json o está vacío
     */
    private function loadContexts(): array
    {
        try {
            return ContextResolver::all();
        } catch (Throwable $e) {
            throw new \InvalidArgumentException(
                "No se pudo leer contexts.json: {$e->getMessage()}\n"
                . "Ejecuta: php artisan innodite:module-setup"
            );
        }
    }

    // ─── Helpers de orquestación ──────────────────────────────────────────────

    /**
     * Derive la funcionalidad (prefijo de ruta) desde el nombre del módulo.
     * Sub-proceso atómico: User → users, InvoiceItem → invoice-items
     */
    private function resolveFunctionality(string $moduleName): string
    {
        return Str::kebab(Str::plural(Str::snake($moduleName)));
    }

    /**
     * Determina si el usuario pasó algún flag de componente individual.
     */
    private function hasIndividualFlags(): bool
    {
        return (bool) (
            $this->option('model')      ||
            $this->option('controller') ||
            $this->option('service')    ||
            $this->option('repository') ||
            $this->option('migration')  ||
            $this->option('request')
        );
    }

    /**
     * Elimina el directorio del módulo como parte de un rollback.
     */
    private function performRollback(string $modulePath): void
    {
        $this->components->task('Ejecutando rollback', function () use ($modulePath) {
            File::deleteDirectory($modulePath);
            return true;
        });

        $this->components->warn("Rollback completado. Los archivos de '{$modulePath}' fueron eliminados.");
    }

    // ─── Output visual ────────────────────────────────────────────────────────

    /**
     * Muestra la tabla de configuración antes de ejecutar.
     */
    private function displayConfigTable(
        string $moduleName,
        string $contextKey,
        string $contextId,
        string $functionality
    ): void {
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Módulo',        $moduleName],
                ['Contexto',      $contextKey],
                ['Variante',      $contextId],
                ['Prefijo ruta',  $functionality],
            ]
        );
    }

    /**
     * Muestra el resumen final tras la generación exitosa.
     */
    private function displaySuccess(string $moduleName, string $contextKey, string $contextId): void
    {
        $this->components->info("Módulo <comment>{$moduleName}</comment> generado exitosamente.");
        $this->newLine();
        $this->line("  Próximos pasos:");
        // El primer paso pedía añadir marcadores en el `routes/web.php` del proyecto, que era donde
        // el generador inyectaba una segunda copia de las rutas. Ya no escribe ahí: las del módulo
        // viven en Modules/{$moduleName}/Routes/ y las carga el ServiceProvider del paquete.
        // Ninguno de los dos pasos que decía antes era cierto en la v4:
        //   · El ServiceProvider del módulo NO hay que registrarlo a mano — lo registra el del
        //     paquete al arrancar, y en los dos proyectos reales `bootstrap/providers.php` no
        //     nombra ni uno solo.
        //   · `php artisan migrate` a secas es justo lo que el patrón prohíbe: ejecuta todo
        //     lo pendiente del proyecto, no la migración de este módulo. Para eso está `deploy`,
        //     que además pone permisos y datos canónicos en el orden declarado.
        $this->line("    1. Revisa la migración generada y ajusta sus columnas al negocio.");
        $this->line("    2. Despliega el módulo: <comment>php artisan innodite:deploy stage</comment>");
        $this->line("       (o una sola migración con <comment>innodite:migrate-one</comment>).");
        $this->newLine();

        $this->avisarSiLasVistasNoSonLasQueSePidieron();
        $this->decirQuienAportoLosStubs();
        $this->avisarSiElModuloNoVaACargar($moduleName);
        $this->avisarSiLaPantallaNoVaAAbrir();
    }

    /**
     * ⚠️ Se pidió la biblioteca de interfaz y nadie la aporta: las vistas salieron genéricas.
     *
     * **Es el aviso que convierte un interruptor mudo en uno que habla.** El proyecto declaró
     * `frontend.modo = innodite`, así que espera pantallas escritas con los componentes de esa
     * biblioteca. Si ningún paquete instalado aporta sus plantillas, el generador escribe las
     * genéricas — y el comando termina en verde.
     *
     * Sin decirlo aquí, el usuario pidió una cosa y tiene otra, y **no se entera hasta abrir la
     * pantalla**: para entonces puede llevar varios módulos generados con la forma equivocada.
     *
     * ⛔ Y no se arregla trayendo esas plantillas dentro: este paquete es público, y la forma de una
     * biblioteca de la casa no se publica en un repositorio abierto. Las aporta quien las tiene.
     */
    private function avisarSiLasVistasNoSonLasQueSePidieron(): void
    {
        if (! Frontend::pidioBibliotecaQueNadieAporta()) {
            return;
        }

        $this->components->warn('Las vistas se generaron genéricas, no con la biblioteca que pediste.');

        $this->line(
            '  <fg=yellow>FALLA:</> el proyecto declara <comment>frontend.modo = innodite</comment>, '
            . 'y ningún paquete instalado aporta las plantillas de esa biblioteca.'
        );
        $this->line(
            '  <fg=green>FIX:</> instala la biblioteca en el proyecto, o cambia el modo a '
            . '<comment>default</comment> si estas vistas te sirven:'
        );
        $this->line('       <comment>MODULE_MAKER_FRONTEND=default</comment> en el .env');
        $this->line(
            '  <fg=gray>Un paquete aporta sus plantillas trayendo stubs/module-maker/contextual/ '
            . 'en su raíz; el generador las usa sin configurar nada.</>'
        );
        $this->newLine();
    }

    /**
     * Dice si el módulo se generó con stubs de otro paquete, y de cuál.
     *
     * ⚠️ **Cambiar el origen de lo que se genera sin decirlo es lo que después nadie sabe
     * explicar.** Un paquete instalado puede aportar sus propios stubs y ganarle a los del
     * generador; eso es deliberado y útil, pero significa que dos proyectos con el mismo comando
     * obtienen código distinto. Que salga escrito convierte una sorpresa en un dato.
     *
     * Con más de un paquete aportando el mismo archivo hay un empate, y ese sí se avisa: gana el
     * primero por orden alfabético, que es determinista pero no necesariamente el que se quería.
     */
    private function decirQuienAportoLosStubs(): void
    {
        $carpetas = StubsDeVendor::carpetas();

        if ($carpetas === []) {
            return;
        }

        $this->line(
            '  <fg=cyan>Stubs aportados por:</> <comment>'
            . implode('</comment>, <comment>', array_keys($carpetas))
            . '</comment>'
        );
        $this->line(
            '  <fg=gray>Ganan a los del paquete y pierden contra los de '
            . 'module-maker-config/stubs/contextual/.</>'
        );

        $empatados = [];

        foreach (['vue-index.stub', 'vue-create.stub', 'vue-edit.stub', 'vue-show.stub', 'provider-boot.stub'] as $stub) {
            $quienes = StubsDeVendor::paquetesQueAportan($stub);

            if (count($quienes) > 1) {
                $empatados[$stub] = $quienes;
            }
        }

        foreach ($empatados as $stub => $quienes) {
            $this->components->warn(
                "Varios paquetes aportan {$stub}: " . implode(', ', $quienes)
                . '. Gana ' . $quienes[0] . ' (orden alfabético).'
            );
        }

        $this->newLine();
    }

    /**
     * Comprueba que el módulo recién escrito **va a cargar de verdad**, y lo dice si no.
     *
     * ⚠️ **El fallo que esto evita no da ningún error.** El proveedor del paquete registra el de cada
     * módulo, pero dentro de un `class_exists()`: si la clase no resuelve —porque el proyecto no
     * declara el namespace `Modules\` en su autoload, o porque falta el `dump-autoload` de después de
     * generar—, no se registra, sus rutas no se cargan y **la aplicación sigue respondiendo 200**.
     * Se descubre buscando durante horas una ruta que «no existe».
     */
    private function avisarSiElModuloNoVaACargar(string $moduleName): void
    {
        $proveedor = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";

        if (class_exists($proveedor)) {
            return;
        }

        $this->components->warn("El módulo está escrito, pero todavía NO carga.");

        if (! $this->proyectoDeclaraElAutoload()) {
            $this->line(
                '  <fg=yellow>FALLA:</> el composer.json del proyecto no declara el namespace '
                . '<comment>Modules\\</comment> en su autoload, así que ninguna de sus clases '
                . 'resuelve — ni el proveedor, ni el modelo, ni el servicio.'
            );
            $this->line('  <fg=green>FIX:</> añádelo a <comment>autoload.psr-4</comment> y recarga:');
            $this->line('       <comment>"Modules\\\\": "Modules/"</comment>');
            $this->line('       <comment>composer dump-autoload</comment>');
        } else {
            $this->line(
                '  <fg=yellow>FALLA:</> el autoload todavía no conoce las clases recién escritas.'
            );
            $this->line('  <fg=green>FIX:</> <comment>composer dump-autoload</comment>');
        }

        $this->line(
            '  <fg=gray>Sin esto la aplicación arranca igual y sus rutas sencillamente no existen.</>'
        );
        $this->newLine();
    }

    /**
     * Comprueba que la pantalla recién generada **va a abrir**, y lo dice si no.
     *
     * Es el gemelo del aviso de arriba, en el otro lado. Aquel mira si el módulo carga en el
     * servidor; este, si su vista sobrevive en el navegador. Las cuatro vistas piden sus rutas por
     * el nombre —lo correcto, porque así un cambio de prefijo no obliga a tocar ninguna—, y quien
     * traduce ese nombre a una dirección es `route()`, que la pone Ziggy.
     *
     * ⚠️ **Sin Ziggy la pantalla no falla a medias: no llega a pintarse.** `route is not defined`
     * salta al montar el componente, y el mensaje no menciona ni al módulo, ni al paquete, ni a
     * Ziggy. En el servidor no queda constancia de nada, porque la petición respondió 200.
     */
    private function avisarSiLaPantallaNoVaAAbrir(): void
    {
        if (! Ziggy::falta()) {
            return;
        }

        $this->components->warn('El módulo está escrito, pero su pantalla todavía NO abre.');

        if (! Ziggy::instalado()) {
            $this->line(
                '  <fg=yellow>FALLA:</> las vistas generadas piden sus rutas por el nombre y este '
                . 'proyecto no tiene <comment>Ziggy</comment>, que es quien resuelve el nombre en '
                . 'el navegador.'
            );
            $this->line('  <fg=green>FIX:</> instálalo y publica el mapa en el layout:');
            $this->line('       <comment>composer require tightenco/ziggy</comment>');
            $this->line('       <comment>@routes</comment> en el &lt;head&gt; del layout Blade');
        } else {
            $this->line(
                '  <fg=yellow>FALLA:</> Ziggy está instalado, pero ningún layout Blade publica el '
                . 'mapa de rutas.'
            );
            $this->line(
                '  <fg=green>FIX:</> añade <comment>@routes</comment> en el &lt;head&gt; del '
                . 'layout que sirve las páginas.'
            );
        }

        $this->line(
            '  <fg=gray>El síntoma es una pantalla en blanco y «route is not defined» en la '
            . 'consola del navegador; en el servidor no aparece ningún error.</>'
        );
        $this->newLine();
    }

    /** ¿Está `Modules\` declarado en el autoload PSR-4 del proyecto? */
    private function proyectoDeclaraElAutoload(): bool
    {
        $composer = base_path('composer.json');

        if (! File::exists($composer)) {
            return true;   // sin composer.json no hay nada que afirmar: no se inventa un fallo
        }

        $declarado = json_decode(File::get($composer), true);
        $psr4      = $declarado['autoload']['psr-4'] ?? [];

        return is_array($psr4) && array_key_exists('Modules\\', $psr4);
    }
}
