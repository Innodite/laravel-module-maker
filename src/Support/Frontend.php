<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

/**
 * Contra qué se escriben las vistas generadas — y si eso que se pidió está disponible.
 *
 * ⛔ **Este paquete no configura el frontend de ningún proyecto.** No publica composables, ni
 * componentes, ni toca `app.js`, `bootstrap.js` ni el middleware de Inertia. Solo GENERA. Quien
 * monta el andamiaje es el proyecto, o la biblioteca de interfaz que lo instale.
 *
 * La dirección de esa dependencia va en un solo sentido: **la biblioteca instala este paquete, y
 * este paquete nunca la instala a ella**. Es público, y declararla obligaría a cualquiera que lo
 * use a poder descargar un producto que no es suyo.
 *
 * De ahí que el modo `innodite` **no traiga plantillas propias**: las aporta quien las tiene, por el
 * punto de extensión de {@see StubsDeVendor}. Y de ahí que esta clase exista — porque un modo que
 * pide algo que nadie aporta tiene que **decirlo**, no generar otra cosa en silencio.
 */
final class Frontend
{
    public const DEFAULT  = 'default';
    public const INNODITE = 'innodite';

    /** La plantilla por la que se pregunta para saber si hay quien aporte vistas. */
    private const VISTA_DE_REFERENCIA = 'vue-index.stub';

    /** El modo declarado, o `default` si lo declarado no se reconoce. */
    public static function modo(): string
    {
        $modo = (string) config('make-module.frontend.modo', self::DEFAULT);

        return $modo === self::INNODITE ? self::INNODITE : self::DEFAULT;
    }

    public static function esInnodite(): bool
    {
        return self::modo() === self::INNODITE;
    }

    /**
     * ¿Hay algún paquete instalado aportando las vistas?
     *
     * Se pregunta por el listado y no por las cuatro: es la que siempre está, y quien aporta un
     * juego de vistas aporta el juego entero. Preguntar por las cuatro convertiría un aporte
     * parcial —que es un error de quien aporta— en un «no hay nada», que señala al sitio equivocado.
     */
    public static function hayQuienAporteVistas(): bool
    {
        return StubsDeVendor::paquetesQueAportan(self::VISTA_DE_REFERENCIA) !== [];
    }

    /** @return array<int, string> Los paquetes que aportan las vistas. */
    public static function quienAportaVistas(): array
    {
        return StubsDeVendor::paquetesQueAportan(self::VISTA_DE_REFERENCIA);
    }

    /**
     * ⚠️ El caso que hay que decir en voz alta: se pidió la biblioteca y no hay quien la aporte.
     *
     * Sin este aviso el comando termina en verde y escribe las vistas genéricas. El usuario pidió
     * unas y tiene otras, **y no se entera hasta abrir la pantalla** — cuando ya generó varios
     * módulos con la forma equivocada.
     */
    public static function pidioBibliotecaQueNadieAporta(): bool
    {
        return self::esInnodite() && ! self::hayQuienAporteVistas();
    }

    /**
     * El layout que envuelve la pantalla generada, tal y como se importa.
     *
     * Vacío significa que la vista se dibuja suelta, y es lo correcto mientras el proyecto no diga
     * cuál es el suyo: el nombre y la ruta del layout son de cada proyecto, así que inventar uno
     * produce una vista que no compila — y el error aparece en el navegador, no al generar.
     */
    public static function layout(): string
    {
        return trim((string) config('make-module.frontend.layout', ''));
    }

    public static function tieneLayout(): bool
    {
        return self::layout() !== '';
    }

    /**
     * El nombre del componente con que se importa el layout, deducido de su ruta.
     *
     * `'@/Layouts/AppLayout.vue'` → `AppLayout`. Se deduce en vez de pedirse aparte porque son
     * dos formas del mismo dato: pedir las dos permite que dejen de coincidir, y entonces la vista
     * importa una cosa y usa otra.
     */
    public static function nombreDelLayout(): string
    {
        $base = basename(self::layout());
        $base = (string) preg_replace('/\.vue$/', '', $base);

        return $base !== '' ? $base : 'AppLayout';
    }
}
