<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Traits;

/**
 * ResolvesSeederDestructiveMode — la única puerta por la que un seeder borra datos.
 *
 * **No destructivo por defecto (R23 · R24).** Un seeder se ejecuta muchas veces y en entornos que
 * no siempre son el que uno cree: el mismo comando que en local reconstruye una tabla de prueba, en
 * el servidor equivocado borra datos reales. Por eso el borrado no es un modo del seeder sino una
 * **orden explícita** de quien lo lanza:
 *
 *     SEEDER_DESTRUCTIVE=true php artisan db:seed --class="…StageSeeder"
 *
 * Sin esa señal, el Stage se comporta exactamente como el de producción: migra, aplica los deltas y
 * hace upsert. Con ella, y **solo** en Stage, trunca las tablas de datos canónicos y las recarga.
 *
 * **El seeder de producción no usa este trait**, y eso es deliberado: no es que responda `false`, es
 * que no tiene la pregunta. Poner la variable de entorno en el servidor no puede habilitar un
 * borrado que en ese archivo no está escrito.
 */
trait ResolvesSeederDestructiveMode
{
    /**
     * ¿Se pidió explícitamente el modo destructivo?
     *
     * Se lee del entorno y no de la configuración a propósito: es una decisión de **esta ejecución**,
     * no un ajuste del proyecto que alguien pueda dejarse encendido en un archivo versionado.
     */
    protected function isDestructive(): bool
    {
        return filter_var(env('SEEDER_DESTRUCTIVE', false), FILTER_VALIDATE_BOOLEAN);
    }
}
