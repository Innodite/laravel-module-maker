<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Innodite\LaravelModuleMaker\Commands\Concerns\RehearsesChanges;
use Illuminate\Database\Seeder;
use Innodite\LaravelModuleMaker\Exceptions\ModeNotConfiguredException;
use Innodite\LaravelModuleMaker\Support\DryRun;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Throwable;

/**
 * Levanta el proyecto entero con una orden: esquema, datos y permisos, en el orden declarado.
 *
 * **El comando vive en el paquete; el seeder que invoca, en el proyecto.** Es la misma división que
 * el resto de la fase: aquí está lo que nadie lee ni toca —resolver el modo, validar el contexto,
 * encontrar la clase, arrancar el seeder—, y en `database/seeders/` la **secuencia de pasos**, que es
 * lo que el desarrollador lee y amplía. Escribir el comando también en el proyecto habría sembrado
 * una copia por proyecto de un código que es idéntico en todos.
 *
 *     php artisan innodite:deploy production                     (aplicación única)
 *     php artisan innodite:deploy production --context=central   (multitenant)
 *     php artisan innodite:deploy stage --context=tenant
 *
 * **Nada por defecto.** Ni el entorno ni el contexto se adivinan: desplegar stage donde tocaba
 * producción es el escenario que reconstruye tablas desde cero, y desplegar el contexto equivocado
 * llena la base que no era. Los dos son argumentos explícitos, y sin ellos el comando no arranca.
 */
class DeployCommand extends Command
{
    use RehearsesChanges;
    use ReportsFailures;

    protected $signature = 'innodite:deploy
        {entorno : Qué se despliega: stage | production}
        {--context= : Qué despliegue, en multitenant: central | tenant}
        {--force : No pedir confirmación aunque el modo destructivo esté activo}
        {--dry-run : Ensayo: enseña qué desplegaría y contra qué conexión, sin tocar la base}';

    protected $description = 'Despliega el proyecto entero —esquema, datos y permisos— en el orden declarado.';

    /** Los dos despliegues de un proyecto multitenant: dos bases de datos distintas. */
    private const DESPLIEGUES = ['central', 'tenant'];

    public function handle(): int
    {
        // El ensayo se enciende antes de nada y se apaga pase lo que pase: el interruptor es
        // del proceso, así que dejarlo puesto convertiría el siguiente comando en un ensayo
        // que nadie pidió.
        $this->startRehearsal();

        try {
            return $this->ejecutar();
        } finally {
            $this->reportRehearsal();
        }
    }

