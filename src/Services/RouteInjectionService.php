<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * RouteInjectionService — Motor de Inyección de Rutas (WORKPLAN Fase 3)
 *
 * Escribe bloques de rutas en los archivos del proyecto Laravel
 * (routes/web.php, routes/tenant.php) usando marcadores de posición.
 *
 * Marcadores esperados en los archivos del proyecto:
 *   web.php          → // {{CENTRAL_ROUTES_END}}
 *   tenant.php Shared → // {{TENANT_SHARED_ROUTES_END}}
 *   tenant.php Tenant → // {{TENANT_{NAME}_ROUTES_END}}
 *
 * Garantías:
 *   - Idempotente: nunca inyecta el mismo bloque dos veces.
 *   - Agnosticismo: si route_middleware es [], omite ->middleware().
 *   - Auto-import: añade el `use ControllerClass;` si no existe.
 *   - Indentación: respeta el nivel de sangría del marcador detectado.
 */
class RouteInjectionService
{
    public function __construct(
        private readonly ?object $output = null
    ) {}

    // ─── Punto de entrada ─────────────────────────────────────────────────────

    /**
     * Inyecta el bloque de rutas en el/los archivos correspondientes al contexto.
     *
     * @param  string  $contextKey     Clave del contexto (central, shared, tenant_shared, tenant)
     * @param  string  $entityName     Nombre de la entidad en StudlyCase (ej: User)
     * @param  string  $contextId      ID del contexto (ej: central, clinic-one)
     * @param  string  $controllerFqcn FQCN completo del controlador
     * @param  array   $contextConfig  Configuración del contexto desde contexts.json
     * @return void
     */
    public function inject(
        string $contextKey,
        string $entityName,
        string $contextId,
        string $controllerFqcn,
        array  $contextConfig
    ): void {
        // route_file puede ser string o array ['web.php', 'tenant.php']
        $routeFiles = (array) ($contextConfig['route_file'] ?? 'web.php');

        foreach ($routeFiles as $routeFile) {
            $this->injectIntoFile(
                $routeFile,
                $contextKey,
                $entityName,
                $contextId,
                $controllerFqcn,
                $contextConfig
            );
        }
    }

    // ─── Inyección por archivo ────────────────────────────────────────────────

    /**
     * Inyecta el bloque en un archivo de rutas específico.
     *
     * Flujo:
     *   1. Verificar que el archivo existe.
     *   2. Comprobar idempotencia (bloque ya inyectado → skip).
     *   3. Resolver el marcador correcto para el contexto + archivo.
     *   4. Si el marcador no existe y es tenant específico, crearlo.
     *   5. Detectar indentación del marcador.
     *   6. Construir el bloque con prefijos correctos.
     *   7. Añadir use statement.
     *   8. Inyectar antes del marcador.
     *
     * @param  string  $routeFile      Nombre del archivo (web.php | tenant.php)
     * @param  string  $contextKey     Clave del contexto
     * @param  string  $entityName     Nombre de la entidad
     * @param  string  $contextId      ID del contexto
     * @param  string  $controllerFqcn FQCN del controlador
     * @param  array   $contextConfig  Configuración del contexto
     * @return void
     */
    private function injectIntoFile(
        string $routeFile,
        string $contextKey,
        string $entityName,
        string $contextId,
        string $controllerFqcn,
        array  $contextConfig
    ): void {
        $filePath = base_path("routes/{$routeFile}");

        if (!File::exists($filePath)) {
            $this->warn("Archivo no encontrado: routes/{$routeFile}");
            $this->warn("Añade el marcador de inyección y vuelve a ejecutar el comando.");
            return;
        }

        $content = File::get($filePath);

        // ── Idempotencia ──────────────────────────────────────────────────────
        if ($this->blockExists($content, $entityName, $contextKey, $contextId)) {
            $this->line("  routes/{$routeFile}: bloque de '{$entityName}' ya existe. Sin cambios.");
            return;
        }

        // ── Resolver marcador ─────────────────────────────────────────
        $markerKey = $this->resolveMarkerKey($contextKey, $contextId, $routeFile);
        $marker    = "// {{{$markerKey}}}";

        if (!str_contains($content, $marker)) {
            if ($contextKey === 'tenant') {
                // Para tenant específico: crear sección con marcador si no existe
                $content = $this->appendTenantSection($content, $markerKey, $contextConfig);
                if (!str_contains($content, $marker)) {
                    $this->warn("No se pudo crear el marcador '{$marker}' en routes/{$routeFile}.");
                    return;
                }
            } else {
                $this->warn("Marcador '{$marker}' no encontrado en routes/{$routeFile}.");
                $this->warn("Añade '    {$marker}' dentro del grupo correspondiente y vuelve a ejecutar.");
                return;
            }
        }

        // ── Detectar indentación del marcador ─────────────────────────────────
        $indent = $this->detectIndentation($content, $marker);

        // ── Resolver prefijos de ruta ─────────────────────────────────────────
        [$routePrefix, $routeName, $permissionPrefix] = $this->resolveRoutePrefixes(
            $contextKey,
            $contextConfig,
            $routeFile,
            $entityName
        );

        // ── Construir bloque ──────────────────────────────────────────────────
        $block = $this->buildBlock(
            entityName:       $entityName,
            contextId:        $contextId ?: ucfirst($contextKey),
            controllerFqcn:   $controllerFqcn,
            middleware:       $contextConfig['route_middleware'] ?? [],
            routePrefix:      $routePrefix,
            permissionPrefix: $permissionPrefix,
            indent:           $indent
        );

        // ── Añadir use statement si falta ─────────────────────────────────────
        $this->ensureUseStatement($content, $controllerFqcn);

        // ── Inyectar antes del marcador ───────────────────────────────────────
        // Reemplaza la línea entera del marcador con: bloque + salto + marcador
        $markerLine  = $indent . $marker;
        $replacement = $block . PHP_EOL . PHP_EOL . $markerLine;
        $content     = str_replace($markerLine, $replacement, $content);

        File::put($filePath, $content);
        $this->info("✅ Rutas de '{$entityName}' inyectadas en routes/{$routeFile} [{$marker}]");
    }

