<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Str;

/**
 * SubFeaturePermissions — todos los permisos de una subfuncionalidad, decididos en un solo sitio.
 *
 * **Por qué existe.** Los permisos de una subfuncionalidad los necesitan **cuatro** sitios distintos:
 * el generador de rutas (para el `->middleware()`), el generador de vistas (para el `can()` de cada
 * botón), el `PermissionsSeeder` (que los crea) y el seeder de webmaster (que los recoge). Cuatro
 * cálculos independientes del mismo conjunto son cuatro oportunidades de que dejen de coincidir, y
 * cuando eso pasa no se rompe nada de forma visible: la pantalla carga bien y simplemente no tiene
 * botones, o el endpoint pide un permiso que nadie creó y responde 403 hasta al administrador.
 *
 * Ya ocurrió, y es el defecto que originó esta clase: los stubs Vue pedían `can('invoices.create')`
 * —con punto, y verbo `create`— mientras las rutas exigían `invoices_store`. Ninguno de los permisos
 * de la vista existía ni iba a existir, así que **todos los botones quedaban ocultos para todo el
 * mundo**, incluido el webmaster.
 *
 * **Las dos protecciones son independientes.** El permiso de **ruta** protege el servicio; el de
 * **vista** protege el elemento visual. Ocultar el botón no protege el endpoint, y proteger el
 * endpoint no limpia la pantalla. Se emiten las dos, siempre — y nunca son el mismo permiso.
 *
 * **Un permiso por ruta y por acción de vista, sin reutilizar ninguno.** Ni siquiera
 * `index` y `list` lo comparten, aunque una alimente a la otra: protegen cosas distintas —la pantalla
 * y sus datos— y cada permiso tiene que poder decirle al usuario, en su `description`, **qué cosa
 * concreta** habilita. Un permiso compartido entre varias acciones deja de ser explicable: quien
 * mantiene el sistema meses después ya no sabe qué bloquea cada cual.
 *
 * **El naming por modo no se decide aquí**: lo responde `ModuleMode::permissionPrefix()`, que existe
 * desde la fase 1. Esta clase recibe el prefijo ya resuelto.
 */
final class SubFeaturePermissions
{
    /**
     * Las rutas que genera el paquete y el permiso que exige cada una.
     *
     * `permission` es el **sufijo**, no el nombre completo: lo compone `permissionName()` con el
     * prefijo del contexto y la clave de la subfuncionalidad.
     *
     * `comment` es el rótulo que se escribe encima de cada ruta en el archivo generado: vive aquí
     * porque describe la ruta, y así añadir una acción es tocar **una** fila.
     *
     * @var array<int, array{route: string, verb: string, uri: string, action: string, permission: string, comment: string}>
     */
    public const ACTIONS = [
        ['route' => 'index',   'verb' => 'GET',    'uri' => '/',     'action' => 'index',   'permission' => 'index',   'comment' => 'Vista principal'],
        ['route' => 'list',    'verb' => 'GET',    'uri' => '/list', 'action' => 'list',    'permission' => 'list',    'comment' => 'Endpoint JSON listado'],
        ['route' => 'store',   'verb' => 'POST',   'uri' => '/',     'action' => 'store',   'permission' => 'store',   'comment' => 'Crear'],
        ['route' => 'show',    'verb' => 'GET',    'uri' => '/{id}', 'action' => 'show',    'permission' => 'show',    'comment' => 'Ver uno'],
        ['route' => 'update',  'verb' => 'PUT',    'uri' => '/{id}', 'action' => 'update',  'permission' => 'update',  'comment' => 'Actualizar'],
        ['route' => 'destroy', 'verb' => 'DELETE', 'uri' => '/{id}', 'action' => 'destroy', 'permission' => 'destroy', 'comment' => 'Eliminar'],
    ];

    /**
     * Las acciones de la interfaz, cada una con su permiso propio.
     *
     * El infijo `view` las separa de las de ruta al leer la lista de permisos, y el verbo se empareja
     * con el del servicio que consume el elemento: el botón `view_store` dispara la ruta `store`. Ese
     * emparejamiento se lee de un vistazo y **no** los convierte en el mismo permiso.
     *
     * `element` es lo que el generador de vistas necesita saber para colocar el `can()`.
     *
     * @var array<int, array{action: string, permission: string, element: string}>
     */
    public const VIEW_ACTIONS = [
        ['action' => 'store',   'permission' => 'view_store',   'element' => 'boton-crear'],
        ['action' => 'show',    'permission' => 'view_show',    'element' => 'enlace-ver'],
        ['action' => 'update',  'permission' => 'view_update',  'element' => 'boton-editar'],
        ['action' => 'destroy', 'permission' => 'view_destroy', 'element' => 'boton-eliminar'],
    ];

