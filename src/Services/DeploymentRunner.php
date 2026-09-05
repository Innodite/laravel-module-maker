<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Innodite\LaravelModuleMaker\Contracts\TenantContext;
use Innodite\LaravelModuleMaker\Services\Tenancy\StanclTenantContext;
use Innodite\LaravelModuleMaker\Support\DeploymentResult;
use Innodite\LaravelModuleMaker\Support\DryRun;
use Throwable;

/**
 * Ejecuta un seeder de despliegue: contra la conexión actual, o dentro del contexto de cada tenant.
 *
 * **Es el núcleo que hasta aquí vivía dentro del comando.** `innodite:deploy` mezclaba tres trabajos
 * distintos: leer lo que escribió la persona, decidir qué seeder toca, y ejecutarlo. Los dos
 * primeros son de la consola —sus mensajes hablan de banderas y argumentos—; el tercero no tiene
 * nada de consola, y es justo el que necesita el proyecto anfitrión cuando levanta un tenant recién
 * creado desde su propio código.
 *
 * **No imprime nada ni decide nada.** Recibe la clase y la pieza ya resueltas y devuelve un
 * `DeploymentResult`. Quien llama traduce eso a lo suyo: el comando, a un mensaje con su arreglo y
 * un código de salida; el aprovisionamiento de un proyecto, a seguir o a deshacer.
 *
 * ⚠️ **El comando se le pasa solo para que el seeder pueda hablar.** `ReportsSeederErrors::say()`
 * calla si no hay comando, así que sin él el despliegue funciona igual: en silencio.
 */
class DeploymentRunner
{
    public function __construct(
        private readonly Application $app,
        private readonly ?Command $command = null,
        private readonly ?TenantContext $context = null,
    ) {
    }

    /**
     * El contexto de cliente. Se inyecta en las pruebas y se resuelve solo en producción.
     *
     * ⛔ No se guarda en una propiedad al construir: `usable()` consulta la configuración del
     * proyecto, y hacerlo en el constructor la congelaría en el momento equivocado.
     */
    private function context(): TenantContext
    {
        return $this->context ?? new StanclTenantContext();
    }

    /**
     * Ejecuta el seeder contra la conexión que esté activa.
     *
     * @param  string  $fqcn   Clase del seeder de despliegue del proyecto.
     * @param  string  $piece  `Stage` o `Production`.
     * @param  string  $step   Con qué nombre queda registrado el paso en el resultado.
     */
    public function run(string $fqcn, string $piece, string $step = 'default'): DeploymentResult
    {
        return $this->runOne(DeploymentResult::empty(), $fqcn, $piece, $step);
    }

    /**
     * Ejecuta el seeder una vez **dentro** del contexto de cada tenant.
     *
     * **Un tenant que falla no cancela a los demás:** cada base es independiente, y detenerse en el
     * tercero de doce deja nueve sin desplegar por un fallo ajeno. Lo que falló queda en el
     * resultado, con la clave del tenant, para que quien llama sepa exactamente cuáles rehacer.
     *
     * @param  array<int, object>       $tenants  Modelos de tenant, ya resueltos.
     * @param  null|callable(string):void  $notify  Se invoca con la clave, antes de cada tenant.
     */
    public function runForTenants(
        string $fqcn,
        string $piece,
        array $tenants,
        ?callable $notify = null,
    ): DeploymentResult {
        $result = DeploymentResult::empty();

        foreach ($tenants as $tenant) {
            $key = (string) $tenant->getTenantKey();

            if (DryRun::active()) {
                DryRun::record("ejecutaría  {$fqcn} · pieza {$piece} · tenant {$key}");

                continue;
            }

            if ($notify !== null) {
                $notify($key);
            }

            $contexto = $this->context();
            $contexto->enter($tenant);

            try {
                $result = $this->runOne($result, $fqcn, $piece, $key);
            } finally {
                // Salir del contexto pase lo que pase: dejarlo abierto haría que el siguiente
                // tenant —o lo que venga después— escribiera en la base del anterior.
                $contexto->leave();
            }
        }

        return $result;
    }

    /**
     * Arranca el seeder como lo arranca `db:seed`, pero pasándole la pieza.
     *
     * `db:seed --class=` no admite parámetros, y la pieza —stage o producción— es justamente lo que
     * distingue un despliegue del otro. Se le entrega igual que se lo entregaría el `DatabaseSeeder`
     * del proyecto: `$this->call($clase, false, ['piece' => 'Stage'])`.
     */
    private function runOne(DeploymentResult $result, string $fqcn, string $piece, string $step): DeploymentResult
    {
        // En ensayo se enseña QUÉ se ejecutaría, y no se ejecuta.
        //
        // Es donde más falta hace: los generadores escriben archivos, que se pueden borrar; esto
        // corre un seeder contra una base real, y `stage` con el modo destructivo puesto reconstruye
        // tablas desde cero. Un despliegue lanzado contra la base equivocada no se deshace leyendo
        // el error.
        if (DryRun::active()) {
            DryRun::record("ejecutaría  {$fqcn} · pieza {$piece}");

            return $result->withApplied($step);
        }

        /** @var Seeder $seeder */
        $seeder = $this->app->make($fqcn);

        $seeder->setContainer($this->app);

        if ($this->command !== null) {
            $seeder->setCommand($this->command);
        }

        try {
            $seeder->__invoke(['piece' => $piece]);
        } catch (Throwable $e) {
            // El seeder ya listó cada fallo con su archivo:línea al cerrar; aquí solo se recoge el
            // mensaje, sin reescribirlo, para que quien llama decida qué hacer con él.
            return $result->withFailure($step, $e->getMessage());
        }

        return $result->withApplied($step);
    }
}
