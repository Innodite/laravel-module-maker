<?php

declare(strict_types=1);

use Illuminate\Database\Seeder;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Innodite\LaravelModuleMaker\Traits\DeploysSubFeatures;
use Innodite\LaravelModuleMaker\Traits\ReportsSeederErrors;

/**
 * Los tres maestros del módulo: fan-out ordenado, y una cadena que no se puede cruzar.
 *
 * Un maestro no tiene esquema ni datos propios — solo llama, en orden, a las subfuncionalidades de su
 * módulo. Lo que estas pruebas vigilan es lo que de verdad puede salir mal ahí:
 *
 *   1. Que el maestro de producción invoque un seeder de **stage** — el que reconstruye desde cero.
 *      Es el peor escenario posible de esta fase, y aquí no se evita con un `if`: el maestro no
 *      nombra a sus hijos, los deriva de la carpeta más su propia pieza, así que lo otro **no se
 *      puede escribir**.
 *   2. Que un hijo que falla se lleve por delante a los que vienen detrás.
 *   3. Que el orden declarado por el desarrollador no se respete.
 */

/** Un maestro de mentira: el trait de verdad, con las llamadas anotadas en vez de ejecutadas. */
function maestroDePrueba(string $piece, ?string $context = null, string $module = 'Invoice'): object
{
    return new class ($piece, $context, $module) extends Seeder {
        use DeploysSubFeatures;
        use ReportsSeederErrors;

        /** @var array<int, array{clase: string, parametros: array<string, mixed>}> */
        public array $llamados = [];

        public function __construct(
            protected string $piece,
            protected ?string $context,
            protected string $module,
        ) {
        }

        public function desplegar(): void
        {
            $this->runSubFeatures();
        }

        public function cerrar(): void
        {
            $this->reportErrors();
        }

        /** @param  class-string  $class */
        public function callWith($class, array $parameters = [])
        {
            $this->llamados[] = ['clase' => $class, 'parametros' => $parameters];

            return $this;
        }
    };
}

it('los tres maestros se generan en su carpeta, aparte de las subfuncionalidades', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    $esperados = array_map(
        static fn (string $maestro): string => "Database/Seeders/Central/Application/{$maestro}.php",
        SeederNames::masterPieces('Central', 'Invoice')
    );

    $modulo->assertTreeHas($esperados, 'Los maestros son del módulo, no de una subfuncionalidad.');
});

it('cada maestro invoca su propia pieza y ninguna otra', function (string $pieza) {
    // La cadena no se cruza. Y no porque el archivo lo compruebe, sino porque el nombre del hijo se
    // deriva de `$piece`: un maestro de producción solo sabe resolver piezas de producción.
    $modulo  = $this->generateModule('Invoice', ModuleMode::SingleApp);
    $maestro = $modulo->contents(
        'Database/Seeders/Application/' . SeederNames::masterFor('', 'Invoice', $pieza) . '.php'
    );

    expect($maestro)->toContain("protected string \$piece = '{$pieza}';");

    $otras = array_values(array_diff(SeederNames::RUNNABLE, [$pieza]));

    foreach ($otras as $otra) {
        expect(str_contains(soloCodigo($maestro), $otra))->toBeFalse(
            "El maestro de {$pieza} menciona '{$otra}' en su código: la cadena tiene que ser "
            . 'inderivable hacia otra pieza, no evitable con una comprobación.'
        );
    }
})->with([['Stage'], ['Production'], ['Permissions']]);

it('la carpeta resuelve a la clase de la pieza que pide quien pregunta', function () {
    // El mismo camino que sigue el maestro en ejecución, aislado: carpeta + pieza → clase.
    expect(SeederNames::classFromPath('Invoice/Central/Invoice', 'Production'))
        ->toBe('Modules\Invoice\Database\Seeders\Central\Invoice\CentralInvoiceInvoiceProductionSeeder');

    expect(SeederNames::classFromPath('UserManagement/Tenant/Shared/Role', 'Stage'))
        ->toBe('Modules\UserManagement\Database\Seeders\Tenant\Shared\Role\TenantSharedUserManagementRoleStageSeeder');

    // Sin eje de contexto: la ruta trae dos segmentos y el nombre no lleva prefijo.
    expect(SeederNames::classFromPath('Invoice/Invoice', 'Permissions'))
        ->toBe('Modules\Invoice\Database\Seeders\Invoice\InvoiceInvoicePermissionsSeeder');
});

