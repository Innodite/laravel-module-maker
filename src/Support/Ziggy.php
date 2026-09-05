<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Illuminate\Support\Facades\File;

/**
 * Ziggy — el generador de rutas del navegador, que es de quien dependen las vistas generadas.
 *
 * **Por qué el paquete tiene que saber de esto.** Las cuatro vistas que genera piden sus rutas
 * **por el nombre** —`route(contextRoute('invoices.list'))`— y no escribiendo la dirección. Es lo
 * correcto: el nombre lo resuelve Ziggy contra el mapa que publica el servidor, así que cambiar un
 * prefijo en el archivo de rutas no obliga a tocar ni una vista.
 *
 * Lo que no es correcto es **darlo por supuesto**. Ziggy es un paquete aparte, y un proyecto puede
 * no tenerlo o tenerlo sin la directiva `@routes` en su layout. Cuando falta, `route` no existe en
 * el navegador y la pantalla muere **al montarse**, antes de pintar nada, con un
 * `route is not defined` que no menciona ni al módulo, ni al paquete, ni a Ziggy. Es el defecto que
 * dejó sin abrir toda pantalla generada sobre un proyecto que no lo llevaba.
 *
 * ⚠️ **Esto NO es husmear el vendor para decidir qué generar.** Esa regla —la de {@see ModuleMode}
 * y {@see TenancyPackage}: lo declara el proyecto, no lo adivina el paquete— vale para la
 * generación, donde adivinar mal produce una estructura equivocada multiplicada por cada módulo.
 * Aquí no se genera nada distinto según lo que se encuentre: lo generado es siempre lo mismo y esta
 * clase solo **diagnostica** si va a funcionar, que es exactamente lo que ya hace el `doctor` con
 * el modelo `User` o con el registro del bridge.
 */
final class Ziggy
{
    /**
     * Los dos nombres del paquete en Packagist.
     *
     * Cambió de organización en la v2 y los dos siguen instalados por ahí, así que comprobar solo
     * uno da un falso «no está» en la mitad de los proyectos.
     *
     * @var array<int, string>
     */
    private const PAQUETES = ['tightenco/ziggy', 'tighten/ziggy'];

    /** Las clases que expone, una por versión. */
    private const CLASES = ['Tightenco\\Ziggy\\Ziggy', 'Tighten\\Ziggy\\Ziggy'];

    /**
     * ¿Está Ziggy instalado en el proyecto anfitrión?
     *
     * Se pregunta por tres vías porque cada una falla en un escenario legítimo: la clase no está
     * cargada si el autoload del proyecto no se ha recargado, la carpeta de vendor no existe en una
     * instalación con `vendor-dir` movido, y el `composer.json` no lo nombra cuando llega como
     * dependencia de otro paquete. Con las tres, un «no está» es de verdad un no está.
     */
    public static function instalado(): bool
    {
        foreach (self::CLASES as $clase) {
            if (class_exists($clase)) {
                return true;
            }
        }

        foreach (self::PAQUETES as $paquete) {
            if (File::isDirectory(base_path("vendor/{$paquete}"))) {
                return true;
            }
        }

        return self::declaradoEnComposer();
    }

    /**
     * El layout que publica el mapa de rutas con `@routes`, si alguno lo hace.
     *
     * Sin esa directiva Ziggy está instalado y `route` **sigue sin existir** en el navegador: la
     * directiva es la que escribe el mapa y la función en la página. Es la mitad del requisito que
     * más se olvida, porque `composer require` deja la sensación de que ya está.
     *
     * @return string|null  Ruta relativa del archivo que la lleva, o null si ninguno
     */
    public static function layoutConDirectiva(): ?string
    {
        $vistas = base_path('resources/views');

        if (! File::isDirectory($vistas)) {
            return null;
        }

        foreach (File::allFiles($vistas) as $archivo) {
            if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }

            if (preg_match('/@routes\b/', (string) File::get($archivo->getPathname())) === 1) {
                return 'resources/views/' . $archivo->getRelativePathname();
            }
        }

        return null;
    }

    /**
     * ¿Le falta al proyecto algo para que las vistas generadas resuelvan sus rutas?
     *
     * Las dos mitades cuentan: instalado sin `@routes` falla igual que no instalado, y con el mismo
     * error, así que un diagnóstico que solo mirase la primera diría «OK» sobre una pantalla que no
     * abre.
     */
    public static function falta(): bool
    {
        return ! self::instalado() || self::layoutConDirectiva() === null;
    }

    /** ¿Lo nombra el `composer.json` del proyecto, en cualquiera de sus dos secciones? */
    private static function declaradoEnComposer(): bool
    {
        $composer = base_path('composer.json');

        if (! File::exists($composer)) {
            return false;
        }

        $declarado = json_decode((string) File::get($composer), true);

        if (! is_array($declarado)) {
            return false;
        }

        $dependencias = array_merge(
            (array) ($declarado['require'] ?? []),
            (array) ($declarado['require-dev'] ?? []),
        );

        foreach (self::PAQUETES as $paquete) {
            if (array_key_exists($paquete, $dependencias)) {
                return true;
            }
        }

        return false;
    }
}
