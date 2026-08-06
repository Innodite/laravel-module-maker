<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Traits;

use Innodite\LaravelModuleMaker\Support\SeederNames;

/**
 * DeploysSubFeatures — el fan-out de un maestro hacia las subfuncionalidades de su módulo.
 *
 * **Lo que hace un maestro y lo que no.** No tiene esquema ni datos propios: solo llama, en orden, a
 * las subfuncionalidades de su módulo, y les pasa el modo con el que se le llamó a él. Todo lo que
 * sabe de sus hijos es **la carpeta donde viven**.
 *
 * **Por qué eso importa: la cadena no se puede cruzar.** El maestro no nombra a ningún hijo — deriva
 * cada clase de la carpeta más **su propia pieza** (`$piece`). El maestro de producción resuelve
 * piezas de producción porque es lo único que sabe resolver; invocar un seeder de stage —el que
 * reconstruye desde cero— no es algo que aquí se evite con un `if`, es algo que no se puede escribir.
 *
 * **El orden lo declara el desarrollador**, en la configuración del proyecto, y se lee de arriba
 * abajo. El paquete no lo adivina: una tabla con clave foránea no puede sembrarse antes que aquella
 * a la que apunta, y eso lo sabe el negocio, no el generador.
 */
trait DeploysSubFeatures
{
    /**
     * Llama a cada subfuncionalidad del módulo, en orden, y sigue aunque alguna falle.
     *
     * Cada hijo va dentro de su propio `safe()`: si el primero revienta, los demás **se ejecutan
     * igual** y al cerrar se ve la lista completa. Sin esto, un despliegue de diez subfuncionalidades
     * se arregla de una en una, a un despliegue por fallo.
     */
    protected function runSubFeatures(): void
    {
        $rutas = $this->subFeaturePaths();

        if ($rutas === []) {
            $this->say(
                "   ⚠️  Ninguna subfuncionalidad declarada para «{$this->module}» en el orden de "
                . 'despliegue. Añádelas a `deploy` en config/make-module.php: el orden de esa lista '
                . 'es el orden en que se despliegan.',
                'warn'
            );

            return;
        }

        foreach ($rutas as $ruta) {
            $this->safe($ruta, fn () => $this->seedFrom($ruta));
        }
    }

    /**
     * Despliega **una** subfuncionalidad, nombrada por su carpeta.
     *
     * @throws \RuntimeException Si la carpeta no tiene la pieza que este maestro busca.
     */
    protected function seedFrom(string $path): void
    {
        $clase = SeederNames::classFromPath($path, $this->piece);

        if (! class_exists($clase)) {
            throw new \RuntimeException(
                "La subfuncionalidad '{$path}' no tiene su seeder de {$this->piece}.\n"
                . "Se buscó {$clase}.\n"
                . 'O la carpeta está mal escrita en el orden de despliegue, o esa subfuncionalidad '
                . 'nunca se generó.'
            );
        }

        $this->say("   → {$path}");

        $this->callWith($clase, $this->childParameters());
    }

    /**
     * Las rutas de **este** módulo dentro del orden de despliegue del proyecto, tal como se declaró.
     *
     * La lista es del proyecto entero; el maestro se queda solo con lo suyo y respeta el orden en que
     * está escrita. Se admiten las dos formas —una lista plana donde no hay eje de contexto, y un
     * mapa por contexto donde sí lo hay—, porque un proyecto single-app no tiene contexto por el que
     * agrupar.
     *
     * @return array<int, string>
     */
    protected function subFeaturePaths(): array
    {
        $declarado = config('make-module.deploy', []);

        if (! is_array($declarado)) {
            return [];
        }

        $rutas = $this->context === null
            ? $declarado
            : ($declarado[$this->context] ?? []);

        // Un mapa por contexto en un maestro sin contexto: se aplanan sus listas antes de filtrar,
        // para no devolver arrays donde se esperan rutas.
        if ($this->context === null && $rutas !== [] && ! array_is_list($rutas)) {
            $rutas = array_merge(...array_values(array_filter($rutas, 'is_array')));
        }

        $mias = array_values(array_filter(
            $rutas,
            fn (mixed $ruta): bool => is_string($ruta)
                && SeederNames::moduleOfPath($ruta) === $this->module
        ));

        return $this->sinRepetidas($mias);
    }

    /**
     * Quita las entradas repetidas conservando **la primera**, y lo dice.
     *
     * Desplegar dos veces la misma subfuncionalidad no rompe nada —los seeders son idempotentes—,
     * pero tarda el doble y, sobre todo, **es la señal de que alguien la declaró dos veces**: la
     * segunda copia está en el sitio equivocado del orden, y ese sí es un problema real el día que
     * importe. Se conserva la primera porque es la que fija la posición en la que se pensó.
     *
     * @param  array<int, string>  $rutas
     * @return array<int, string>
     */
    protected function sinRepetidas(array $rutas): array
    {
        $unicas = array_values(array_unique($rutas));

        foreach (array_diff_assoc($rutas, $unicas) as $repetida) {
            $this->say(
                "   ⚠️  '{$repetida}' está declarada más de una vez en el orden de despliegue. Se "
                . 'despliega una sola vez, en su primera posición.',
                'warn'
            );
        }

        return $unicas;
    }

    /**
     * Lo que se le pasa a cada hijo.
     *
     * Solo el maestro de permisos tiene algo que propagar: los seeders de stage y de producción
     * resuelven su propio modo —el de stage preguntando al entorno, el de producción no
     * preguntando—, así que pasarles nada es lo correcto.
     *
     * @return array<string, mixed>
     */
    protected function childParameters(): array
    {
        return [];
    }
}
