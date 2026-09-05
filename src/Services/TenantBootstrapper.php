<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Innodite\LaravelModuleMaker\Exceptions\TenantBootstrapFailedException;
use Innodite\LaravelModuleMaker\Support\DeploymentResult;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Innodite\LaravelModuleMaker\Support\TenancyPackage;

/**
 * Levanta un tenant **recién creado**, desde el código del proyecto anfitrión.
 *
 * ⭐ **API PÚBLICA.** Esta clase la llama el proyecto, no el paquete: su nombre y la forma de
 * `bootstrap()` son un compromiso, y cambiarlos exige una versión mayor con su manual de migración.
 * Lo de dentro puede reescribirse las veces que haga falta.
 *
 *     use Innodite\LaravelModuleMaker\Services\TenantBootstrapper;
 *
 *     $resultado = (new TenantBootstrapper($this->app))->bootstrap($tenant, 'production');
 *
 *     if (! $resultado->successful()) {
 *         // Se sabe QUÉ quedó aplicado, así que el alta se puede deshacer con criterio.
 *         $this->revertir($tenant, $resultado->applied());
 *     }
 *
 * **Por qué existe.** El paquete sabía levantar un tenant **por consola y a posteriori**:
 * `innodite:deploy stage --context=tenant --tenant=<clave>`. Pero el alta de un cliente ocurre en
 * runtime, dentro de una petición, sobre una base que acaba de nacer — y desde ahí no había a quién
 * llamar. El primer proyecto que llegó a ese punto se escribió el suyo: un `glob()` de carpetas
 * ordenado por abecedario y un `migrate` por cada una. Resultado: el orden lo decidía el nombre de
 * la carpeta —`carts` antes que `customers`, con clave foránea de por medio— y **dos de las seis
 * piezas del patrón no llegaban a correr nunca**. Aquí el orden es el declarado, y las seis piezas
 * las ejecuta el maestro de cada módulo, como en cualquier otro despliegue.
 *
 * **Lo que NO hace, y es deliberado:**
 *
 *   · **No crea el tenant.** Llega creado; esto levanta su base.
 *   · **No elige el entorno.** `stage` o `production` los dice quien llama, sin valor por defecto:
 *     desplegar stage donde tocaba producción es lo que reconstruye tablas desde cero.
 *   · **No sigue adelante cuando algo falta.** Si no hay orden declarado, si falta el seeder del
 *     proyecto o si la tenencia no se puede inicializar, **lanza**. Un alta que devuelve «bien» con
 *     la base vacía es peor que una que falla: la segunda se reintenta, la primera se descubre
 *     cuando el cliente entra.
 */
class TenantBootstrapper
{
    public function __construct(
        private readonly Application $app,
        private readonly ?Command $command = null,
    ) {
    }

    /**
     * Levanta la base del tenant: esquema, deltas, datos canónicos y permisos, en el orden declarado.
     *
     * **Abre el contexto del cliente él mismo** — salvo que ya esté abierto para ese mismo tenant,
     * y entonces lo respeta y no lo toca. Es la diferencia entre poder llamarlo desde dentro de un
     * `$tenant->run(...)` o dejar al anfitrión sin contexto justo después de levantarlo.
     *
     * @param  object  $tenant       El tenant ya creado, con su `getTenantKey()`.
     * @param  string  $environment  `stage` o `production`. Sin valor por defecto.
     *
     * @throws TenantBootstrapFailedException Cuando levantarlo no es posible — nunca en silencio.
     */
    public function bootstrap(object $tenant, string $environment): DeploymentResult
    {
        $pieza = $this->piece($environment);
        $mode  = ModuleMode::current();

        if (! $mode->hasContextAxis()) {
            throw TenantBootstrapFailedException::notMultitenant($mode->value);
        }

        $declarado = config('make-module.deploy', []);

        if (! is_array($declarado) || $declarado === []) {
            throw TenantBootstrapFailedException::nothingDeclared();
        }

        // El contexto ya abierto se respeta: cerrarlo al terminar dejaría sin él a quien nos llamó.
        $dentro = $this->alreadyInsideContextOf($tenant);

        if (! $dentro) {
            $tenancy = TenancyPackage::current();

            if (! $tenancy->initialisesContext()) {
                throw TenantBootstrapFailedException::tenancyNotUsable($tenancy->label());
            }
        }

        // La clase del proyecto se comprueba la última, y a propósito: es lo único que depende de
        // que el instalador se haya ejecutado, y de nada sirve decir que falta un seeder a quien
        // todavía no había declarado ni el modo ni el orden.
        $fqcn = 'Database\\Seeders\\' . SeederNames::projectDeploySeeder('tenant');

        if (! class_exists($fqcn)) {
            throw TenantBootstrapFailedException::seederMissing($fqcn);
        }

        $runner = new DeploymentRunner($this->app, $this->command);

        return $dentro
            ? $runner->run($fqcn, $pieza, (string) $tenant->getTenantKey())
            : $runner->runForTenants($fqcn, $pieza, [$tenant]);
    }

    /** `stage` → `Stage` · `production` → `Production`, y nada más. */
    private function piece(string $environment): string
    {
        $piezas = ['stage' => 'Stage', 'production' => 'Production'];
        $dado   = strtolower(trim($environment));

        return $piezas[$dado] ?? throw TenantBootstrapFailedException::unknownEnvironment($environment);
    }

    /**
     * ¿Estamos ya dentro del contexto de **este** tenant?
     *
     * Comprobar solo que «hay un contexto abierto» no basta: si el abierto fuera el de otro cliente,
     * respetarlo sembraría la base equivocada, que es exactamente el fallo que este arranque evita.
     */
    private function alreadyInsideContextOf(object $tenant): bool
    {
        if (! function_exists('tenancy')) {
            return false;
        }

        $tenancy = tenancy();

        if (! ($tenancy->initialized ?? false)) {
            return false;
        }

        $abierto = $tenancy->tenant ?? null;

        return $abierto !== null
            && (string) $abierto->getTenantKey() === (string) $tenant->getTenantKey();
    }
}
