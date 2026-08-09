import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useModuleContext } from './useModuleContext'

/**
 * usePermissions — decide qué ve el usuario en la pantalla.
 *
 * **De dónde vienen los permisos, de punta a punta.** Conviene leerlo una vez, porque son cuatro
 * piezas y ninguna funciona sin las otras tres:
 *
 *     el seeder los crea   →  el middleware los comparte  →  Inertia los pone en las props
 *     (PermissionsSeeder)     (InnoditeContextBridge)        (auth.permissions)
 *                                                                    ↓
 *                                                      este composable los consulta: can()
 *
 * Si falta cualquiera de las tres primeras, `can()` devuelve `false` para todo y la pantalla carga
 * **perfecta y sin un solo botón** — sin error, sin aviso, para todo el mundo. Es un defecto real y
 * ya ocurrido, y por eso `php artisan innodite:doctor` comprueba justo esas tres condiciones.
 *
 * **Cómo se llama un permiso.** Siempre `{prefijo}_{funcionalidad}_{acción}`, todo con guion bajo:
 *
 *     central_roles_update        un permiso de RUTA — protege el endpoint
 *     central_roles_view_update   un permiso de VISTA — decide si se pinta el botón de editar
 *
 * Los emite `SubFeaturePermissions` en el lado PHP, que es de donde los toma también el seeder que
 * los crea y la ruta que los exige. En aplicación única el prefijo va vacío: `roles_update`.
 *
 * ⚠️ **No confundir con los nombres de RUTA, que sí llevan punto** (`central.roles.index`). Ese
 * separador es de `useModuleContext().contextRoute()`, no de aquí. Esta función lo usaba, y por eso
 * su segunda comprobación buscaba `central.roles_update`: un nombre que ningún seeder crea nunca.
 *
 * **Vista y ruta son dos capas, y se emiten las dos.** Ocultar un botón no protege el endpoint, y
 * proteger el endpoint no limpia la pantalla.
 *
 * Requisito: `HandleInertiaRequests` debe compartir `auth.permissions` como un array plano de
 * strings. Ejecuta `php artisan innodite:doctor` para verificar el contrato de datos.
 *
 * Uso:
 *   const { can, canAny, canAll } = usePermissions()
 *
 *   can('central_roles_update')                                    // true/false
 *   canAny(['central_roles_update', 'central_roles_destroy'])      // true si tiene alguno
 *   canAll(['central_roles_update', 'central_roles_view_update'])  // true si tiene los dos
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
                    'Ejecuta: php artisan innodite:doctor'
                )
            }
            return []
        }

        return perms
    })

    /**
     * Verifica si el usuario tiene un permiso.
     *
     * Acepta las dos formas de nombrarlo, porque las dos aparecen en una vista real:
     *
     *   can('central_roles_update')  el nombre completo — es lo que escriben las vistas generadas
     *   can('roles_update')          sin el prefijo — se le añade el del contexto activo
     *
     * La segunda existe para el código escrito a mano: un componente que sirve a varios contextos
     * no puede llevar `central_` incrustado. El prefijo lo pone el contexto en tiempo de ejecución.
     *
     * ⚠️ El separador es **guion bajo**, igual que en el lado PHP. Aquí había un punto —
     * `${prefix}.${permission}` —, copiado del formato de los nombres de ruta. Esa comprobación no
     * podía acertar jamás: el paquete emite `central_roles_update` y esto buscaba
     * `central.roles_update`. No dio la cara porque las vistas generadas pasan el nombre completo y
     * aciertan por la primera comprobación; el que se rompía era el código escrito a mano, en
     * silencio y con el botón oculto.
     *
     * @param {string} permission - Nombre del permiso, completo o sin el prefijo del contexto
     * @returns {boolean}
     */
    function can(permission) {
        const list = permissions.value

        if (list.length === 0) return false

        // 1. Tal cual llega — es el caso de las vistas generadas, que pasan el nombre completo
        if (list.includes(permission)) return true

        // 2. Con el prefijo del contexto activo. El `replace` normaliza el prefijo igual que hace
        //    SubFeaturePermissions::permissionName() en PHP: llega como 'central' desde el
        //    contexts.json y como 'central_' desde el modo, y concatenar el segundo sin normalizar
        //    da 'central__roles_update' — guion doble, un permiso que no tiene nadie.
        const prefix = permissionPrefix.value?.replace(/_+$/, '')
        if (prefix && list.includes(`${prefix}_${permission}`)) return true

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
