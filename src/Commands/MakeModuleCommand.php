<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\ModuleGenerator;
use Innodite\LaravelModuleMaker\Support\ContextResolver;

/**
 * Comando principal para generar un módulo Laravel con arquitectura modular.
 *
 * Modos de uso:
 *   1. php artisan innodite:make-module User
 *      Modo interactivo: pregunta contexto, variante y automático/JSON.
 *
 *   2. php artisan innodite:make-module User --json
 *      Lee Modules/module-maker-config/user.json y genera con esa configuración.
 *
 *   3. php artisan innodite:make-module User -M -C -S -R
 *      Componentes individuales: pregunta contexto y crea solo los marcados.
 */
class MakeModuleCommand extends Command
{
    /**
     * Firma del comando con sus opciones.
     *
     * @var string
     */
    protected $signature = 'innodite:make-module {name : Nombre del módulo en StudlyCase}
                             {--json : Usa el archivo JSON de configuración en module-maker-config/}
                             {--M|model      : Crea el modelo}
                             {--C|controller : Crea el controlador e interface}
                             {--S|service    : Crea el servicio e interface}
                             {--R|repository : Crea el repositorio e interface}
                             {--G|migration  : Crea la migración}
                             {--Q|request    : Crea el form request}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Crea un módulo completo o componentes individuales con convención de contexto.';

    /**
     * Ejecuta el comando principal según el modo seleccionado.
     *
     * @return int
     */
    public function handle(): int
    {
        $moduleName = Str::studly($this->argument('name'));
        $modulePath = config('make-module.module_path') . "/{$moduleName}";

        // ── Modo: componentes individuales (-M -C -S -R -G -Q) ───────────────
        if ($this->hasIndividualComponentOptions()) {
            return $this->handleComponentMode($moduleName, $modulePath);
        }

        // ── Módulo ya existe (sin componentes individuales) ───────────────────
        if (File::exists($modulePath)) {
            $this->error("El módulo '{$moduleName}' ya existe. Usa -M -C -S -R -G -Q para añadir componentes.");
            return Command::FAILURE;
        }

        // ── Modo: JSON ────────────────────────────────────────────────────────
        if ($this->option('json')) {
            return $this->handleJsonMode($moduleName);
        }

        // ── Modo: interactivo (por defecto) ───────────────────────────────────
        return $this->handleInteractiveMode($moduleName);
    }

