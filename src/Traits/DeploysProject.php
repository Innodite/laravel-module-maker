<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Traits;

use Innodite\LaravelModuleMaker\Support\DeployCoverage;
use Innodite\LaravelModuleMaker\Support\SeederNames;

/**
 * DeploysProject — el fan-out del **proyecto** hacia los maestros de cada módulo.
 *
 * Es el escalón que faltaba. Hasta aquí cada maestro leía **solo lo suyo**: el orden de despliegue es
 * del proyecto entero, pero quien lo recorría se quedaba con las subfuncionalidades de su módulo y
 * nadie miraba la lista completa. Eso deja el despliegue en manos de quien recuerde llamar a los
 * maestros uno a uno, y en el orden correcto — que es justo lo que la lista viene a evitar.
 *
 *   deploy-{contexto}                            ← este trait
 *     └── {Pref}{Mod}Application{Pieza}Seeder    ← DeploysSubFeatures
 *           └── {Pref}{Mod}{Sub}{Pieza}Seeder
 *
 * **La cadena tampoco se cruza aquí, y por lo mismo.** El seeder de despliegue no nombra a ningún
 * maestro: los deriva de las carpetas declaradas más la pieza que le pidieron. Un despliegue de
 * producción no puede invocar el maestro de stage —el que reconstruye desde cero— porque esa clase no
 * está escrita en ninguna parte; se calcula.
 *
 * **Y no hay un segundo listado de módulos.** Los módulos salen de las mismas rutas que declaran el
 * orden, deduplicadas por el maestro al que resuelven. Escribir la lista de módulos aparte sería el
 * error de la fase anterior repetido un nivel más arriba: dos listas que el día que aparezca el
 * módulo siguiente solo se actualizarán una.
 */
trait DeploysProject
{
    /**
     * Llama, en orden, al maestro de cada módulo declarado — y sigue aunque alguno falle.
     *
     * Cada maestro va dentro de su propio `safe()`: un módulo que revienta no impide que se
     * desplieguen los demás, y al cerrar está la lista completa de lo que hay que arreglar. Sin esto,
     * un proyecto de diez módulos se levanta a un despliegue por fallo.
     *
     * @param  string  $piece  'Stage', 'Production' o 'Permissions'
     */
    protected function runMasters(string $piece): void
    {
        if (! in_array($piece, SeederNames::RUNNABLE, true)) {
            // Se rechaza en vez de normalizar ('stage' → 'Stage'): quien pide una pieza que no
            // existe cree estar desplegando algo, y adivinar cuál convierte un error de quien llama
            // en un despliegue que hace otra cosa. La de stage reconstruye desde cero.
            throw new \InvalidArgumentException(
                "'{$piece}' no es una pieza desplegable. Son: " . implode(', ', SeederNames::RUNNABLE) . '.'
            );
        }

        $maestros = $this->masterClasses($piece);

        if ($maestros === []) {
            return;   // ya se avisó al resolver las rutas: no se avisa dos veces de lo mismo
        }

        foreach ($maestros as $maestro) {
            $this->safe($maestro, function () use ($maestro): void {
                if (! class_exists($maestro)) {
                    throw new \RuntimeException(
                        "No existe el maestro {$maestro}.\n"
                        . 'O el módulo no se generó con este paquete, o su carpeta está mal escrita '
                        . 'en el orden de despliegue.'
                    );
                }

                $this->say("   → {$maestro}");

                $this->callWith($maestro);
            });
        }
    }

    /**
     * Los maestros que toca invocar, en el orden en que se declararon y sin repetir.
     *
     * Dos subfuncionalidades del mismo módulo y contexto resuelven al **mismo** maestro —el maestro
     * es del módulo, no de una subfuncionalidad—, y ese maestro ya despliega a las dos en el orden
     * de la lista. Invocarlo dos veces las desplegaría dos veces.
     *
     * @return array<int, string>
     */
    protected function masterClasses(string $piece): array
    {
        $maestros = [];

        foreach ($this->deployPaths() as $ruta) {
            try {
                $maestro = SeederNames::masterFromPath($ruta, $piece);
            } catch (\InvalidArgumentException $e) {
                $this->say("   ⚠️  {$e->getMessage()}", 'warn');

                continue;
            }

            if (! in_array($maestro, $maestros, true)) {
                $maestros[] = $maestro;
            }
        }

        return $maestros;
    }

