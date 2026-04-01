import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/**
 * useModuleContext — Composable de contexto de módulo Innodite v3.0.0
 *
 * Expone el prefijo de ruta activo y la función `contextRoute()` para
 * construir rutas conscientes del contexto actual (Central, Shared, Tenant).
 *
 * Requisito: El middleware InnoditeContextBridge debe estar registrado
 * en el stack web para que auth.context esté disponible en las props.
 *
 * Uso:
 *   const { contextRoute, routePrefix } = useModuleContext()
 *
 *   // En el panel Central  → route(contextRoute('roles.index')) = 'central.roles.index'
 *   // En Tenant One        → route(contextRoute('roles.index')) = 'tenant-one.roles.index'
 *   // Sin contexto         → route(contextRoute('roles.index')) = 'roles.index'
 */
export function useModuleContext() {
    const page = usePage()

    /** Prefijo de ruta del contexto activo (ej: 'central', 'tenant-one'). */
    const routePrefix = computed(() => page.props?.auth?.context?.route_prefix ?? null)

    /** Prefijo de permisos del contexto activo (ej: 'central', 'tenant_one'). */
    const permissionPrefix = computed(() => page.props?.auth?.context?.permission_prefix ?? null)

    /** Objeto completo de contexto compartido por el middleware. */
    const context = computed(() => page.props?.auth?.context ?? {
        route_prefix: null,
        permission_prefix: null,
    })

    /**
     * Construye el nombre de ruta con el prefijo del contexto activo.
     *
     * Si `auth.context.route_prefix` no está disponible (middleware no registrado),
     * emite un warning en consola (solo en desarrollo) y retorna el nombre tal cual.
     *
     * @param {string} name - Nombre base de la ruta (ej: 'roles.index')
     * @returns {string}    - Nombre con prefijo (ej: 'central.roles.index')
     */
    function contextRoute(name) {
        if (!routePrefix.value) {
            if (import.meta.env?.DEV) {
                console.warn(
                    '[InnoditeContextBridge] auth.context.route_prefix no está disponible en las props de Inertia.\n' +
                    'Asegúrate de haber registrado InnoditeContextBridge en el stack middleware web.\n' +
                    'Ejecuta: php artisan innodite:check-env'
                )
            }
            return name
        }
        return `${routePrefix.value}.${name}`
    }

    return {
        context,
        routePrefix,
        permissionPrefix,
        contextRoute,
    }
}
