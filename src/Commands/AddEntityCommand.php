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
use Innodite\LaravelModuleMaker\Support\ContextOption;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Throwable;

/**
 * AddEntityCommand — Agrega una nueva entidad a un módulo existente.
 *
 * Uso:
 *   php artisan innodite:add-entity UserManagement Role --context=central
 *   php artisan innodite:add-entity UserManagement Permission --context=energy-spain -M -C -S -R
 *
 * La entidad se coloca en su propia subcarpeta dentro de cada capa del módulo:
 *   Models/{Context}/Role/CentralRole.php
 *   Http/Controllers/{Context}/Role/CentralRoleController.php
 *   Services/{Context}/Role/CentralRoleService.php
 *   Services/Contracts/{Context}/Role/CentralRoleServiceInterface.php
 *   etc.
 *
 * Las convenciones de nombres (prefijo de contexto) se mantienen intactas.
 */
class AddEntityCommand extends Command
{
    use RehearsesChanges;
    use PrintsHeader;
    use ReportsFailures;

    protected $signature = 'innodite:add-entity
        {module                : Nombre del módulo existente (ej: UserManagement)}
        {entity                : Nombre de la nueva entidad en singular (ej: Role)}
        {--context=            : Contexto donde se genera, en multitenant: central | shared | tenant_shared | id del tenant}
        {--M|model             : Solo añade el modelo}
        {--C|controller        : Solo añade el controlador}
        {--S|service           : Solo añade el servicio e interface}
        {--R|repository        : Solo añade el repositorio e interface}
        {--G|migration         : Solo añade la migración contextualizada}
        {--Q|request           : Solo añade el form request}
        {--dry-run             : Ensayo: enseña lo que haría, sin escribir nada}';

    protected $description = 'Agrega una nueva entidad a un módulo existente con su propia subcarpeta.';

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
        $moduleName = Str::studly($this->argument('module'));
        $entityName = Str::studly($this->argument('entity'));
        $modulePath = config('make-module.module_path') . "/{$moduleName}";

        $this->cabecera("Entidad {$entityName} en {$moduleName}");

        // ── Verificar que el módulo existe ────────────────────────────────────
        if (!File::isDirectory($modulePath)) {
            $this->fallo(
                "el módulo '{$moduleName}' no existe en {$modulePath}.",
                "créalo primero — php artisan innodite:make-module {$moduleName}",
                'Este comando añade subfuncionalidades a un módulo que ya está; no crea el módulo.'
            );
            return self::FAILURE;
        }