    /**
     * Las rutas del orden de despliegue que cubre **este** seeder, en el orden en que están escritas.
     *
     * Se admiten las dos formas del array: una lista plana donde no hay eje de contexto, y un mapa
     * por contexto donde sí lo hay. Un seeder sin contextos declarados lee la lista entera, que es lo
     * que corresponde en un proyecto de una sola aplicación.
     *
     * @return array<int, string>
     */
    protected function deployPaths(): array
    {
        $declarado = config('make-module.deploy', []);

        if (! is_array($declarado) || $declarado === []) {
            $this->say(
                '   ⚠️  El orden de despliegue está vacío, así que no hay nada que desplegar. Se '
                . 'declara en `deploy`, dentro de config/make-module.php: cada línea es la carpeta '
                . 'de una subfuncionalidad, y el orden de la lista es el orden en que se despliegan.',
                'warn'
            );

            return [];
        }

        if ($this->contexts === []) {
            return $this->aplanar($declarado);
        }

        $rutas       = [];
        $encontrados = [];

        foreach ($this->contexts as $contexto) {
            if (! isset($declarado[$contexto]) || ! is_array($declarado[$contexto])) {
                continue;
            }

            $encontrados[] = $contexto;

            foreach ($declarado[$contexto] as $ruta) {
                if (is_string($ruta) && trim($ruta) !== '') {
                    $rutas[] = trim($ruta);
                }
            }
        }

        if ($encontrados === []) {
            // Las claves que este seeder dice cubrir no están en el array. Es el desfase silencioso
            // de siempre —dos declaraciones que dejaron de coincidir—, y aquí el síntoma sería un
            // despliegue que termina en verde sin haber desplegado nada.
            $this->say(
                '   ⚠️  Ninguno de los contextos de este despliegue —' . implode(', ', $this->contexts)
                . '— está declarado en `deploy`. Los que hay son: '
                . (implode(', ', array_keys(array_filter($declarado, 'is_array'))) ?: '(ninguno)')
                . '. Ajusta $contexts en este seeder, o el contexto con el que generas.',
                'warn'
            );
        }

        return $rutas;
    }

    /**
     * La lista entera, venga plana o agrupada por contexto.
     *
     * Un proyecto de una sola aplicación la escribe plana, que es lo natural donde no hay contextos.
     * Se admite igual la agrupada porque el modo puede cambiar antes que el archivo, y devolver
     * arrays donde se esperan rutas convertiría eso en un error de tipos a tres llamadas de
     * distancia.
     *
     * @param  array<mixed>  $declarado
     * @return array<int, string>
     */
    protected function aplanar(array $declarado): array
    {
        $rutas = [];

        array_walk_recursive($declarado, static function (mixed $valor) use (&$rutas): void {
            if (is_string($valor) && trim($valor) !== '') {
                $rutas[] = trim($valor);
            }
        });

        return $rutas;
    }

    /**
     * Al terminar: lo que está generado y **nadie despliega**.
     *
     * Es el cruce que solo se puede hacer desde aquí, porque hace falta la lista completa delante —un
     * maestro ve su módulo y nada más—. Y es un aviso, no un fallo: lo que se desplegó se desplegó
     * bien; lo que falta es una declaración que el paquete no puede escribir en el sitio correcto,
     * porque la posición dentro del orden depende de qué tabla apunta a cuál.
     */
    protected function reportCoverage(): void
    {
        $sinDeclarar = DeployCoverage::undeclared();

        if ($sinDeclarar !== []) {
            $this->say(
                "\n⚠️  Generadas pero sin declarar en el orden de despliegue (" . count($sinDeclarar)
                . "): nadie las despliega.\n   Añádelas a `deploy` en config/make-module.php, cada una "
                . "en la posición que le toque:\n     '" . implode("',\n     '", $sinDeclarar) . "',",
                'warn'
            );
        }

        $sinGenerar = DeployCoverage::missing();

        if ($sinGenerar !== []) {
            $this->say(
                "\n⚠️  Declaradas en el orden de despliegue y sin generar (" . count($sinGenerar)
                . "):\n     " . implode("\n     ", $sinGenerar)
                . "\n   O están mal escritas, o esas subfuncionalidades nunca se generaron.",
                'warn'
            );
        }
    }
}
