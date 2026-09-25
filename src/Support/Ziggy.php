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
 *
 * ⚠️ **Y se exige solo a quien de verdad lo usa.** Las vistas pueden salir de otra plantilla —una
 * que el proyecto publicó o que aporta un paquete instalado— y esa plantilla puede resolver sus
 * rutas con su propia función importada, sin Ziggy. Exigírselo igual dejaba el `doctor` en rojo
 * sobre un proyecto cuyas pantallas abren, y el FIX mandaba instalar un paquete que nadie iba a
 * llamar. Por eso lo primero es mirar las plantillas que van a ganar: {@see self::laUsanLasVistas()}.
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

    /** Las cuatro plantillas de vista, que son las que piden rutas. */
    private const VISTAS = ['vue-index.stub', 'vue-create.stub', 'vue-edit.stub', 'vue-show.stub'];

    /**
     * ¿Alguna de las plantillas de vista que se van a usar depende de Ziggy?
     *
     * Se miran **todas las que pueden ganar**, no solo la genérica: una plantilla del proyecto para
     * un contexto concreto gana en ese contexto, y si ella usa Ziggy, Ziggy hace falta aunque la
     * genérica no lo use.
     */
    public static function laUsanLasVistas(): bool
    {
        foreach (self::VISTAS as $stub) {
            foreach (self::plantillasQuePuedenGanar($stub) as $ruta) {
                if (self::dependeDeZiggy((string) File::get($ruta))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ¿Este contenido llama a la `route()` que pone Ziggy?
     *
     * `window.route(` es Ziggy siempre. Un `route(` suelto también lo es, salvo que la plantilla la
     * importe de algún sitio: entonces es otra función, y Ziggy no pinta nada.
     */
    public static function dependeDeZiggy(string $contenido): bool
    {
        if (preg_match('/\bwindow\.route\s*\(/', $contenido) === 1) {
            return true;
        }

        if (preg_match('/(?<![\w.$])route\s*\(/', $contenido) !== 1) {
            return false;
        }

        return preg_match('/import\s*\{[^}]*\broute\b[^}]*\}\s*from/', $contenido) !== 1;
    }

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
     * abre. Y ninguna cuenta si las vistas no lo usan.
     */
    public static function falta(): bool
    {
        if (! self::laUsanLasVistas()) {
            return false;
        }

        return ! self::instalado() || self::layoutConDirectiva() === null;
    }

    /**
     * Las plantillas que pueden llegar a usarse para una vista, en el orden de {@see HasStubs}.
     *
     * Las del proyecto por contexto cuentan todas, porque cada una gana en el suyo; de las demás
     * gana solo la primera que exista: la genérica del proyecto, la que aporta un paquete o la de
     * este paquete.
     *
     * @return array<int, string>
     */
    private static function plantillasQuePuedenGanar(string $stub): array
    {
        $delProyecto = config('make-module.stubs.path') . '/contextual';

        $rutas = array_merge(
            (array) glob("{$delProyecto}/*/{$stub}"),
            (array) glob("{$delProyecto}/*/*/{$stub}"),
        );

        $generica = array_values(array_filter(
            ["{$delProyecto}/{$stub}", StubsDeVendor::buscar($stub), __DIR__ . "/../../stubs/contextual/{$stub}"],
            fn (?string $ruta) => $ruta !== null && File::exists($ruta),
        ));

        if ($generica !== []) {
            $rutas[] = $generica[0];
        }

        return array_values(array_filter($rutas, fn ($ruta) => is_string($ruta) && File::exists($ruta)));
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
