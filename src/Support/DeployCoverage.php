<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;

/**
 * DeployCoverage — lo que hay generado en disco, contra lo que está declarado en el orden.
 *
 * **El desfase que esto vigila no rompe ningún archivo.** Una subfuncionalidad generada y no
 * declarada existe, compila y tiene sus seis piezas perfectas; simplemente **nadie la despliega**. La
 * aplicación queda a medio levantar: una pantalla que no abre nadie porque su seeder de permisos no
 * se llegó a ejecutar, y una tabla vacía porque su seeder de datos tampoco. Sin error, sin log.
 *
 * `make-module` declara sola cada subfuncionalidad que genera, así que el desfase ya
 * no nace por olvido. Pero sigue naciendo por las otras tres vías: una configuración sin publicar el
 * día que se generó, un marcador borrado al reordenar la lista a mano, y una subfuncionalidad creada
 * a mano copiando otra. Por eso el despliegue **cruza al terminar** en vez de confiar.
 *
 * **Avisa, no falla.** Lo desplegado se desplegó bien; lo que falta es una declaración que el
 * paquete no puede escribir por su cuenta —la posición dentro del orden depende de qué tabla apunta
 * a cuál—. Reventar aquí convertiría un despliegue correcto en uno fallido.
 */
final class DeployCoverage
{
    /**
     * Las subfuncionalidades **generadas**, tal como se nombran en el orden de despliegue.
     *
     * Una carpeta es una subfuncionalidad cuando tiene al menos una de sus tres piezas ejecutables.
     * La de los maestros queda fuera por su nombre: sus archivos también terminan en `Seeder.php`
     * —`…ApplicationStageSeeder`—, y contarla declararía como subfuncionalidad al módulo entero.
     *
     * @return array<int, string> Rutas 'Modulo/Contexto/SubFuncionalidad', ordenadas
     */
    public static function onDisk(): array
    {
        $raiz = (string) config('make-module.module_path');

        if ($raiz === '' || ! File::isDirectory($raiz)) {
            return [];
        }

        $rutas = [];

        foreach (File::directories($raiz) as $moduloPath) {
            $modulo = basename($moduloPath);

            // Las subfuncionalidades son las carpetas de PRIMER nivel del módulo, y sus seeders
            // viven dentro de cada una: `{Módulo}/{SubFuncionalidad}/Database/Seeders/{Contexto}`.
            // Antes se recorría `{Módulo}/Database/Seeders` entero, que hoy solo contiene los
            // maestros — y con eso el cruce no encontraba **ninguna** subfuncionalidad en disco:
            // el aviso de «nadie la despliega» dejaba de salir justo cuando más falta hace.
            foreach (File::directories($moduloPath) as $subPath) {
                $sub     = basename($subPath);
                $seeders = "{$subPath}/Database/Seeders";

                if (! File::isDirectory($seeders)) {
                    continue;
                }

                foreach (self::contextFolders($seeders) as $contexto) {
                    $rutas[] = $contexto === ''
                        ? "{$modulo}/{$sub}"
                        : "{$modulo}/{$contexto}/{$sub}";
                }
            }
        }

        sort($rutas);

        return $rutas;
    }

    /**
     * Las declaradas en el orden de despliegue, **de todos los contextos**.
     *
     * Se aplanan a propósito: la pregunta que responde este cruce es «¿a esta subfuncionalidad la
     * despliega alguien?», no «¿la despliega el contexto que le tocaría?». Declararla en el contexto
     * equivocado es otro problema, y quien lo ve es el maestro al no encontrarla.
     *
     * @return array<int, string>
     */
    public static function declared(): array
    {
        $declarado = config('make-module.deploy', []);

        if (! is_array($declarado)) {
            return [];
        }

        $rutas = [];

        array_walk_recursive($declarado, static function (mixed $valor) use (&$rutas): void {
            if (is_string($valor) && trim($valor) !== '') {
                $rutas[] = trim($valor);
            }
        });

        return array_values(array_unique($rutas));
    }

    /**
     * Lo generado que **nadie declaró** — el aviso con el que cierra un despliegue.
     *
     * @return array<int, string>
     */
    public static function undeclared(): array
    {
        return array_values(array_diff(self::onDisk(), self::declared()));
    }

    /**
     * Lo declarado que **no está en disco** — la otra mitad del cruce.
     *
     * El maestro ya rechaza una carpeta que nadie generó, nombrándola, cuando le toca desplegarla.
     * Esto la ve igual cuando el maestro no llega a ejecutarse: una ruta mal escrita en un contexto
     * cuyo módulo no tiene ninguna otra subfuncionalidad no la despliega nadie, y por tanto nadie la
     * rechaza.
     *
     * @return array<int, string>
     */
    public static function missing(): array
    {
        return array_values(array_diff(self::declared(), self::onDisk()));
    }

    /**
     * Los contextos con piezas dentro de `{SubFuncionalidad}/Database/Seeders/`.
     *
     * Devuelve `''` cuando las piezas están sueltas ahí —aplicación única, donde no hay eje— y el
     * nombre de cada carpeta de contexto cuando lo hay. La coordenada del orden de despliegue se
     * compone luego como `Módulo/Contexto/SubFuncionalidad`, que es la que declara el proyecto.
     *
     * @return array<int, string> '', 'Central', 'Tenant'…
     */
    private static function contextFolders(string $seedersRoot): array
    {
        $encontrados = [];

        if (self::tienePiezaEjecutable($seedersRoot)) {
            $encontrados[] = '';
        }

        foreach (File::directories($seedersRoot) as $dir) {
            if (basename($dir) === SeederNames::MASTER_FOLDER) {
                continue;
            }

            if (self::tienePiezaEjecutable($dir)) {
                $encontrados[] = basename($dir);
            }
        }

        sort($encontrados);

        return $encontrados;
    }

    /** ¿Hay ahí dentro un Stage, un Production o un Permissions? */
    private static function tienePiezaEjecutable(string $dir): bool
    {
        foreach (File::files($dir) as $archivo) {
            $nombre = $archivo->getFilename();

            foreach (SeederNames::RUNNABLE as $pieza) {
                if (str_ends_with($nombre, "{$pieza}Seeder.php")) {
                    return true;
                }
            }
        }

        return false;
    }
}
