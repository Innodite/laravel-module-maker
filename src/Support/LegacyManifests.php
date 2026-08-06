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
