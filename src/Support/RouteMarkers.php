<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

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
     * **La decide el archivo, no el contexto**, y por una razón que costó un defecto: cada archivo
     * de rutas sirve a un contexto y solo a uno —`web.php` a la central, `tenant.php` al
     * inquilino—, así que dentro de un archivo hay una sección y un solo sitio donde crece.
     *
     * ⛔ Hasta la 4.x la clave llevó el **id del inquilino** (`TENANT_ACME_ROUTES_END`), porque el
     * catálogo declaraba inquilinos nombrados y cada uno pedía su bloque. Eso es lo que hacía que un
     * módulo escribiera en `tenant.php` un bloque por cliente, importando controladores que nadie
     * había generado. Con un inquilino que es un contexto y no una lista, el id sobra.
     *
     * Un `$contextKey` que no sea de los dos —uno que declare el proyecto— cae en `ROUTES_END`, que
     * es correcto: su archivo será el suyo.
     */
    public static function key(string $contextKey, string $routeFile, string $contextId = ''): string
    {
        return match ($routeFile) {
            'web.php'    => 'CENTRAL_ROUTES_END',
            'tenant.php' => 'TENANT_ROUTES_END',
            default      => 'ROUTES_END',
        };
    }

    /** El marcador tal y como se escribe y se busca en el archivo. */
    public static function comment(string $contextKey, string $routeFile, string $contextId = ''): string
    {
        return '// {{' . self::key($contextKey, $routeFile, $contextId) . '}}';
    }
}