        // ── Resolver contexto ─────────────────────────────────────────────────
        try {
            [$contextKey, $contextItem] = $this->resolveContext();
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        $contextId = $contextItem['id'] ?? '';

        // ── Mostrar resumen ───────────────────────────────────────────────────
        $this->components->twoColumnDetail('Módulo', $moduleName);
        $this->components->twoColumnDetail('Entidad', $entityName);
        $this->components->twoColumnDetail('Contexto', "{$contextKey}" . ($contextId ? " / {$contextId}" : ''));
        $this->newLine();

        // ── Determinar flags activos ──────────────────────────────────────────
        $flags = [
            'model'      => $this->option('model'),
            'controller' => $this->option('controller'),
            'service'    => $this->option('service'),
            'repository' => $this->option('repository'),
            'migration'  => $this->option('migration'),
            'request'    => $this->option('request'),
        ];

        // Si no hay flags, generar todos los componentes
        $hasFlags = array_filter($flags);
        if (empty($hasFlags)) {
            $flags = array_fill_keys(array_keys($flags), true);
        }

        $componentConfig = [
            'context'    => $contextKey,
            'context_id' => $contextId,
            'subFeature' => $entityName,
            // La funcionalidad sale de la ENTIDAD, no del módulo. `make-module` las deriva del
            // mismo sitio porque ahí coinciden —el módulo *es* su primera subfuncionalidad—, pero
            // al agregar la segunda dejan de coincidir: sin esta línea, `Payment` heredaría las
            // rutas y los permisos de `Invoice`, y dos subfuncionalidades distintas abrirían con
            // la misma llave.
            'functionality' => Str::kebab(Str::plural(Str::snake($entityName))),
        ];

        // ── Generar componentes ───────────────────────────────────────────────
        try {
            $this->components->task("Generando componentes de '{$entityName}'", function () use (
                $moduleName,
                $entityName,
                $flags,
                $componentConfig
            ) {
                (new ModuleGenerator($moduleName, true, null, $this))
                    ->createIndividualComponents($flags, $componentConfig, $entityName);
                return true;
            });

            // Aquí se inyectaban las rutas de la entidad en el `routes/web.php` del proyecto. Esa
            // vía escribía `create` y `edit` —pantallas que la v4 no genera— y ninguna ruta con
            // permiso. Las de la entidad ya están en Modules/{Módulo}/Routes/, con sus seis
            // acciones y su permiso cada una, y el ServiceProvider del paquete las carga solo.

            $this->newLine();
            $this->components->info("Entidad '{$entityName}' agregada al módulo '{$moduleName}' correctamente.");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Resuelve el contexto desde la opción --context o interactivamente.
     *
     * Las guardas del modo son **las mismas** que aplica `make-module`, y por eso salen de
     * `ContextOption` en vez de estar copiadas aquí: este comando añade subfuncionalidades a un
     * módulo que ya existe, así que equivocarse de eje produce un módulo **mitad en un contexto y
     * mitad en otro** — peor que uno entero mal, porque nada se ve raro al abrirlo.
     */
    private function resolveContext(): array
    {
        $mode        = ModuleMode::current();
        $allContexts = ContextResolver::all();
        $option      = trim($this->option('context') ?? '');

        ContextOption::check($mode, $option, $allContexts, $this->input->isInteractive());

        if (! $mode->hasContextAxis()) {
            return ['', []];
        }

        if ($option === '') {
            return $this->askContextInteractive($allContexts);
        }

        if (isset($allContexts[$option])) {
            $item = $allContexts[$option];

            if (!is_array($item)) {
                throw new \InvalidArgumentException("Contexto '{$option}' tiene formato inválido.");
            }

            $isAssociative = array_keys($item) !== range(0, count($item) - 1);

            if ($isAssociative) {
                return [$option, $item];
            }

            if (count($item) === 1) {
                return [$option, $item[0]];
            }

            return $this->askVariant($option, $item);
        }

        foreach ($allContexts['tenant'] ?? [] as $item) {
            if ($this->tenantMatches($option, $item)) {
                return ['tenant', $item];
            }
        }

        $available = implode(', ', array_keys($allContexts));
        $tenants   = implode(', ', array_map(fn ($t) => $t['id'] ?? 'unknown', $allContexts['tenant'] ?? []));

        throw new \InvalidArgumentException(
            "Contexto '{$option}' no encontrado en contexts.json.\n"
            . "  Contextos disponibles: {$available}\n"
            . "  Tenants disponibles:   {$tenants}"
        );
    }

    private function askContextInteractive(array $allContexts): array
    {
        $items   = ContextResolver::allItems();
        $choices = array_map(fn ($c) => $c['id'] ?? $c['class_prefix'] ?? 'unknown', $items);

        $selected = $this->choice('¿En qué contexto se ubica esta entidad?', $choices);
        $chosen   = $items[array_search($selected, $choices)];

        $contextKey = $chosen['id'] ?? '';
        foreach ($allContexts as $key => $value) {
            if ($key === 'tenant' && is_array($value)) {
                foreach ($value as $t) {
                    if (($t['id'] ?? '') === $contextKey) {
                        return ['tenant', $t];
                    }
                }
            } elseif (is_array($value) && ($value['id'] ?? '') === $contextKey) {
                return [$key, $value];
            }
        }

        return ['central', $chosen];
    }

    private function askVariant(string $contextKey, array $variants): array
    {
        $choices  = array_map(fn ($v) => $v['id'] ?? $v['class_prefix'] ?? 'unknown', $variants);
        $selected = $this->choice("¿Qué variante de '{$contextKey}'?", $choices);
        $chosen   = $variants[array_search($selected, $choices)];

        return [$contextKey, $chosen];
    }

    private function tenantMatches(string $input, array $item): bool
    {
        return ($item['id'] ?? '')           === $input
            || ($item['class_prefix'] ?? '') === $input
            || ($item['route_prefix'] ?? '')  === $input
            || Str::slug($item['id'] ?? '')   === Str::slug($input);
    }
}