    // ─── Construcción del bloque CRUD ─────────────────────────────────────────

    /**
     * Construye el bloque de rutas CRUD siguiendo la estructura del WORKPLAN.
     *
     * Si route_middleware está vacío, omite completamente ->middleware($middleware)
     * para que la seguridad sea heredada del grupo padre en el archivo de rutas.
     *
     * @param  string  $entityName       Nombre de la entidad (StudlyCase)
     * @param  string  $contextId        ID del contexto (ej: central, clinic-one)
     * @param  string  $controllerFqcn   FQCN del controlador
     * @param  array   $middleware        Array de middlewares
     * @param  string  $routePrefix       Prefijo de ruta calculado
     * @param  string  $permissionPrefix  Prefijo de permisos
     * @param  string  $indent            Indentación detectada del marcador
     * @return string
     */
    private function buildBlock(
        string $entityName,
        string $contextId,
        string $controllerFqcn,
        array  $middleware,
        string $routePrefix,
        string $permissionPrefix,
        string $indent
    ): string {
        $controllerClass = class_basename($controllerFqcn);
        $i               = $indent;
        $i2              = $indent . '    ';

        $lines = [];

        // ── Comentario de origen ──────────────────────────────────────────────
        $lines[] = "{$i}// Bloque generado para: {$entityName} (Contexto: {$contextId})";

        // ── Variables de configuración ────────────────────────────────────────
        $lines[] = "{$i}\$routePrefix = '{$routePrefix}';";

        if ($permissionPrefix !== '') {
            $lines[] = "{$i}\$permissionPrefix = '{$permissionPrefix}';";
        }

        // Middleware solo si no está vacío (agnosticismo de seguridad)
        $hasMiddleware = !empty($middleware);
        if ($hasMiddleware) {
            $lines[] = "{$i}\$middleware = {$this->formatMiddlewareArray($middleware)};";
        }

        $lines[] = '';

        // ── Apertura del grupo Route ──────────────────────────────────────────
        $lines[] = "{$i}Route::prefix(\$routePrefix)";
        $lines[] = "{$i}    ->name(\$routePrefix . '.')";

        // Solo incluir ->middleware() si hay middlewares definidos
        if ($hasMiddleware) {
            $lines[] = "{$i}    ->middleware(\$middleware)";
        }

        $groupCallback = $permissionPrefix !== ''
            ? "function () use (\$permissionPrefix)"
            : "function ()";

        $lines[] = "{$i}    ->group({$groupCallback} {";

        // ── Rutas CRUD ────────────────────────────────────────────────────────
        $lines[] = "{$i2}Route::get('/', [{$controllerClass}::class, 'index'])->name('index');";
        $lines[] = "{$i2}Route::get('/create', [{$controllerClass}::class, 'create'])->name('create');";
        $lines[] = "{$i2}Route::post('/', [{$controllerClass}::class, 'store'])->name('store');";
        $lines[] = "{$i2}Route::get('/{id}/edit', [{$controllerClass}::class, 'edit'])->name('edit');";
        $lines[] = "{$i2}Route::put('/{id}', [{$controllerClass}::class, 'update'])->name('update');";
        $lines[] = "{$i2}Route::delete('/{id}', [{$controllerClass}::class, 'destroy'])->name('destroy');";

        $lines[] = "{$i}});";

        return implode(PHP_EOL, $lines);
    }

