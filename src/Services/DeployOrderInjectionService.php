<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Support\Facades\File;

/**
 * Añade una subfuncionalidad al orden de despliegue del proyecto.
 *
 * **Por qué esto existe.** El desfase entre «lo que se generó» y «lo que se despliega» no rompe
 * ningún archivo: deja la aplicación a medio levantar, con una pantalla que no abre nadie porque su
 * seeder de permisos no se llegó a ejecutar. Y es el desfase más fácil de producir, porque generar y
 * declarar son dos actos separados por días. Así que **generar declara**: la entrada se escribe sola,
 * al final de la lista.
 *
 * **Al final, y moverla es del desarrollador.** El paquete sabe que la subfuncionalidad existe; no
 * sabe si va antes o después de otra —eso depende de qué tabla apunta a cuál, que es del negocio—.
 * Ponerla al final es la única posición que nunca miente sobre lo que el paquete sabe.
 *
 * **Si algo no cuadra, se dice; no se falla.** Un `make-module` no puede reventar porque la
 * configuración no esté publicada o porque alguien borrara un marcador: la subfuncionalidad ya está
 * generada y es correcta. Se avisa con la línea exacta que hay que escribir a mano.
 */
class DeployOrderInjectionService
{
    /** El marcador de la lista general, y el patrón del de cada contexto. */
    private const MARCADOR_GENERAL = '{{DEPLOY_END}}';

    public function __construct(
        private readonly ?object $output = null
    ) {
    }

    /**
     * Registra `Modulo/Contexto/SubFuncionalidad` al final de la lista de su contexto.
     *
     * @param  string       $path        La carpeta de la subfuncionalidad
     * @param  string|null  $contextKey  Contexto por el que se agrupa; `null` donde no hay eje
     */
    public function register(string $path, ?string $contextKey = null): void
    {
        $archivo = config_path('make-module.php');

        if (! File::exists($archivo)) {
            $this->avisar(
                "La configuración del paquete no está publicada, así que '{$path}' no se añadió al "
                . "orden de despliegue.\n"
                . "   Publícala con: php artisan vendor:publish --tag=make-module-config\n"
                . '   y añade la línea a mano en `deploy`.'
            );

            return;
        }

        $contenido = File::get($archivo);

        if ($this->yaRegistrada($contenido, $path)) {
            return;   // idempotente: generar dos veces no declara dos veces
        }

        $marcador = $this->marcadorDe($contextKey);

        if ($this->lineaDelMarcador($contenido, $marcador) !== null) {
            File::put($archivo, $this->insertarAntesDe($contenido, $marcador, $path));

            $this->avisar("Añadida al orden de despliegue: '{$path}'.", 'info');

            return;
        }

        if ($contextKey !== null && $this->lineaDelMarcador($contenido, self::MARCADOR_GENERAL) !== null) {
            // El contexto todavía no tiene su lista: se crea entera, con su marcador dentro, para
            // que la siguiente subfuncionalidad de ese contexto ya la encuentre.
            File::put($archivo, $this->crearListaDeContexto($contenido, $contextKey, $path));

            $this->avisar("Añadida al orden de despliegue: '{$path}' (contexto '{$contextKey}').", 'info');

            return;
        }

        $this->avisar(
            "No encontré dónde añadir '{$path}' en el orden de despliegue.\n"
            . "   Falta el marcador `// {$marcador}` en `deploy`, dentro de config/make-module.php.\n"
            . "   Añade esta línea donde le toque:  '{$path}',"
        );
    }


    /**
     * Las líneas del archivo, marcando cuáles son comentario de bloque.
     *
     * **B26 nació justo aquí.** El propio comentario de `deploy` documenta la forma del array con
     * marcadores de ejemplo dentro, así que buscar «la primera aparición del marcador» encontraba la
     * del ejemplo: las entradas se escribían **dentro de la documentación** y el array real quedaba
     * vacío. Sin error, sin aviso, y con un despliegue que no desplegaba nada.
     *
     * Es la misma forma que los diez defectos anteriores —dos apariciones de lo mismo y el consumidor
     * quedándose con la equivocada—, y por eso se resuelve donde se lee, no tapando el ejemplo: la
     * documentación tiene que poder enseñar el marcador de verdad.
     *
     * @param  array<int, string>  $lineas
     * @return array<int, bool>    índice → ¿es comentario de bloque?
     */
    private function comentarios(array $lineas): array
    {
        $dentro = false;
        $mapa   = [];

        foreach ($lineas as $i => $linea) {
            $abre  = str_contains($linea, '/*');
            $cierra = str_contains($linea, '*/');

            $mapa[$i] = $dentro || $abre;

            if ($abre && ! $cierra) {
                $dentro = true;
            }

            if ($cierra) {
                $dentro = false;
            }
        }

        return $mapa;
    }

