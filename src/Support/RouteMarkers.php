<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Str;

/**
 * RouteMarkers — dónde se inserta el siguiente bloque de rutas, decidido en un solo sitio.
 *
 * Un archivo de rutas generado lleva un comentario al final de cada sección:
 *
 *     // {{CENTRAL_ROUTES_END}}
 *
 * No es un placeholder —no se sustituye al generar—: **sobrevive en el proyecto** para que la
 * siguiente subfuncionalidad sepa dónde añadirse, dentro del grupo que le da dominio y middleware y
 * no al final del archivo.
 *
 * **Por qué existe esta clase.** El marcador lo escribe un lado y lo busca otro: el generador cuando
 * crea el archivo, y el inyector cuando ese archivo ya existe. Los dos lo componían por su cuenta —y
 * no coincidían: uno escribía `{{CENTRAL_END}}` y el otro buscaba `CENTRAL_ROUTES_END`.
 *
 * El síntoma de ese desacuerdo no es un error. Es que el inyector **no encuentra** el marcador y
 * añade la sección al final del archivo, fuera del grupo: rutas sin el dominio que las acota y sin
 * el middleware que las protege, en un archivo que parsea perfectamente. Es la misma familia de
 * defectos que `SeederNames`, `TestNames` y `RequestNames` ya cerraron para sus nombres.
 */
final class RouteMarkers
{
    /**
     * La clave del marcador de una sección, sin las llaves.
     *
     * La tabla depende de las dos coordenadas que deciden dónde vive un bloque: **qué contexto** lo
     * escribe y **en qué archivo**. `shared` necesita las dos porque es el único que vive en los dos
     * lados, y sus dos bloques no pueden compartir marcador — se pisarían.
     */
    public static function key(string $contextKey, string $routeFile, string $contextId = ''): string
    {
        return match (true) {
            $contextKey === 'central'                               => 'CENTRAL_ROUTES_END',
            $contextKey === 'shared' && $routeFile === 'web.php'    => 'CENTRAL_ROUTES_END',
            $contextKey === 'shared' && $routeFile === 'tenant.php' => 'TENANT_SHARED_ROUTES_END',
            $contextKey === 'tenant_shared'                         => 'TENANT_SHARED_ROUTES_END',
            $contextKey === 'tenant' && $contextId !== ''
                => 'TENANT_' . strtoupper(Str::snake(Str::studly($contextId))) . '_ROUTES_END',
            default => 'ROUTES_END',
        };
    }

    /** El marcador tal y como se escribe y se busca en el archivo. */
    public static function comment(string $contextKey, string $routeFile, string $contextId = ''): string
    {
        return '// {{' . self::key($contextKey, $routeFile, $contextId) . '}}';
    }
}
