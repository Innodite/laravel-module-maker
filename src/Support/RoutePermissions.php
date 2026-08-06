<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Str;

/**
 * RoutePermissions — qué rutas se generan y qué permiso protege cada una, decidido en un solo sitio.
 *
 * **Por qué existe.** El generador de rutas escribe el `middleware('...:central_invoices_index')` y el
 * `PermissionsSeeder` tiene que crear exactamente ese permiso. Son **dos mitades del mismo conjunto**:
 * si cada una lo calcula por su cuenta, el día que cambie una acción el seeder creará un permiso que
 * ninguna ruta pide, o —peor— la ruta pedirá uno que nadie creó y la pantalla dará 403 para todo el
 * mundo, incluido el administrador.
 *
 * Esa forma de fallo ya costó ocho defectos en las fases 1 y 2 de esta reescritura: el stub y el
 * generador escribiendo la misma columna, la lista y la carpeta, el marcador y el inyector. Ninguno es
 * un error de lógica, y por eso una revisión de código los lee como correctos. Aquí se corta antes de
 * nacer: **una fuente, dos lectores**.
 *
 * **Lo que NO se puede deducir «a ojo», y es la razón concreta de esta clase:**
 *
 *   - Son **6 rutas pero 5 permisos**: `index` y `list` comparten permiso, porque la pantalla y el
 *     endpoint que la alimenta son la misma autorización — quien puede ver el listado puede pedir sus
 *     datos.
 *   - La ruta se llama `destroy` y su permiso termina en **`_delete`**. El nombre del método del
 *     controlador y el verbo del permiso no coinciden, y nunca lo han hecho.
 *
 * Un segundo cálculo independiente acertaría las cuatro filas obvias y fallaría estas dos.
 *
 * **El naming por modo no se decide aquí**: lo responde `ModuleMode::permissionPrefix()`, que ya
 * existe desde la fase 1. Esta clase recibe el prefijo ya resuelto.
 */
final class RoutePermissions
{
    /**
     * Las rutas que genera el paquete y el permiso que exige cada una.
     *
     * `permission` es el **sufijo** del permiso, no su nombre completo: el nombre lo compone
     * `permissionName()` con el prefijo del contexto y la clave de la subfuncionalidad.
     *
     * `comment` es el rótulo que el generador escribe encima de cada ruta en el archivo de rutas:
     * vive aquí porque describe la ruta, y así añadir una acción es tocar **una** fila.
     *
     * @var array<int, array{route: string, verb: string, uri: string, action: string, permission: string, comment: string}>
     */
    public const ACTIONS = [
        ['route' => 'index',   'verb' => 'GET',    'uri' => '/',     'action' => 'index',   'permission' => 'index',  'comment' => 'Vista principal'],
        ['route' => 'list',    'verb' => 'GET',    'uri' => '/list', 'action' => 'list',    'permission' => 'index',  'comment' => 'Endpoint JSON listado'],
        ['route' => 'store',   'verb' => 'POST',   'uri' => '/',     'action' => 'store',   'permission' => 'store',  'comment' => 'Crear'],
        ['route' => 'show',    'verb' => 'GET',    'uri' => '/{id}', 'action' => 'show',    'permission' => 'show',   'comment' => 'Ver uno'],
        ['route' => 'update',  'verb' => 'PUT',    'uri' => '/{id}', 'action' => 'update',  'permission' => 'update', 'comment' => 'Actualizar'],
        ['route' => 'destroy', 'verb' => 'DELETE', 'uri' => '/{id}', 'action' => 'destroy', 'permission' => 'delete', 'comment' => 'Eliminar'],
    ];

    /**
     * Qué permite cada permiso, qué pantalla protege y qué deja de poder hacerse sin él.
     *
     * Las tres cosas van en la `description`, **en español**, porque esa descripción es lo único que
     * ve quien asigna permisos a un rol desde la interfaz. Un `description` vacío —o en inglés, o que
     * repita el nombre del permiso— convierte esa pantalla en una lista de claves indescifrables.
     *
     * @var array<string, array{verbo: string, permite: string, bloquea: string}>
     */
    private const SEMANTICS = [
        'index'  => [
            'verbo'   => 'Ver el listado de',
            'permite' => 'abrir la pantalla y consultar sus registros',
            'bloquea' => 'no se puede abrir la pantalla ni consultar sus datos',
        ],
        'store'  => [
            'verbo'   => 'Registrar',
            'permite' => 'crear registros nuevos',
            'bloquea' => 'no se pueden crear registros',
        ],
        'show'   => [
            'verbo'   => 'Ver el detalle de',
            'permite' => 'abrir un registro concreto y ver todos sus datos',
            'bloquea' => 'no se puede abrir el detalle de ningún registro',
        ],
        'update' => [
            'verbo'   => 'Modificar',
            'permite' => 'editar registros existentes',
            'bloquea' => 'no se puede modificar ningún registro',
        ],
        'delete' => [
            'verbo'   => 'Eliminar',
            'permite' => 'dar de baja registros',
            'bloquea' => 'no se puede eliminar ningún registro',
        ],
    ];