    // ─── Resolvers ────────────────────────────────────────────────────────────

    /**
     * Resuelve la clave del marcador según el contexto y el archivo destino.
     *
     * Tabla de resolución:
     *   central + web.php          → CENTRAL_ROUTES_END
     *   shared  + web.php          → CENTRAL_ROUTES_END  (Shared usa sección central en web)
     *   shared  + tenant.php       → TENANT_SHARED_ROUTES_END
     *   tenant_shared + tenant.php → TENANT_SHARED_ROUTES_END
     *   tenant  + tenant.php       → TENANT_{ID}_ROUTES_END
     *
     * @param  string  $contextKey   Clave del contexto
     * @param  string  $contextId    ID del contexto (ej: clinic-one)
     * @param  string  $routeFile    Archivo destino
     * @return string
     */
    private function resolveMarkerKey(string $contextKey, string $contextId, string $routeFile): string
    {
        return match (true) {
            $contextKey === 'central'                                => 'CENTRAL_ROUTES_END',
            $contextKey === 'shared' && $routeFile === 'web.php'    => 'CENTRAL_ROUTES_END',
            $contextKey === 'shared' && $routeFile === 'tenant.php' => 'TENANT_SHARED_ROUTES_END',
            $contextKey === 'tenant_shared'                          => 'TENANT_SHARED_ROUTES_END',
            $contextKey === 'tenant'
                => 'TENANT_' . strtoupper(Str::snake(Str::studly($contextId))) . '_ROUTES_END',
            default => 'ROUTES_END',
        };
    }

    /**
     * Resuelve los tres prefijos de ruta para el bloque a inyectar.
     * Para el contexto Shared aplica los prefijos diferenciados según el archivo destino.
     *
     * @param  string  $contextKey    Clave del contexto
     * @param  array   $contextConfig Configuración del contexto
     * @param  string  $routeFile     Archivo destino (para diferenciar Shared)
     * @param  string  $entityName    Nombre de la entidad
     * @return array{0: string, 1: string, 2: string}  [routePrefix, routeName, permissionPrefix]
     */
    private function resolveRoutePrefixes(
        string $contextKey,
        array  $contextConfig,
        string $routeFile,
        string $entityName
    ): array {
        $functionality = Str::kebab(Str::plural(Str::snake($entityName)));

        if ($contextKey === 'shared') {
            // Prefijos diferenciados: central.shared-X en web.php, tenant.shared-X en tenant.php
            if ($routeFile === 'web.php') {
                $base = $contextConfig['web_route_prefix'] ?? ($contextConfig['route_prefix'] ?? 'shared');
                $name = $contextConfig['web_route_name']   ?? ($contextConfig['route_name']   ?? 'shared.');
            } else {
                $base = $contextConfig['tenant_route_prefix'] ?? ($contextConfig['route_prefix'] ?? 'shared');
                $name = $contextConfig['tenant_route_name']   ?? ($contextConfig['route_name']   ?? 'shared.');
            }
        } else {
            $base = $contextConfig['route_prefix'] ?? Str::kebab($contextKey);
            $name = $contextConfig['route_name']   ?? (Str::kebab($contextKey) . '.');
        }

        return [
            $base . '-' . $functionality,           // routePrefix
            $name . $functionality . '.',            // routeName  (para referencia; Route::prefix lo genera)
            $contextConfig['permission_prefix'] ?? '',  // permissionPrefix
        ];
    }

    // ─── Helpers de archivo ───────────────────────────────────────────────────

    /**
     * Detecta la indentación (espacios/tabs) de la línea que contiene el marcador.
     *
     * @param  string  $content  Contenido del archivo
     * @param  string  $marker   Marcador a buscar (ej: // {{CENTRAL_ROUTES_END}})
     * @return string  Cadena de espacios o tabs detectada
     */
    private function detectIndentation(string $content, string $marker): string
    {
        foreach (explode(PHP_EOL, $content) as $line) {
            if (str_contains($line, $marker)) {
                preg_match('/^(\s*)/', $line, $matches);
                return $matches[1] ?? '';
            }
        }
        return '    '; // Fallback: 4 espacios
    }