it('una ruta que no nombra una subfuncionalidad se rechaza diciendo qué se esperaba', function () {
    expect(fn () => SeederNames::classFromPath('Invoice', 'Stage'))
        ->toThrow(InvalidArgumentException::class, 'no nombra una subfuncionalidad');
});

it('el maestro despliega en el orden declarado, y solo lo de su módulo', function () {
    // La lista es del proyecto entero: el maestro se queda con lo suyo y respeta el orden escrito,
    // porque una tabla con clave foránea no puede sembrarse antes que aquella a la que apunta.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    cargarPiezas($modulo, 'Database/Seeders/Invoice', SeederNames::subFeaturePieces('', 'Invoice', 'Invoice'));

    config()->set('make-module.deploy', [
        'Payment/Payment',      // de otro módulo: no es cosa de este maestro
        'Invoice/Invoice',
    ]);

    $maestro = maestroDePrueba('Production');
    $maestro->desplegar();

    expect(array_column($maestro->llamados, 'clase'))
        ->toBe(['Modules\Invoice\Database\Seeders\Invoice\InvoiceInvoiceProductionSeeder']);
});

it('solo el maestro de permisos propaga el modo destructivo', function () {
    // Los seeders de stage y de producción resuelven su propio modo: el de stage preguntando al
    // entorno, el de producción no preguntando. Pasarles el flag sería darles una decisión que ya
    // está tomada dentro.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    cargarPiezas($modulo, 'Database/Seeders/Invoice', SeederNames::subFeaturePieces('', 'Invoice', 'Invoice'));

    config()->set('make-module.deploy', ['Invoice/Invoice']);

    $maestro = maestroDePrueba('Stage');
    $maestro->desplegar();

    expect($maestro->llamados[0]['parametros'])->toBe([]);

    // Y el de permisos, que es el único que sí lo pasa:
    expect($modulo->contents('Database/Seeders/Application/InvoiceApplicationPermissionsSeeder.php'))
        ->toContain("return ['destructive' => \$this->isDestructive()];");
});

it('un hijo que falla no detiene a los que vienen detrás', function () {
    // Sin esto, un despliegue de diez subfuncionalidades se arregla a un despliegue por fallo.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    cargarPiezas($modulo, 'Database/Seeders/Invoice', SeederNames::subFeaturePieces('', 'Invoice', 'Invoice'));

    config()->set('make-module.deploy', [
        'Invoice/NoGenerada',   // esta revienta: nadie la generó
        'Invoice/Invoice',
    ]);

    $maestro = maestroDePrueba('Stage');
    $maestro->desplegar();

    // La segunda se desplegó igual, pese a que la primera falló.
    expect(array_column($maestro->llamados, 'clase'))
        ->toBe(['Modules\Invoice\Database\Seeders\Invoice\InvoiceInvoiceStageSeeder']);

    // Y al cerrar, el fallo sigue estando: acumular no es tragar.
    expect(fn () => $maestro->cerrar())
        ->toThrow(RuntimeException::class, 'Invoice/NoGenerada');
});

it('una carpeta declarada que nadie generó se rechaza nombrándola', function () {
    config()->set('make-module.deploy', ['Invoice/NoGenerada']);

    $maestro = maestroDePrueba('Stage');
    $maestro->desplegar();

    expect(fn () => $maestro->cerrar())->toThrow(
        RuntimeException::class,
        'no tiene su seeder de Stage'
    );
});

it('sin orden declarado avisa, en vez de desplegar nada en silencio', function () {
    config()->set('make-module.deploy', []);

    $maestro = maestroDePrueba('Stage');
    $maestro->desplegar();

    expect($maestro->llamados)->toBe([]);
    $maestro->cerrar();   // no hay fallo que reportar: no desplegar nada no es un error
});

it('el módulo con sus maestros sigue siendo coherente', function () {
    $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')->assertCoherent();
});
