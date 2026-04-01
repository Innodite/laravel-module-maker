import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useModuleContext } from './useModuleContext'

/**
 * usePermissions — Composable de permisos Innodite v3.0.0
 *
 * Valida permisos con estrategia dual:
 *   1. Permiso con prefijo de contexto activo: `{prefix}.{permission}` → 'central.roles.edit'
 *   2. Permiso plano sin prefijo:              `{permission}`          → 'roles.edit'
 *
 * Ambas verificaciones se realizan siempre. Retorna `true` si el usuario
 * tiene cualquiera de las dos variantes.
 *
 * Requisito: `HandleInertiaRequests` debe compartir `auth.permissions`
 * como un array plano de strings. Ejecuta `php artisan innodite:check-env`
 * para verificar el contrato de datos.
 *
 * Uso:
 *   const { can, canAny, canAll } = usePermissions()
 *
 *   can('roles.edit')                        // true/false
 *   canAny(['roles.edit', 'roles.create'])   // true si tiene alguno
 *   canAll(['roles.edit', 'roles.delete'])   // true si tiene todos
 */
export function usePermissions() {
    const page = usePage()
    const { permissionPrefix } = useModuleContext()

    /**
     * Array reactivo de permisos del usuario autenticado.
     * Emite warning en DEV si `auth.permissions` no está disponible.
     */
    const permissions = computed(() => {
        const perms = page.props?.auth?.permissions

        if (!Array.isArray(perms)) {
            if (import.meta.env?.DEV) {
                console.warn(
                    '[InnoditeContextBridge] auth.permissions no está disponible en las props de Inertia.\n' +
                    'Verifica que HandleInertiaRequests comparte el nodo auth.permissions como array.\n' +
                    'Ejecuta: php artisan innodite:check-env'
                )
            }
            return []
        }

        return perms
    })

    /**
     * Verifica si el usuario tiene un permiso (estrategia dual).
     *
     * Dada una solicitud de permiso `roles.edit` con prefijo `central`:
     *   ✓ Acepta `central.roles.edit`  (permiso con contexto)
     *   ✓ Acepta `roles.edit`          (permiso plano / shared)
     *   ✗ Rechaza cualquier otra variante
     *
     * @param {string} permission - Nombre del permiso sin prefijo (ej: 'roles.edit')
     * @returns {boolean}
     */
    function can(permission) {
        const list = permissions.value

        if (list.length === 0) return false

        // 1. Verificar permiso plano (siempre)
        if (list.includes(permission)) return true

        // 2. Verificar permiso con prefijo de contexto (si está disponible)
        const prefix = permissionPrefix.value
        if (prefix && list.includes(`${prefix}.${permission}`)) return true

        return false
    }

    /**
     * Retorna `true` si el usuario tiene AL MENOS UNO de los permisos dados.
     *
     * @param {string[]} permissionList
     * @returns {boolean}
     */
    function canAny(permissionList) {
        return permissionList.some(p => can(p))
    }

    /**
     * Retorna `true` si el usuario tiene TODOS los permisos dados.
     *
     * @param {string[]} permissionList
     * @returns {boolean}
     */
    function canAll(permissionList) {
        return permissionList.every(p => can(p))
    }

    return {
        permissions,
        permissionPrefix,
        can,
        canAny,
        canAll,
    }
}
