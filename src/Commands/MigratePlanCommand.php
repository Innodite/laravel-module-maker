<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\PrintsHeader;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;

/**
 * `innodite:migrate-plan` — **retirado**. Queda solo para decir qué usar en su lugar.
 *
 * **Qué hacía y por qué sobraba.** Aplicaba las migraciones del proyecto leyendo los traits
 * `MigrationsList` del árbol. Era el único sitio del paquete que ejecutaba migraciones **fuera del
 * seeder**, y eso contradice lo que el propio paquete escribe en cada trait que genera: *«Nadie
 * ejecuta `migrate` a mano — el vehículo del despliegue es el seeder»* (R22).
 *
 * **Y ordenaba mal.** Recorría el árbol y ordenaba las subfuncionalidades con `ksort` sobre la ruta
 * del archivo — es decir, por el abecedario de las carpetas, ignorando `deploy`, que es donde el
 * proyecto declara qué va antes que qué. Con `Cart`, `Customer` y `Order` en un módulo de ventas,
 * `carts` se migraba antes que las dos tablas a las que apunta. No es hipotético: ese mismo criterio
 * rompió el alta de clientes de un proyecto real.
 *
 * Se podía corregir haciéndole leer `deploy`. Se retiró en su lugar porque lo único que ofrecía y
 * `innodite:deploy` no —aplicar el esquema **sin** sembrar— es justamente lo que la norma no
 * contempla; y mantener dos comandos que deciden por su cuenta en qué orden se toca la base es
 * mantener dos fuentes que pueden contradecirse.
 *
 * **Por qué sigue registrado y no borrado del todo.** Un comando que desaparece contesta
 * `Command "innodite:migrate-plan" is not defined`, que no dice a dónde ir. Este vive para dar esa
 * respuesta durante la v4; se borra entero cuando la v4 lleve tiempo estable.
 *
 * **Devuelve fallo, no éxito.** No hizo lo que se le pidió. Un comando que no ejecuta nada y termina
 * en verde es la forma de fallar que este issue vino a corregir, no la forma de anunciar una
 * retirada — en un script de despliegue pasaría inadvertido.
 */
class MigratePlanCommand extends Command
{
    use PrintsHeader;
    use ReportsFailures;

    protected $signature = 'innodite:migrate-plan';

    protected $description = 'RETIRADO — el esquema lo aplica innodite:deploy, a través de los seeders.';

    /**
     * Traga cualquier opción de las que aceptaba antes, en vez de rechazarla.
     *
     * Quien tenga escrito `innodite:migrate-plan --context=central` en un script recibiría, si no,
     * `The "--context" option does not exist`: un error sobre la firma, que manda a mirar el sitio
     * equivocado justo cuando lo que hay que leer es que el comando se retiró.
     *
     * ⛔ Y **no** se declaran esas opciones para lograrlo. Declarar `--dry-run` en un comando que no
     * ejecuta nada lo devolvería a la lista de los que ensayan, y su descripción tendría que empezar
     * por «Ensayo: …» —lo exige `FirmaCoherenteTest`— para algo que no ensaya. La firma diría una
     * cosa y el comando haría otra.
     */
    public function __construct()
    {
        parent::__construct();

        $this->ignoreValidationErrors();
    }

    public function handle(): int
    {
        $this->cabecera('Plan de migraciones — retirado');

        // Los dos comandos van cada uno en su renglón, y no seguidos dentro de una frase: la consola
        // envuelve el párrafo por el ancho que tenga, y ahí un nombre se parte por la mitad. Lo que
        // hay que poder copiar de un vistazo es justamente la línea que se teclea.
        $this->fallo(
            'innodite:migrate-plan se retiró en la v4.',
            "despliega con innodite:deploy.\n"
            . "     php artisan innodite:deploy stage --context=central\n"
            . "     Una migración suelta:\n"
            . '     php artisan innodite:migrate-one <Modulo:Contexto/archivo.php>',
            'El esquema, los datos y los permisos van juntos, en el orden que declaras en `deploy` '
            . 'dentro de config/make-module.php. Este los aplicaba por su cuenta, ordenando por el '
            . 'nombre de las carpetas: una tabla podía crearse antes que aquella a la que apunta.'
        );

        return self::FAILURE;
    }
}