    /**
     * Añade el `use ControllerFqcn;` al archivo si no existe.
     * Busca el último `use` existente e inserta justo después.
     *
     * @param  string  $content        Contenido del archivo (por referencia)
     * @param  string  $controllerFqcn FQCN completo del controlador
     * @return void
     */
    private function ensureUseStatement(string &$content, string $controllerFqcn): void
    {
        $useStatement = "use {$controllerFqcn};";

        if (str_contains($content, $useStatement)) {
            return;
        }

        // Insertar después del último `use ...;` existente
        if (preg_match_all('/^use [^;]+;/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch  = end($matches[0]);
            $insertPos  = $lastMatch[1] + strlen($lastMatch[0]);
            $content    = substr($content, 0, $insertPos)
                . PHP_EOL . $useStatement
                . substr($content, $insertPos);
            return;
        }

        // Sin use statements: insertar después de declare(strict_types=1); o de <?php
        if (str_contains($content, 'declare(strict_types=1);')) {
            $content = str_replace(
                "declare(strict_types=1);",
                "declare(strict_types=1);" . PHP_EOL . PHP_EOL . $useStatement,
                $content
            );
            return;
        }

        $content = preg_replace('/^(<\?php\s*)/m', "$1" . PHP_EOL . $useStatement . PHP_EOL, $content, 1);
    }

    /**
     * Verifica si ya existe un bloque para esta entidad+contexto (garantiza idempotencia).
     * Busca el comentario cabecera que el propio servicio genera.
     *
     * @param  string  $content      Contenido del archivo
     * @param  string  $entityName   Nombre de la entidad
     * @param  string  $contextKey   Clave del contexto
     * @param  string  $contextId    ID del contexto
     * @return bool
     */
    private function blockExists(
        string $content,
        string $entityName,
        string $contextKey,
        string $contextId
    ): bool {
        $contextLabel = $contextId ?: ucfirst($contextKey);
        $signature    = "// Bloque generado para: {$entityName} (Contexto: {$contextLabel})";
        return str_contains($content, $signature);
    }

    /**
     * Añade al final del archivo una nueva sección de tenant con su marcador.
     * Solo se invoca para contextos 'tenant' específicos cuando el marcador no existe.
     *
     * @param  string  $content     Contenido actual del archivo
     * @param  string  $markerKey   Clave del marcador sin llaves
     * @param  array   $contextConfig Configuración del contexto del tenant
     * @return string  Contenido modificado
     */
    private function appendTenantSection(string $content, string $markerKey, array $contextConfig): string
    {
        $marker     = '// {{' . $markerKey . '}}';
        $middleware = $this->formatMiddlewareArray($contextConfig['route_middleware'] ?? ['web', 'auth', 'tenant']);
        $tenantName = $contextConfig['id'] ?? 'Tenant';
        $separator  = str_repeat('─', 60);

        // Si el archivo termina con un tag de cierre PHP el contenido añadido
        // quedaría fuera del modo PHP y se imprimiría en stdout al bootear. Se elimina.
        $trimmed = rtrim($content);
        if (str_ends_with($trimmed, '?' . '>')) {
            $content = substr($trimmed, 0, -2) . PHP_EOL;
        }

        $section = PHP_EOL
            . PHP_EOL . "// {$separator}"
            . PHP_EOL . "// {$tenantName}"
            . PHP_EOL . "// {$separator}"
            . PHP_EOL . "Route::middleware({$middleware})->group(function () {"
            . PHP_EOL . "    {$marker}"
            . PHP_EOL . "});"
            . PHP_EOL;

        return $content . $section;
    }

    /**
     * Formatea un array de middlewares como string PHP literal.
     * Ej: ['web', 'auth'] → "['web', 'auth']"
     *
     * @param  array  $middleware
     * @return string
     */
    private function formatMiddlewareArray(array $middleware): string
    {
        if (empty($middleware)) {
            return '[]';
        }

        $items = array_map(fn (string $m) => "'{$m}'", $middleware);
        return '[' . implode(', ', $items) . ']';
    }

    // ─── Output ───────────────────────────────────────────────────────────────

    private function info(string $message): void
    {
        $this->output?->info($message);
    }

    private function warn(string $message): void
    {
        $this->output?->warn($message);
    }

    private function line(string $message): void
    {
        $this->output?->line($message);
    }
}