    /**
     * La primera línea de código —no de comentario— que contenga la aguja, desde `$desde`.
     *
     * @param  array<int, string>  $lineas
     */
    private function lineaConCodigo(array $lineas, string $aguja, int $desde = 0): ?int
    {
        $comentario = $this->comentarios($lineas);

        foreach ($lineas as $i => $linea) {
            if ($i < $desde || $comentario[$i] || ! str_contains($linea, $aguja)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /** ¿Está ya declarada? Se compara la ruta exacta, entre comillas. */
    private function yaRegistrada(string $contenido, string $path): bool
    {
        $lineas = explode("\n", $contenido);

        return $this->lineaConCodigo($lineas, "'{$path}'") !== null
            || $this->lineaConCodigo($lineas, "\"{$path}\"") !== null;
    }

    /** `{{DEPLOY_CENTRAL_END}}` · `{{DEPLOY_TENANT_SHARED_END}}` · o el general. */
    private function marcadorDe(?string $contextKey): string
    {
        if ($contextKey === null) {
            return self::MARCADOR_GENERAL;
        }

        $clave = strtoupper(str_replace(['-', '/', ' '], '_', $contextKey));

        return '{{DEPLOY_' . $clave . '_END}}';
    }

    /**
     * El marcador **que el archivo ejecuta**, no el que su documentación enseña.
     *
     * Se busca a partir de la clave `deploy`, y saltando los comentarios de bloque: las dos cosas
     * apuntan al mismo sitio, y juntas hacen que un ejemplo documentado no pueda confundirse con el
     * array de verdad (B26).
     */
    private function lineaDelMarcador(string $contenido, string $marcador): ?int
    {
        $lineas = explode("\n", $contenido);
        $deploy = $this->lineaConCodigo($lineas, "'deploy'");

        if ($deploy === null) {
            return null;
        }

        return $this->lineaConCodigo($lineas, $marcador, $deploy);
    }

    /** Escribe la entrada justo encima del marcador, con su misma sangría. */
    private function insertarAntesDe(string $contenido, string $marcador, string $path): string
    {
        $lineas = explode("\n", $contenido);
        $i      = $this->lineaDelMarcador($contenido, $marcador);

        if ($i === null) {
            return $contenido;
        }

        $sangria = substr($lineas[$i], 0, strlen($lineas[$i]) - strlen(ltrim($lineas[$i])));

        array_splice($lineas, $i, 0, ["{$sangria}'{$path}',"]);

        return implode("\n", $lineas);
    }

    /** Crea la sublista del contexto —con su marcador— justo antes del marcador general. */
    private function crearListaDeContexto(string $contenido, string $contextKey, string $path): string
    {
        $lineas = explode("\n", $contenido);
        $i      = $this->lineaDelMarcador($contenido, self::MARCADOR_GENERAL);

        if ($i === null) {
            return $contenido;
        }

        $sangria = substr($lineas[$i], 0, strlen($lineas[$i]) - strlen(ltrim($lineas[$i])));
        $dentro  = $sangria . '    ';

        array_splice($lineas, $i, 0, [
            "{$sangria}'{$contextKey}' => [",
            "{$dentro}'{$path}',",
            "{$dentro}// " . $this->marcadorDe($contextKey),
            "{$sangria}],",
        ]);

        return implode("\n", $lineas);
    }

    private function avisar(string $mensaje, string $nivel = 'warn'): void
    {
        if ($this->output === null || ! method_exists($this->output, $nivel)) {
            return;
        }

        $this->output->{$nivel}(($nivel === 'warn' ? '⚠️  ' : '   📋 ') . $mensaje);
    }
}