    /**
     * Modo componentes individuales: pregunta contexto y crea solo los archivos marcados.
     * Si el módulo no existe, pregunta si desea crearlo antes de continuar.
     *
     * @param  string  $moduleName  Nombre del módulo en StudlyCase
     * @param  string  $modulePath  Ruta absoluta al directorio del módulo
     * @return int
     */
    private function handleComponentMode(string $moduleName, string $modulePath): int
    {
        if (! File::exists($modulePath)) {
            $this->warn("El módulo '{$moduleName}' no existe.");
            if (! $this->confirm("¿Quieres crear la estructura base del módulo antes de continuar?")) {
                $this->line("Operación cancelada.");
                return Command::FAILURE;
            }
            (new ModuleGenerator($moduleName, true, null, $this))->createFolders();
        }

        $allContexts = $this->loadContexts();
        if ($allContexts === null) {
            return Command::FAILURE;
        }

        [$contextKey, $selectedItem] = $this->askContextSelection($allContexts);
        if ($contextKey === null) {
            return Command::FAILURE;
        }

        $componentConfig = [
            'context'      => $contextKey,
            'context_name' => $selectedItem['name'],
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
        $this->line("  Módulo   : {$moduleName}");
        $this->line("  Contexto : {$contextKey} → {$selectedItem['name']}");
        $this->newLine();

        (new ModuleGenerator($moduleName, true, null, $this))
            ->createIndividualComponents($flags, $componentConfig);

        return Command::SUCCESS;
    }

    /**
     * Genera el módulo a partir del archivo JSON de configuración.
     * Busca Modules/module-maker-config/{modulename}.json (en minúscula o kebab-case).
     *
     * @param  string  $moduleName  Nombre del módulo en StudlyCase
     * @return int
     */
    private function handleJsonMode(string $moduleName): int
    {
        $configDir     = config('make-module.module_path') . '/module-maker-config';
        $jsonPath      = "{$configDir}/" . Str::lower($moduleName) . '.json';
        $jsonPathKebab = "{$configDir}/" . Str::kebab($moduleName) . '.json';

        if (! File::exists($jsonPath) && File::exists($jsonPathKebab)) {
            $jsonPath = $jsonPathKebab;
        }

        if (! File::exists($jsonPath)) {
            $this->error("No se encontró el archivo de configuración para '{$moduleName}'.");
            $this->line("  Buscado en : {$jsonPath}");
            $this->line("  Crea '{$configDir}/" . Str::lower($moduleName) . ".json' con la estructura del módulo.");
            $this->line("  Crea el archivo JSON y luego ejecuta: php artisan innodite:make-module {$moduleName} --json");
            return Command::FAILURE;
        }

        $config = json_decode(File::get($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Error al parsear '{$jsonPath}': " . json_last_error_msg());
            return Command::FAILURE;
        }

        $this->info("Usando configuración: {$jsonPath}");

        $resolvedName = Str::studly($config['module_name'] ?? $moduleName);
        $generator    = new ModuleGenerator($resolvedName, false, $config, $this);
        $generator->createDynamicModule();

        return Command::SUCCESS;
    }

    /**
     * Modo interactivo: contexto → variante (si >1) → automático/JSON → funcionalidad.
     * Si no hay contexts.json disponible, genera un módulo básico sin contexto.
     *
     * @param  string  $moduleName  Nombre del módulo en StudlyCase
     * @return int
     */
    private function handleInteractiveMode(string $moduleName): int
    {
        $this->info("Creando módulo '{$moduleName}'...");

        $allContexts = $this->loadContexts();

        if ($allContexts === null) {
            (new ModuleGenerator($moduleName, true, null, $this))->createCleanModule();
            return Command::SUCCESS;
        }

        [$contextKey, $selectedItem] = $this->askContextSelection($allContexts);
        if ($contextKey === null) {
            return Command::FAILURE;
        }

        // ── Automático o desde JSON ───────────────────────────────────────────
        $generationMode = $this->choice(
            '¿Cómo generar el módulo?',
            ['Automático (estructura limpia)', 'Desde JSON (module-maker-config/{module}.json)'],
            0
        );

        if (str_starts_with($generationMode, 'Desde JSON')) {
            return $this->handleJsonMode($moduleName);
        }

        // ── Funcionalidad para el prefijo de ruta ─────────────────────────────
        $defaultFunctionality = Str::plural(Str::kebab($moduleName));
        $functionality        = $this->ask(
            'Nombre de la funcionalidad para las rutas (ej: users, campaign-goals)',
            $defaultFunctionality
        );

        $this->newLine();
        $this->line("  Contexto      : {$contextKey}");
        $this->line("  Variante      : {$selectedItem['name']}");
        $this->line("  Funcionalidad : {$functionality}");
        $this->newLine();

        (new ModuleGenerator($moduleName, true, null, $this))
            ->createCleanModuleWithContext($contextKey, $functionality, $selectedItem['name']);

        return Command::SUCCESS;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Carga los contextos desde contexts.json.
     * Muestra advertencia y retorna null si no hay contexts.json disponible o está vacío.
     *
     * @return array<string, array>|null
     */
    private function loadContexts(): ?array
    {
        try {
            $allContexts = ContextResolver::all();
        } catch (\Throwable) {
            $this->warn('No se encontró contexts.json. Ejecuta: php artisan innodite:module-setup');
            return null;
        }

        if (empty($allContexts)) {
            $this->warn('contexts.json está vacío.');
            return null;
        }

        return $allContexts;
    }

    /**
     * Pregunta al usuario el contexto y la variante a usar.
     * Si el contexto tiene un solo item, lo selecciona automáticamente.
     * Retorna [contextKey, selectedItem] o [null, null] si hay error.
     *
     * @param  array<string, array>  $allContexts  Mapa de contextos desde contexts.json
     * @return array{0: string|null, 1: array|null}
     */
    private function askContextSelection(array $allContexts): array
    {
        $contextKeys    = array_keys($allContexts);
        $selectedCtxKey = $this->choice('¿En qué contexto?', $contextKeys, 0);
        $contextItems   = $allContexts[$selectedCtxKey];

        if (empty($contextItems)) {
            $this->error("El contexto '{$selectedCtxKey}' no tiene variantes en contexts.json.");
            return [null, null];
        }

        if (count($contextItems) === 1) {
            return [$selectedCtxKey, $contextItems[0]];
        }

        $names        = array_map(fn ($item) => $item['name'] ?? '?', $contextItems);
        $selectedName = $this->choice('¿Cuál variante?', $names, 0);

        foreach ($contextItems as $item) {
            if (($item['name'] ?? '') === $selectedName) {
                return [$selectedCtxKey, $item];
            }
        }

        $this->error("No se encontró la variante '{$selectedName}'.");
        return [null, null];
    }

    /**
     * Determina si el usuario pasó alguna opción de componente individual.
     *
     * @return bool
     */
    private function hasIndividualComponentOptions(): bool
    {
        return (bool) (
            $this->option('model')
            || $this->option('controller')
            || $this->option('service')
            || $this->option('repository')
            || $this->option('migration')
            || $this->option('request')
        );
    }
}
