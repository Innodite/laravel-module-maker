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
 *      Modo interactivo: pregunta contexto y funcionalidad, genera estructura limpia.
 *
 *   2. php artisan innodite:make-module User --json
 *      Modo configurado: lee Modules/module-maker-config/user.json y genera el módulo
 *      con todos los detalles definidos (contexto, atributos, relaciones, rutas, etc.)
 *
 *   3. php artisan innodite:make-module User --model=User --controller=UserController
 *      Componente individual: agrega un archivo específico a un módulo existente.
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
                             {--model= : Nombre del modelo a crear (componente individual)}
                             {--controller= : Nombre del controlador a crear (componente individual)}
                             {--request= : Nombre del request a crear (componente individual)}
                             {--service= : Nombre del servicio a crear (componente individual)}
                             {--repository= : Nombre del repositorio a crear (componente individual)}
                             {--migration= : Nombre de la migración a crear (componente individual)}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Crea un módulo (interactivo o desde JSON) o agrega componentes individuales.';

    /**
     * Ejecuta el comando principal según el modo seleccionado.
     *
     * @return int
     */
    public function handle(): int
    {
        $moduleName = Str::studly($this->argument('name'));
        $modulePath = config('make-module.module_path') . "/{$moduleName}";

        // ── Modo: componentes individuales ────────────────────────────────────
        if ($this->hasIndividualComponentOptions()) {
            if (! File::exists($modulePath)) {
                $this->error("El módulo '{$moduleName}' no existe. Créalo primero antes de añadir componentes.");
                return Command::FAILURE;
            }

            $generator = new ModuleGenerator($moduleName, true, null, $this);
            $generator->createIndividualComponents($this->options());
            return Command::SUCCESS;
        }

        // ── Módulo ya existe (sin componentes individuales) ───────────────────
        if (File::exists($modulePath)) {
            $this->error("El módulo '{$moduleName}' ya existe. Usa --model, --controller, etc. para añadir componentes.");
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
     * Genera el módulo a partir del archivo JSON de configuración.
     * Busca Modules/module-maker-config/{modulename}.json (en minúscula o kebab-case).
     *
     * @param  string  $moduleName  Nombre del módulo en StudlyCase
     * @return int
     */
    private function handleJsonMode(string $moduleName): int
    {
        $configDir      = config('make-module.module_path') . '/module-maker-config';
        $jsonPath       = "{$configDir}/" . Str::lower($moduleName) . '.json';
        $jsonPathKebab  = "{$configDir}/" . Str::kebab($moduleName) . '.json';

        if (! File::exists($jsonPath) && File::exists($jsonPathKebab)) {
            $jsonPath = $jsonPathKebab;
        }

        if (! File::exists($jsonPath)) {
            $this->error("No se encontró el archivo de configuración para '{$moduleName}'.");
            $this->line("  Buscado en: {$jsonPath}");
            $this->line("  Crea '{$configDir}/" . Str::lower($moduleName) . ".json' con la estructura del módulo.");
            $this->line("  Ejemplo de estructura disponible en: {$configDir}/blog.json");
            return Command::FAILURE;
        }

        $config = json_decode(File::get($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Error al parsear '{$jsonPath}': " . json_last_error_msg());
            return Command::FAILURE;
        }

        $this->info("📄 Usando configuración: {$jsonPath}");

        $resolvedName = Str::studly($config['module_name'] ?? $moduleName);
        $generator    = new ModuleGenerator($resolvedName, false, $config, $this);
        $generator->createDynamicModule();

        return Command::SUCCESS;
    }

    /**
     * Modo interactivo: pregunta el contexto y la funcionalidad, genera la estructura limpia.
     * Si no hay contexts.json disponible, genera un módulo básico sin contexto.
     *
     * @param  string  $moduleName  Nombre del módulo en StudlyCase
     * @return int
     */
    private function handleInteractiveMode(string $moduleName): int
    {
        $this->info("🚀 Creando módulo '{$moduleName}'...");

        // Cargar contextos disponibles desde contexts.json
        try {
            $contexts = ContextResolver::all();
        } catch (\Throwable) {
            $this->warn('No se encontró contexts.json. Ejecuta primero: php artisan innodite:module-setup');
            $this->warn('Generando módulo sin contexto (estructura básica)...');
            (new ModuleGenerator($moduleName, true, null, $this))->createCleanModule();
            return Command::SUCCESS;
        }

        if (empty($contexts)) {
            $this->warn('contexts.json está vacío. Generando módulo sin contexto...');
            (new ModuleGenerator($moduleName, true, null, $this))->createCleanModule();
            return Command::SUCCESS;
        }

        // Construir lista de opciones etiquetadas para el choice interactivo
        $choices = [];
        $keyMap  = [];

        foreach ($contexts as $key => $ctx) {
            $label          = $ctx['label'] ?? $key;
            $choices[]      = $label;
            $keyMap[$label] = $key;
        }

        $selectedLabel = $this->choice('¿En qué contexto quieres crear el módulo?', $choices, 0);
        $contextKey    = $keyMap[$selectedLabel];

        // Si el contexto es 'tenant', preguntar cuál tenant específico
        $tenantKey = null;
        if ($contextKey === 'tenant') {
            $tenants     = ContextResolver::allTenants();
            $tenantChoices = [];
            $tenantKeyMap  = [];

            foreach ($tenants as $key => $tenant) {
                $label               = $tenant['label'] ?? $key;
                $tenantChoices[]     = $label;
                $tenantKeyMap[$label] = $key;
            }

            if (empty($tenantChoices)) {
                $this->error("No hay tenants definidos en contexts.json. Agrega tenants en la sección 'tenants'.");
                return Command::FAILURE;
            }

            $selectedTenantLabel = $this->choice('¿Para cuál tenant?', $tenantChoices, 0);
            $tenantKey           = $tenantKeyMap[$selectedTenantLabel];
        }

        // Preguntar nombre de funcionalidad para el prefijo de ruta
        $defaultFunctionality = Str::plural(Str::kebab($moduleName));
        $functionality        = $this->ask(
            "Nombre de la funcionalidad para las rutas (ej: users, campaign-goals)",
            $defaultFunctionality
        );

        $this->newLine();
        $this->info("  Contexto      : {$selectedLabel} ({$contextKey})");
        if ($tenantKey) {
            $this->info("  Tenant        : {$tenantKey}");
        }
        $this->info("  Funcionalidad : {$functionality}");
        $this->newLine();

        (new ModuleGenerator($moduleName, true, null, $this))
            ->createCleanModuleWithContext($contextKey, $functionality, $tenantKey);

        return Command::SUCCESS;
    }

    /**
     * Determina si el usuario pasó alguna opción de componente individual.
     *
     * @return bool
     */
    private function hasIndividualComponentOptions(): bool
    {
        return (bool) ($this->option('model')
            || $this->option('controller')
            || $this->option('request')
            || $this->option('service')
            || $this->option('repository')
            || $this->option('migration'));
    }
}
