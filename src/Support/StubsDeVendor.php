<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;

/**
 * StubsDeVendor — los stubs que aporta otro paquete instalado en el proyecto.
 *
 * **El problema que resuelve.** Lo que este paquete genera tiene que servir en un proyecto pelado,
 * pero un proyecto que además lleve una biblioteca de interfaz querría generar contra ella: su
 * tabla, sus botones, su enganche en el menú. Hornear eso aquí no es una opción —este paquete es
 * público y esa forma es de la casa—, y dejarlo fuera del todo obliga a reescribir a mano cada
 * pantalla generada.
 *
 * La salida es que **el paquete no sepa de nadie**: declara dónde mirar, y quien quiera aportar
 * stubs los pone ahí. Concretamente, cualquier paquete instalado que traiga
 *
 *     stubs/module-maker/contextual/<nombre>.stub
 *
 * participa en la cascada. Ni un nombre de paquete escrito aquí, ni una lista que mantener: el
 * contrato es una ruta, y quien la cumple entra.
 *
 * ⭐ **Por eso el descubrimiento es por forma y no por nombre.** Una lista blanca —«si está tal
 * paquete, usa sus stubs»— publicaría en un repositorio abierto exactamente lo que no puede
 * publicar: qué otros productos existen. Un glob no nombra a nadie.
 *
 * ⛔ Y el orden importa: quien aporta gana al paquete y pierde contra el proyecto. El proyecto es
 * el único que puede tener la última palabra sobre su propio código.
 */
final class StubsDeVendor
{
    /** El camino, dentro de un paquete, que lo hace participar. */
    private const CONTRATO = 'stubs/module-maker/contextual';

    /**
     * Carpetas descubiertas, cacheadas por proceso.
     *
     * @var array<string, string>|null  paquete ("vendor/nombre") → carpeta absoluta
     */
    private static ?array $cache = null;

    /**
     * Los paquetes instalados que aportan stubs, en orden alfabético.
     *
     * El orden es alfabético y no «el que Composer devuelva» a propósito: con dos paquetes
     * aportando el mismo stub, lo que decide no puede ser el orden de un directorio, que cambia
     * entre máquinas. Alfabético es arbitrario pero **igual en todas partes**, y quien se lleve la
     * sorpresa la tendrá siempre, no una vez de cada tres.
     *
     * @return array<string, string>  paquete → carpeta absoluta de sus stubs
     */
    public static function carpetas(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $encontradas = [];

        foreach (self::raicesDeVendor() as $vendor) {
            foreach ((array) glob("{$vendor}/*/*/" . self::CONTRATO, GLOB_ONLYDIR) as $carpeta) {
                // De ".../vendor/acme/paquete/stubs/module-maker/contextual" se queda "acme/paquete".
                $raiz    = dirname($carpeta, 3);
                $paquete = basename(dirname($raiz)) . '/' . basename($raiz);

                $encontradas[$paquete] = $carpeta;
            }
        }

        ksort($encontradas);

        return self::$cache = $encontradas;
    }

    /**
     * El stub aportado que gana, si alguno lo aporta.
     *
     * @param  string  $stubFile  Nombre del archivo (ej: 'vue-index.stub')
     * @return string|null        Ruta absoluta, o null si no lo aporta nadie
     */
    public static function buscar(string $stubFile): ?string
    {
        foreach (self::carpetas() as $carpeta) {
            $ruta = "{$carpeta}/{$stubFile}";

            if (File::exists($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    /**
     * Qué paquetes aportan un stub concreto.
     *
     * Se usa para **decirlo**: que la generación cambie de origen sin avisar es justo la clase de
     * cosa que después nadie sabe explicar. Con más de uno, además, hay un empate que el
     * desarrollador tiene que ver.
     *
     * @return array<int, string>  Nombres de paquete
     */
    public static function paquetesQueAportan(string $stubFile): array
    {
        $paquetes = [];

        foreach (self::carpetas() as $paquete => $carpeta) {
            if (File::exists("{$carpeta}/{$stubFile}")) {
                $paquetes[] = $paquete;
            }
        }

        return $paquetes;
    }

    /**
     * Olvida lo descubierto.
     *
     * La caché es por proceso, y en una suite de pruebas el proceso es uno solo para todos los
     * casos: sin esto, el primero que descubra una carpeta se la deja puesta a los demás.
     */
    public static function olvidar(): void
    {
        self::$cache = null;
    }

    /**
     * Dónde puede estar el `vendor/` del proyecto.
     *
     * Son dos sitios y hacen falta los dos. El habitual es `base_path('vendor')`. El otro es el
     * vendor **real** deducido de dónde está instalado este mismo paquete, que es el que vale
     * cuando el proyecto movió su `vendor-dir` — un caso raro, pero en el que mirar solo el
     * primero no encontraría nada y el silencio se leería como «no hay nadie aportando stubs».
     *
     * @return array<int, string>
     */
    private static function raicesDeVendor(): array
    {
        $raices = [];

        $habitual = base_path('vendor');
        if (File::isDirectory($habitual)) {
            $raices[] = $habitual;
        }

        // .../vendor/innodite/laravel-module-maker/src/Support → .../vendor
        $deducida = dirname(__DIR__, 4);
        if (basename($deducida) === 'vendor' && File::isDirectory($deducida)) {
            $raices[] = $deducida;
        }

        return array_values(array_unique($raices));
    }
}
