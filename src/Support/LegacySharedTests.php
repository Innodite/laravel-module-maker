<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;

/**
 * Detecta el árbol de pruebas retirado desde v5.0.0: un trait compartido por pieza, guardado en
 * `Tests/Feature/Shared/` e incorporado con `use <Trait>` en cada contexto.
 *
 * **Detección pura, nunca automigración.** v5 escribe el cuerpo completo de cada pieza directo en su
 * contexto —`extends` su base, no incorpora nada—, así que cualquiera de las dos señales de abajo
 * basta para saber que el grupo quedó en la forma anterior. Pero un trait compartido no se puede
 * trasladar con seguridad por un script ciego: sus aserciones y sus comentarios hay que leerlos, no
 * concatenarlos. Esta clase solo dice qué encontró — nunca mueve ni borra nada.
 */
final class LegacySharedTests
{
    /** `Tests/Feature/Shared`, hermana de la carpeta del contexto que se está mirando. */
    public static function carpetaCompartida(string $carpetaDeContexto): string
    {
        return dirname($carpetaDeContexto) . '/Shared';
    }

    public static function tieneCarpetaCompartida(string $carpetaDeContexto): bool
    {
        return File::isDirectory(self::carpetaCompartida($carpetaDeContexto));
    }

    /**
     * Los `use <Trait>;` que alguna pieza del grupo incorpora dentro de su propia clase.
     *
     * v5 no genera esta forma en ninguna pieza —ni siquiera la base usa un trait así—, así que
     * cualquier `use` indentado dentro de una clase de este grupo es un resto de la forma anterior,
     * sea cual sea el nombre del trait.
     *
     * @return array<string, array<int, string>>  nombre de archivo => traits que incorpora
     */
    public static function incorporacionesDeTrait(string $carpetaDeContexto): array
    {
        if (! File::isDirectory($carpetaDeContexto)) {
            return [];
        }

        $hallazgos = [];

        foreach (File::glob("{$carpetaDeContexto}/*.php") as $archivo) {
            $traits = self::traitsIncorporadosEn((string) File::get($archivo));

            if ($traits !== []) {
                $hallazgos[basename($archivo)] = $traits;
            }
        }

        return $hallazgos;
    }

    /**
     * Los nombres de trait de los `use` que aparecen **dentro de una clase**, no los `use` de
     * importación de namespace que van sueltos al principio del archivo.
     *
     * La distinción es de indentación: un `use` de importación va a columna cero, y uno de
     * incorporación de trait vive dentro de las llaves de la clase — en el estilo de este paquete,
     * indentado. No hace falta un parser AST para esta comprobación puntual; lo que sí exige un
     * parser es mover el cuerpo del trait con seguridad, y eso esta clase no lo intenta.
     *
     * @return array<int, string>
     */
    private static function traitsIncorporadosEn(string $contenido): array
    {
        if (
            preg_match_all(
                '/^[ \t]+use\s+([A-Za-z_][A-Za-z0-9_\\\\]*(?:\s*,\s*[A-Za-z_][A-Za-z0-9_\\\\]*)*)\s*;/m',
                $contenido,
                $coincidencias
            ) === false
        ) {
            return [];
        }

        $traits = [];

        foreach ($coincidencias[1] as $lista) {
            foreach (explode(',', $lista) as $nombre) {
                $traits[] = trim($nombre);
            }
        }

        return $traits;
    }

    /**
     * Arma el mensaje de `$this->fallo(...)` — igual para el comando de pruebas y para el doctor.
     *
     * @param  array<string, array<int, string>>  $incorporaciones
     */
    public static function mensajeDeFallo(bool $tieneCarpeta, string $carpetaCompartida, array $incorporaciones): array
    {
        $encontrado = [];

        if ($tieneCarpeta) {
            $encontrado[] = "la carpeta {$carpetaCompartida}";
        }

        foreach ($incorporaciones as $archivo => $traits) {
            $encontrado[] = "{$archivo} incorpora con use: " . implode(', ', $traits);
        }

        return [
            'este grupo todavía tiene la forma de pruebas retirada desde v5.0.0:'
                . "\n    " . implode("\n    ", $encontrado),
            'inlinea el cuerpo del trait, letra por letra, en cada contexto que lo usaba — leyendo sus '
                . 'aserciones y sus comentarios, nunca concatenándolos — y borra Shared/. La forma '
                . 'nueva la explica docs/es/las-pruebas.md.',
            'Un trait compartido ata un contexto al otro justo donde tienen que poder divergir: el día '
                . 'que uno necesite su propia lógica de negocio, tocar el trait cambia también al que '
                . 'no debía cambiar.',
        ];
    }
}