    private function ejecutar(): int
    {
        try {
            $mode = ModuleMode::current();
        } catch (ModeNotConfiguredException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $pieza = $this->resolvePiece();

        if ($pieza === null) {
            return self::FAILURE;
        }

        $contexto = $this->resolveContext($mode);

        if ($contexto === false) {
            return self::FAILURE;
        }

        $clase = SeederNames::projectDeploySeeder($contexto);
        $fqcn  = "Database\\Seeders\\{$clase}";

        if (! class_exists($fqcn)) {
            $this->fallo(
                "no existe {$fqcn}.",
                'lánzalo con el instalador — php artisan innodite:module-setup',
                'El seeder de despliegue es del proyecto, no del paquete: lo escribe el instalador y '
                . 'luego lo amplías tú.'
            );

            return self::FAILURE;
        }

        if (! $this->confirmDestructive()) {
            return self::FAILURE;
        }

        return $this->runProjectSeeder($fqcn, $pieza);
    }

    /**
     * `stage` → `Stage` · `production` → `Production`.
     *
     * Sin valor por defecto y sin aceptar abreviaturas: los dos despliegues hacen cosas distintas
     * —uno puede reconstruir desde cero— y quien se equivoca de palabra tiene que enterarse aquí.
     */
    private function resolvePiece(): ?string
    {
        $entorno = strtolower(trim((string) $this->argument('entorno')));

        $piezas = ['stage' => 'Stage', 'production' => 'Production'];

        if (! isset($piezas[$entorno])) {
            $this->fallo(
                "'{$entorno}' no es un entorno de despliegue.",
                'usa stage | production, escrito entero.',
                'stage reconstruye desde cero si se le pide con SEEDER_DESTRUCTIVE=true · '
                . 'production solo actualiza, y nunca borra nada.'
            );

            return null;
        }

        return $piezas[$entorno];
    }

    /**
     * El contexto del despliegue, `null` donde no hay eje de contexto, o `false` si no es válido.
     *
     * @return string|null|false
     */
    private function resolveContext(ModuleMode $mode)
    {
        $opcion = trim((string) $this->option('context'));

        if (! $mode->hasContextAxis()) {
            if ($opcion !== '') {
                $this->fallo(
                    "el modo '{$mode->value}' no tiene contextos: no pases --context={$opcion}.",
                    'lánzalo sin más — php artisan innodite:deploy production',
                    'En una aplicación única hay un solo despliegue y una sola base.'
                );

                return false;
            }

            return null;
        }

        if ($opcion === '') {
            $this->fallo(
                "falta --context: en {$mode->value} hay dos despliegues, contra dos bases distintas.",
                '--context=central (la aplicación central) · --context=tenant (un tenant, una '
                . 'ejecución por tenant).',
                'Desplegar el contexto equivocado llena la base que no era.'
            );

            return false;
        }

        if (! in_array($opcion, self::DESPLIEGUES, true)) {
            $this->fallo(
                "'{$opcion}' no es un despliegue.",
                'usa ' . implode(' | ', self::DESPLIEGUES) . '.',
                'No es la clave del orden de despliegue, sino la base que se llena: el despliegue de '
                . "tenant cubre 'tenant', 'tenant_shared' y 'shared'."
            );

            return false;
        }

        return $opcion;
    }

    /**
     * Con el modo destructivo activo se pregunta, porque eso sí borra.
     *
     * Solo entonces: el despliegue normal no destruye nada, y preguntar siempre enseña a contestar
     * que sí sin leer — que es como se pierde una base de datos el día que la pregunta importaba.
     */
    private function confirmDestructive(): bool
    {
        if (! filter_var(env('SEEDER_DESTRUCTIVE', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        if ((bool) $this->option('force')) {
            return true;
        }

        $aviso = 'SEEDER_DESTRUCTIVE=true: los seeders de stage vaciarán sus tablas de datos '
            . 'canónicos y los permisos se recrearán.';

        if (! $this->input->isInteractive()) {
            $this->fallo(
                $aviso,
                'pásale --force si de verdad quieres ejecutarlo sin confirmar.',
                'No hay consola con la que preguntar, y esto borra.'
            );

            return false;
        }

        $this->components->warn($aviso);

        return (bool) $this->confirm('¿Continuar?', false);
    }

    /**
     * Arranca el seeder como lo arranca `db:seed`, pero pasándole la pieza.
     *
     * `db:seed --class=` no admite parámetros, y la pieza —stage o producción— es justamente lo que
     * distingue un despliegue del otro. Se le entrega igual que se lo entregaría el `DatabaseSeeder`
     * del proyecto: `$this->call($clase, false, ['piece' => 'Stage'])`.
     */
    private function runProjectSeeder(string $fqcn, string $pieza): int
    {
        // En ensayo se enseña QUÉ se ejecutaría, y no se ejecuta.
        //
        // Es el comando donde más falta hace y el único de los cinco que no tenía la opción: los
        // otros cuatro escriben archivos, que se pueden borrar; este corre un seeder contra una base
        // real, y `stage` con el modo destructivo puesto reconstruye tablas desde cero. Un
        // despliegue lanzado contra la base equivocada no se deshace leyendo el error.
        if (DryRun::active()) {
            DryRun::record("ejecutaría  {$fqcn} · pieza {$pieza}");

            return self::SUCCESS;
        }

        /** @var Seeder $seeder */
        $seeder = $this->laravel->make($fqcn);

        $seeder->setContainer($this->laravel)->setCommand($this);

        try {
            $seeder->__invoke(['piece' => $pieza]);
        } catch (Throwable $e) {
            // El seeder ya listó cada fallo con su archivo:línea al cerrar; aquí solo se traduce a
            // un código de salida, para que quien lo automatizó se entere.
            $this->fallo(
                $e->getMessage(),
                'corrige lo que listó el seeder arriba y vuelve a lanzar el despliegue.',
                'El despliegue se detuvo: parte del orden declarado puede haberse aplicado ya.'
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