    /**
     * Qué permite cada permiso, y qué deja de poder hacerse sin él.
     *
     * Va en la `description`, **en español**, porque es lo único que ve quien asigna permisos a un rol
     * desde la interfaz. Un `description` vacío —o en inglés, o que repita el nombre del permiso—
     * convierte esa pantalla en una lista de claves indescifrables.
     *
     * @var array<string, array{verbo: string, permite: string, bloquea: string}>
     */
    private const SEMANTICS = [
        // ── Permisos de RUTA: protegen el servicio ────────────────────────────────────────────
        'index'        => [
            'verbo'   => 'Acceder a la pantalla de',
            'permite' => 'que la opción aparezca en el menú y que la pantalla se pueda abrir',
            'bloquea' => 'la opción no se ve en el menú y la pantalla no se abre',
        ],
        'list'         => [
            'verbo'   => 'Consultar los registros de',
            'permite' => 'ver el listado de registros dentro de la pantalla',
            'bloquea' => 'la pantalla se abre pero no muestra ningún registro',
        ],
        'store'        => [
            'verbo'   => 'Registrar',
            'permite' => 'guardar registros nuevos',
            'bloquea' => 'no se pueden guardar registros nuevos',
        ],
        'show'         => [
            'verbo'   => 'Ver el detalle de',
            'permite' => 'consultar un registro concreto con todos sus datos',
            'bloquea' => 'no se puede consultar el detalle de ningún registro',
        ],
        'update'       => [
            'verbo'   => 'Modificar',
            'permite' => 'guardar cambios sobre registros existentes',
            'bloquea' => 'no se pueden guardar cambios',
        ],
        'destroy'      => [
            'verbo'   => 'Eliminar',
            'permite' => 'dar de baja registros',
            'bloquea' => 'no se puede eliminar ningún registro',
        ],

        // ── Permisos de VISTA: protegen el elemento visual ──────────────────────────────
        'view_store'   => [
            'verbo'   => 'Ver el botón de crear en',
            'permite' => 'que el botón «Crear» se muestre en la pantalla',
            'bloquea' => 'el botón no aparece y no se puede iniciar el alta',
        ],
        'view_show'    => [
            'verbo'   => 'Ver el enlace de detalle en',
            'permite' => 'que el enlace para abrir un registro se muestre en cada fila',
            'bloquea' => 'el enlace no aparece en ninguna fila',
        ],
        'view_update'  => [
            'verbo'   => 'Ver el botón de editar en',
            'permite' => 'que el botón «Editar» se muestre en cada fila',
            'bloquea' => 'el botón no aparece y no se puede iniciar la edición',
        ],
        'view_destroy' => [
            'verbo'   => 'Ver el botón de eliminar en',
            'permite' => 'que el botón «Eliminar» se muestre en cada fila',
            'bloquea' => 'el botón no aparece y no se puede iniciar la baja',
        ],
    ];

    /**
     * La clave de la subfuncionalidad dentro del nombre del permiso.
     *
     * Convierte `user-management` en `user_management`: el nombre del permiso va en `snake_case`
     * entero, con la funcionalidad en medio.
     */
    public static function key(string $functionality): string
    {
        return Str::snake(str_replace('-', '_', $functionality));
    }

    /**
     * El nombre completo de un permiso: `{prefijo}_{funcionalidad}_{sufijo}`.
     *
     * **Dos detalles que aquí se normalizan, y que fuera de aquí producirían dos permisos distintos
     * para la misma cosa:**
     *
     *   - En single-app el prefijo llega **vacío**, y el nombre queda `{funcionalidad}_{sufijo}` — sin
     *     el guion bajo suelto delante que produciría concatenar sin comprobar.
     *   - El prefijo llega escrito de **dos formas** según quién lo dé: `contexts.json` lo entrega como
     *     `'central'` y `ModuleMode::permissionPrefix()` como `'central_'`, con guion bajo final.
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
     * Los elementos de la interfaz, cada uno con el permiso que decide si se muestra.
     *
     * Lo lee el generador de vistas para escribir el `can()` de cada botón. Sin permiso el elemento
     * **se oculta**, no se muestra deshabilitado.
     *
     * @return array<string, string>  elemento => nombre completo del permiso
     */
    public static function viewElements(string $permPrefix, string $functionality): array
    {
        $elementos = [];

        foreach (self::VIEW_ACTIONS as $accion) {
            $elementos[$accion['element']] = self::permissionName($permPrefix, $functionality, $accion['permission']);
        }

        return $elementos;
    }

    /**
     * **Todos** los permisos que hay que crear: los de ruta y los de vista.
     *
     * Lo lee el `PermissionsSeeder`. Son uno por ruta y uno por acción de la interfaz —no se admite
     * reutilizar ninguno—, y la prueba que lo vigila exige que no haya repetidos.
     *
     * @return array<int, array{name: string, description: string, module: string, kind: string}>
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
            $permisos[] = self::describe($permPrefix, $functionality, $accion['permission'], $etiqueta, $grupo, 'ruta');
        }

        foreach (self::VIEW_ACTIONS as $accion) {
            $permisos[] = self::describe($permPrefix, $functionality, $accion['permission'], $etiqueta, $grupo, 'vista');
        }

        return $permisos;
    }

    /**
     * Compone un permiso con su descripción en español: qué permite, dónde y qué bloquea.
     *
     * @return array{name: string, description: string, module: string, kind: string}
     */
    private static function describe(
        string $permPrefix,
        string $functionality,
        string $suffix,
        string $etiqueta,
        string $grupo,
        string $kind
    ): array {
        $semantica = self::SEMANTICS[$suffix];

        return [
            'name'        => self::permissionName($permPrefix, $functionality, $suffix),
            'description' => sprintf(
                '%s %s. Permite %s. Sin este permiso, %s.',
                $semantica['verbo'],
                $etiqueta,
                $semantica['permite'],
                $semantica['bloquea']
            ),
            'module'      => $grupo,
            'kind'        => $kind,
        ];
    }

    /**
     * El grupo con el que la interfaz agrupa estos permisos: `"{Módulo} - {SubFuncionalidad}"`.
     *
     * Es lo que produce el acordeón por funcionalidad con una pestaña por subfuncionalidad, en
     * vez de una lista plana de doscientos permisos sueltos.
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
