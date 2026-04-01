<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Innodite\LaravelModuleMaker\Contracts\InnoditeUserPermissions;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * InnoditeContextBridge — Middleware de sincronización Frontend-Backend
 *
 * Intercepta la solicitud e inyecta vía Inertia::share() los datos de
 * contexto y permisos necesarios para que los composables de Vue 3
 * funcionen de forma dinámica y consciente del contexto.
 *
 * Datos compartidos:
 *   auth.context.route_prefix      → Prefijo de ruta activo ('central', 'tenant-one')
 *   auth.context.permission_prefix → Prefijo de permisos ('central', 'tenant_one')
 *   auth.permissions               → Array plano de strings con permisos del usuario
 *
 * Cadena de resolución de permisos:
 *   1. Spatie\Permission → $user->getAllPermissions()->pluck('name')
 *   2. InnoditeUserPermissions → $user->getInnoditePermissions()
 *   3. Fail-safe → [] + Warning en log
 *
 * Registro en bootstrap/app.php (Laravel 11+):
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->appendToGroup('web', [
 *           \Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge::class,
 *       ]);
 *   })
 */
class InnoditeContextBridge
{
    public function handle(Request $request, Closure $next): Response
    {
        if (class_exists(\Inertia\Inertia::class)) {
            \Inertia\Inertia::share([
                'auth' => array_merge(
                    (array) (\Inertia\Inertia::getShared('auth') ?? []),
                    [
                        'context'     => $this->resolveContext($request),
                        'permissions' => $this->resolvePermissions($request),
                    ]
                ),
            ]);
        }

        return $next($request);
    }

    // ─── Resolución de contexto ───────────────────────────────────────────────

    /**
     * Detecta el contexto activo comparando la ruta actual contra todos
     * los contextos definidos en contexts.json.
     *
     * Estrategia de coincidencia (por prioridad):
     *   1. Nombre de ruta con el route_name del contexto
     *   2. Path de la URL con el route_prefix del contexto
     *
     * @return array{route_prefix: string|null, permission_prefix: string|null}
     */
    private function resolveContext(Request $request): array
    {
        $empty = ['route_prefix' => null, 'permission_prefix' => null];

        try {
            $allContexts = ContextResolver::all();
        } catch (\Throwable) {
            return $empty;
        }

        $currentRouteName = $request->route()?->getName() ?? '';
        $currentPath      = ltrim($request->path(), '/');

        foreach ($allContexts as $items) {
            foreach ($items as $item) {
                // Coincidencia por nombre de ruta
                $routeName = $item['route_name'] ?? null;
                if ($routeName && str_starts_with($currentRouteName, $routeName)) {
                    return [
                        'route_prefix'      => $item['route_prefix'] ?? null,
                        'permission_prefix' => $item['permission_prefix'] ?? null,
                    ];
                }

                // Coincidencia por path URL
                $routePrefix = $item['route_prefix'] ?? null;
                if ($routePrefix && str_starts_with($currentPath, $routePrefix)) {
                    return [
                        'route_prefix'      => $routePrefix,
                        'permission_prefix' => $item['permission_prefix'] ?? null,
                    ];
                }
            }
        }

        return $empty;
    }

    // ─── Resolución de permisos ───────────────────────────────────────────────

    /**
     * Resuelve los permisos del usuario autenticado mediante cadena de detección.
     *
     * @return array<string>
     */
    private function resolvePermissions(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        // Cadena 1: Spatie Permission (método getAllPermissions disponible)
        if (method_exists($user, 'getAllPermissions')) {
            return $user->getAllPermissions()->pluck('name')->toArray();
        }

        // Cadena 2: Interfaz InnoditeUserPermissions
        if ($user instanceof InnoditeUserPermissions) {
            return $user->getInnoditePermissions();
        }

        // Cadena 3: Fail-safe — sin romper la aplicación
        Log::warning(
            '[InnoditeContextBridge] No se pueden resolver los permisos del usuario. '
            . 'Instala spatie/laravel-permission o implementa InnoditeUserPermissions en el modelo User. '
            . 'Ejecuta: php artisan innodite:check-env',
            [
                'user_id' => $user->getKey(),
                'model'   => get_class($user),
            ]
        );

        return [];
    }
}
