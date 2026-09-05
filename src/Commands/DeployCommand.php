<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\PrintsHeader;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Innodite\LaravelModuleMaker\Commands\Concerns\RehearsesChanges;
use Innodite\LaravelModuleMaker\Exceptions\ModeNotConfiguredException;
use Innodite\LaravelModuleMaker\Services\DeploymentRunner;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Innodite\LaravelModuleMaker\Support\TenancyPackage;

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
    use PrintsHeader;
    use ReportsFailures;

    protected $signature = 'innodite:deploy
        {environment : Qué se despliega: stage | production}
        {--context= : Contexto contra el que se despliega, en multitenant: central | tenant}
        {--tenant= : Qué tenant se despliega, por su clave. Una ejecución por tenant}
        {--all : Despliega TODOS los tenants, uno tras otro}
        {--force : Despliega sin pedir confirmación, aunque el modo destructivo esté activo}
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
        $this->cabecera('Despliegue — ' . strtolower(trim((string) $this->argument('environment'))));

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

        // Cuando los tenants comparten funcionalidad, el despliegue se ejecuta DENTRO del contexto
        // de cada uno, no contra una conexión.
        //
        // Es la otra cara de por qué esos seeders no declaran conexión: el paquete de tenencia
        // conmuta la conexión por defecto al inicializar el contexto, y en HTTP eso lo hace el
        // middleware de identificación. En consola no hay middleware que lo haga, así que hasta aquí
        // el seeder corría contra la base por defecto —la central— creyendo que escribía en la del
        // cliente, y sin un solo aviso.
        //
        // ⛔ No aplica cuando cada tenant tiene su propia lógica: ahí la estructura es distinta por
        // cliente, el contexto declara su `connection_key` y el seeder generado la lleva escrita. El
        // destino ya está resuelto en el archivo, y entrar en el contexto no añade nada.
        if ($contexto === 'tenant' && ! $mode->requiresTenantConnectionKey()) {
            return $this->deployTenants($fqcn, $pieza);
        }

        return $this->runProjectSeeder($fqcn, $pieza);
    }

    /**
     * Despliega el contexto de tenant una vez por cada tenant pedido, dentro de su contexto.
     *
     * **Sin valor por defecto, como el resto del comando.** `--tenant=` despliega uno y `--all` los
     * despliega todos; sin ninguno de los dos no se arranca. Adivinar «el primero» o «todos» son las
     * dos formas de llenar la base que no era, que es justo lo que este comando existe para evitar.
     */
    private function deployTenants(string $fqcn, string $pieza): int
    {
        // Primero lo que decide quien lanza el comando, y solo después lo que depende del entorno:
        // a quien se olvidó de elegir tenant no se le contesta hablándole de la configuración.
        if (! $this->eleccionDeTenantValida()) {
            return self::FAILURE;
        }

        $tenancy = TenancyPackage::current();

        if (! $tenancy->initialisesContext()) {
            $this->fallo(
                "el proyecto no declara un paquete de tenencia que el generador sepa inicializar "
                . "(hoy: {$tenancy->label()}).",
                'declara `tenancy_package` en config/make-module.php, o despliega cada tenant desde '
                . 'tu propio comando envolviendo el seeder en el contexto del cliente.',
                'Sin inicializar el contexto, el seeder escribe en la base por defecto —la central— '
                . 'creyendo que escribe en la del cliente.'
            );

            return self::FAILURE;
        }

        $tenants = $this->tenantsPedidos();

        if ($tenants === false) {
            return self::FAILURE;
        }

        if ($tenants === []) {
            $this->components->warn(
                'No hay ningún tenant que desplegar: la tabla de tenants está vacía.'
            );

            return self::SUCCESS;
        }

        // Un tenant que falla no cancela a los demás: cada base es independiente, y detenerse en el
        // tercero de doce deja nueve sin desplegar por un fallo ajeno. El runner los recorre y
        // devuelve cuáles fallaron; el seeder ya listó por pantalla qué falló dentro de cada uno.
        $resultado = $this->runner()->runForTenants(
            $fqcn,
            $pieza,
            $tenants,
            fn (string $clave) => $this->components->info("Tenant {$clave}"),
        );

        if ($resultado->successful()) {
            return self::SUCCESS;
        }

        $this->fallo(
            count($resultado->errors()) . ' de ' . count($tenants) . ' tenant(s) fallaron: '
            . implode(', ', array_keys($resultado->errors())) . '.',
            'corrige lo que listó el seeder de cada uno y vuelve a lanzarlo con --tenant=<clave>.',
            'Los demás quedaron desplegados: no hace falta repetirlos.'
        );

        return self::FAILURE;
    }

    /**
     * ¿Quedó dicho contra qué tenants se despliega?
     *
     * **Sin valor por defecto, como el resto del comando.** Adivinar «el primero» o «todos» son las
     * dos formas de llenar la base que no era, que es justo lo que este comando existe para evitar.
     */
    private function eleccionDeTenantValida(): bool
    {
        $pedido = trim((string) $this->option('tenant'));
        $todos  = (bool) $this->option('all');

        if ($pedido !== '' && $todos) {
            $this->fallo(
                '--tenant y --all piden cosas distintas.',
                'usa --tenant=<clave> para uno, o --all para todos.',
                'Con los dos puestos no hay forma de saber cuál gana.'
            );

            return false;
        }

        if ($pedido === '' && ! $todos) {
            $this->fallo(
                'falta elegir el tenant: aquí hay una base por cliente.',
                '--tenant=<clave> despliega uno · --all los despliega todos.',
                'Una ejecución por tenant: sin elegir, el despliegue iría a la base por defecto.'
            );

            return false;
        }

        return true;
    }

    /**
     * Los tenants que hay que desplegar, o `false` si lo pedido no se puede resolver.
     *
     * @return array<int, object>|false
     */
    private function tenantsPedidos(): array|false
    {
        $pedido = trim((string) $this->option('tenant'));
        $todos  = (bool) $this->option('all');

        /** @var class-string $modelo */
        $modelo = (string) config('tenancy.tenant_model');

        if ($modelo === '' || ! class_exists($modelo)) {
            $this->fallo(
                'no encuentro el modelo de tenant del proyecto.',
                'declara `tenant_model` en config/tenancy.php.',
                'Es de donde se sacan las claves de los clientes a desplegar.'
            );

            return false;
        }

        if ($todos) {
            return $modelo::all()->all();
        }

        $tenant = $modelo::find($pedido);

        if ($tenant === null) {
            $this->fallo(
                "no existe el tenant '{$pedido}'.",
                'lista los que hay y vuelve a lanzarlo con una clave que exista.',
                'Se busca por la clave de tenant que declara el propio modelo.'
            );

            return false;
        }

        return [$tenant];
    }

    /**
     * `stage` → `Stage` · `production` → `Production`.
     *
     * Sin valor por defecto y sin aceptar abreviaturas: los dos despliegues hacen cosas distintas
     * —uno puede reconstruir desde cero— y quien se equivoca de palabra tiene que enterarse aquí.
     */
    private function resolvePiece(): ?string
    {
        $entorno = strtolower(trim((string) $this->argument('environment')));

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
        $resultado = $this->runner()->run($fqcn, $pieza);

        if ($resultado->successful()) {
            return self::SUCCESS;
        }

        // El seeder ya listó cada fallo con su archivo:línea al cerrar; aquí solo se traduce a un
        // código de salida, para que quien lo automatizó se entere.
        $this->fallo(
            (string) $resultado->firstError(),
            'corrige lo que listó el seeder arriba y vuelve a lanzar el despliegue.',
            'El despliegue se detuvo: parte del orden declarado puede haberse aplicado ya.'
        );

        return self::FAILURE;
    }

    /** El runner, con este comando dentro para que el seeder pueda seguir hablando por pantalla. */
    private function runner(): DeploymentRunner
    {
        return new DeploymentRunner($this->laravel, $this);
    }
}