    /**
     * La clave de la subfuncionalidad dentro del nombre del permiso.
     *
     * Es lo que convierte `user-management` en `user_management`: el nombre del permiso va en
     * `snake_case` entero, con la funcionalidad en medio.
     */
    public static function key(string $functionality): string
    {
        return Str::snake(str_replace('-', '_', $functionality));
    }

    /**
     * El nombre completo de un permiso: `{prefijo}_{funcionalidad}_{sufijo}`.
     *
     * **Dos detalles que aquí se normalizan, y que fuera de aquí producirían dos permisos distintos
     * para la misma ruta:**
     *
     *   - En single-app el prefijo llega **vacío**, y el nombre queda `{funcionalidad}_{sufijo}` — sin
     *     el guion bajo suelto delante que produciría concatenar sin comprobar.
     *   - El prefijo llega escrito de **dos formas** según quién lo dé: `contexts.json` lo entrega
     *     como `'central'` y `ModuleMode::permissionPrefix()` como `'central_'`, con guion bajo final.
     *     Concatenar el segundo sin normalizar da `central__invoices_index`, con guion doble: un
     *     permiso que nadie tiene y una pantalla que nadie abre.
     */
    public static function permissionName(string $permPrefix, string $functionality, string $suffix): string
    {
        $key    = self::key($functionality);
        $prefix = trim($permPrefix, '_');

        return $prefix === ''
            ? "{$key}_{$suffix}"
            : "{$prefix}_{$key}_{$suffix}";
    }

    /**
     * Las rutas generadas, cada una con el permiso que la protege.
     *
     * Lo lee el generador de rutas para escribir sus `middleware()`.
     *
     * @return array<int, array{route: string, verb: string, uri: string, action: string, permission: string, comment: string}>
     */
    public static function routes(string $permPrefix, string $functionality): array
    {
        return array_map(
            fn (array $a): array => [
                ...$a,
                'permission' => self::permissionName($permPrefix, $functionality, $a['permission']),
            ],
            self::ACTIONS
        );
    }

    /**
     * Los permisos **únicos** que hay que crear, con su descripción y su grupo de la interfaz.
     *
     * Lo lee el `PermissionsSeeder`. Son cinco y no seis: `index` y `list` comparten permiso, así que
     * pasar por `routes()` y sembrar una fila por ruta crearía el mismo permiso dos veces.
     *
     * @return array<int, array{name: string, description: string, module: string}>
     */
    public static function permissions(
        string $permPrefix,
        string $functionality,
        string $module,
        string $subFeature
    ): array {
        $etiqueta = self::label($subFeature);
        $grupo    = self::moduleLabel($module, $subFeature);
        $permisos = [];

        foreach (self::ACTIONS as $accion) {
            $nombre = self::permissionName($permPrefix, $functionality, $accion['permission']);

            // index y list comparten permiso: se siembra una vez.
            if (isset($permisos[$nombre])) {
                continue;
            }

            $semantica = self::SEMANTICS[$accion['permission']];

            $permisos[$nombre] = [
                'name'        => $nombre,
                'description' => sprintf(
                    '%s %s. Permite %s. Sin este permiso, %s.',
                    $semantica['verbo'],
                    $etiqueta,
                    $semantica['permite'],
                    $semantica['bloquea']
                ),
                'module'      => $grupo,
            ];
        }

        return array_values($permisos);
    }

    /**
     * El grupo con el que la interfaz agrupa estos permisos: `"{Módulo} - {SubFuncionalidad}"`.
     *
     * Es lo que produce una pestaña por subfuncionalidad en la pantalla de roles, en vez de una lista
     * plana de doscientos permisos sueltos.
     */
    public static function moduleLabel(string $module, string $subFeature): string
    {
        return self::label($module) . ' - ' . self::label($subFeature);
    }

    /**
     * Un nombre técnico convertido en algo legible: `user-management` → `User Management`.
     */
    private static function label(string $value): string
    {
        return Str::headline(str_replace(['-', '_'], ' ', $value));
    }
}
