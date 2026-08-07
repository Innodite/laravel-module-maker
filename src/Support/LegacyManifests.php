<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;

/**
 * Los manifiestos `*.order.json` de la v3 — que ya no lee nadie.
 *
 * **Un proyecto que actualiza los sigue teniendo en el disco**, con su lista de migraciones y
 * seeders dentro, y todo indica que siguen mandando: están ahí, tienen contenido y nada en el árbol
 * dice lo contrario. Es la peor forma de quedarse obsoleto — un archivo que parece la fuente de la
 * verdad y ya no lo es—, así que el paquete lo dice **cuando toca**: al ejecutar los comandos que
 * antes los usaban.
 *
 * **Aviso, no error.** El proyecto funciona perfectamente sin tocarlos: las migraciones salen de los
 * traits `MigrationsList` y los seeders del array `deploy`. Borrarlos es una decisión del
 * desarrollador —puede querer consultarlos antes—, y hacer fallar un despliegue por un archivo que
 * ya nadie lee sería exactamente al revés de lo que hace falta.
 */
final class LegacyManifests
{
    /** La carpeta donde la v3 los guardaba. */
    public static function directory(): string
    {
        return rtrim((string) config('make-module.config_path'), '/\\') . '/migrations';
    }

    /**
     * Los manifiestos que quedan en el proyecto.
     *
     * @return array<int, string> Nombres de archivo, ordenados
     */
    public static function found(): array
    {
        $dir = self::directory();

        if (! File::isDirectory($dir)) {
            return [];
        }

        $nombres = [];

        foreach (File::files($dir) as $archivo) {
            if (str_ends_with($archivo->getFilename(), '.order.json')) {
                $nombres[] = $archivo->getFilename();
            }
        }

        sort($nombres);

        return $nombres;
    }

    /**
     * Los `Tests/test-config.json` que queden en los módulos — el último manifiesto JSON que hubo.
     *
     * Decía qué contextos tenía cada módulo y con qué variables de entorno ejecutar sus pruebas. Lo
     * sustituye lo mismo que sustituyó al de migraciones: **el contexto lo dicen el comando y el
     * modo**. `innodite:test Modulo SubFuncionalidad --context=central` no necesita que un archivo
     * le recuerde qué contextos existen — ya están en `contexts.json`, que es de donde salían.
     *
     * @return array<int, string> Rutas relativas a la carpeta de módulos, ordenadas
     */
    public static function testConfigs(): array
    {
        $modulos = rtrim((string) config('make-module.module_path'), '/\\');

        if (! File::isDirectory($modulos)) {
            return [];
        }

        $encontrados = [];

        foreach (File::directories($modulos) as $modulo) {
            if (File::exists("{$modulo}/Tests/test-config.json")) {
                $encontrados[] = basename($modulo) . '/Tests/test-config.json';
            }
        }

        sort($encontrados);

        return $encontrados;
    }

    /**
     * Avisa de los `test-config.json` que queden, si los hay.
     *
     * **Aviso, no error**, por el mismo motivo que el de los `*.order.json`: el proyecto funciona sin
     * ellos, y hacer fallar la ejecución de las pruebas por un archivo que ya nadie lee sería
     * exactamente al revés de lo que hace falta. Pero se dice — un JSON con contextos dentro parece
     * la fuente de la verdad hasta que alguien comprueba que no lo lee nadie.
     *
     * @param  object|null  $consola  Cualquier cosa con `warn()` y `line()` — el comando en curso
     */
    public static function noticeTestConfig(?object $consola): void
    {
        $encontrados = self::testConfigs();

        if ($encontrados === [] || $consola === null || ! method_exists($consola, 'warn')) {
            return;
        }

        $consola->warn(
            '⚠️  Queda(n) ' . count($encontrados) . ' test-config.json de la v3: '
            . implode(', ', $encontrados)
        );

        if (method_exists($consola, 'line')) {
            $consola->line(
                '   Ya no los lee nadie. El contexto lo dice el comando '
                . "(<comment>--context</comment>) y la forma del módulo la dice el modo.\n"
                . '   Puedes borrarlos cuando hayas comprobado que no te falta nada de ellos.'
            );
        }
    }

    /**
     * Avisa, si hay alguno, en la consola que se le pase.
     *
     * @param  object|null  $consola  Cualquier cosa con `warn()` y `line()` — el comando en curso
     */
    public static function notice(?object $consola): void
    {
        $encontrados = self::found();

        if ($encontrados === [] || $consola === null || ! method_exists($consola, 'warn')) {
            return;
        }

        $consola->warn(
            '⚠️  Quedan ' . count($encontrados) . ' manifiesto(s) de la v3 en '
            . self::directory() . ': ' . implode(', ', $encontrados)
        );

        if (method_exists($consola, 'line')) {
            $consola->line(
                '   Ya no los lee nadie. El orden de las migraciones vive en los traits '
                . '<comment>MigrationsList</comment> de cada subfuncionalidad, y el de los seeders en '
                . "<comment>deploy</comment> (config/make-module.php).\n"
                . '   Puedes borrar esa carpeta cuando hayas comprobado que no te falta nada de ella.'
            );
        }
    }
}
