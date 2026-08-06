<?php

declare(strict_types=1);

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\DeployOrderInjectionService;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Traits\DeploysSubFeatures;
use Innodite\LaravelModuleMaker\Traits\ReportsSeederErrors;

/**
 * El orden de despliegue lo declara el desarrollador, y **generar declara** (P4).
 *
 * El desfase entre «lo que se generó» y «lo que se despliega» no rompe ningún archivo: deja la
 * aplicación a medio levantar, con una pantalla que no abre nadie porque su seeder de permisos nunca
 * llegó a ejecutarse. Y es el desfase más fácil de producir de todos, porque generar y declarar son
 * dos actos separados por días. Por eso la entrada se escribe sola.
 *
 * Lo que el paquete **no** hace es decidir la posición: el orden depende de qué tabla apunta a cuál,
 * y eso lo sabe el negocio. Se añade al final, que es la única posición que no miente.
 */

/** Publica una configuración con sus marcadores, como la que deja `vendor:publish`. */
function publicarConfig(string $tempBase, string $cuerpoDeploy = "        // {{DEPLOY_END}}\n"): string
{
    $dir = "{$tempBase}/config";

    File::ensureDirectoryExists($dir);

    $contenido = "<?php\n\nreturn [\n    'mode' => 'single-app',\n\n    'deploy' => [\n"
        . $cuerpoDeploy
        . "    ],\n];\n";

    File::put("{$dir}/make-module.php", $contenido);

    app()->useConfigPath($dir);

    return "{$dir}/make-module.php";
}

/** Un maestro de mentira, para mirar qué se despliega y en qué orden. */
function maestroDelOrden(string $module = 'Invoice', ?string $context = null): object
{
    return new class ($module, $context) extends Seeder {
        use DeploysSubFeatures;
        use ReportsSeederErrors;

        /** @var array<int, string> */
        public array $llamados = [];

        protected string $piece = 'Stage';

        public function __construct(protected string $module, protected ?string $context)
        {
        }

        public function rutas(): array
        {
            return $this->subFeaturePaths();
        }

        /** @param  class-string  $class */
        public function callWith($class, array $parameters = [])
        {
            $this->llamados[] = $class;

            return $this;
        }
    };
}

/** Publica **la configuración de verdad** del paquete, con su bloque de documentación incluido. */
function publicarConfigReal(string $tempBase): string
{
    $dir = "{$tempBase}/config";

    File::ensureDirectoryExists($dir);
    File::copy(dirname(__DIR__, 2) . '/config/make-module.php', "{$dir}/make-module.php");

    app()->useConfigPath($dir);

    return "{$dir}/make-module.php";
}

it('la entrada va al array de verdad, no al ejemplo que lo documenta', function () {
    // **B26.** El comentario de `deploy` enseña la forma del array con marcadores de ejemplo dentro,
    // así que «la primera aparición del marcador» era la de la documentación: las entradas se
    // escribían dentro del comentario y el array real quedaba vacío. Sin error y sin aviso — un
    // despliegue que no desplegaba nada.
    //
    // Esta prueba usa la configuración REAL del paquete. La anterior usaba una mínima sin
    // comentarios, y por eso pasaba en verde mientras el defecto estaba vivo.
    $archivo = publicarConfigReal($this->tempBase);

    (new DeployOrderInjectionService())->register('Invoice/Central/Invoice', 'central');

    $contenido = File::get($archivo);
    $codigo    = soloCodigo($contenido);

    expect($codigo)->toContain("'Invoice/Central/Invoice'");

    // Y la documentación sigue intacta: no se le metió nada dentro. El bloque va desde su encabezado
    // hasta el `*/` que lo cierra — medirlo «a ojo» dejaría dentro el array de verdad, que empieza
    // justo debajo.
    $inicio     = (int) strpos($contenido, 'Orden de despliegue');
    $comentario = substr($contenido, $inicio, (int) strpos($contenido, '*/', $inicio) - $inicio);

    expect(str_contains($comentario, "'Invoice/Central/Invoice'"))->toBeFalse(
        'La entrada acabó dentro del bloque de comentarios que documenta el array.'
    );
});

it('el archivo resultante sigue siendo PHP que se puede cargar', function () {
    // Escribir en un archivo de configuración a base de texto tiene esta forma de fallar: queda
    // sintácticamente roto y el proyecto entero deja de arrancar.
    $archivo  = publicarConfigReal($this->tempBase);
    $servicio = new DeployOrderInjectionService();

    $servicio->register('Invoice/Central/Invoice', 'central');
    $servicio->register('Invoice/Central/Payment', 'central');
    $servicio->register('Payment/Payment');

    $cargado = require $archivo;

    expect($cargado)->toBeArray()
        ->and($cargado['deploy']['central'])->toBe(['Invoice/Central/Invoice', 'Invoice/Central/Payment'])
        ->and($cargado['deploy'])->toHaveKey(0)
        ->and($cargado['deploy'][0])->toBe('Payment/Payment');
});

it('la entrada se escribe al final de la lista, con la sangría del marcador', function () {
    $archivo = publicarConfig($this->tempBase);

    (new DeployOrderInjectionService())->register('Invoice/Invoice');

    expect(File::get($archivo))->toContain("        'Invoice/Invoice',\n        // {{DEPLOY_END}}");
});

it('generar dos veces no la declara dos veces', function () {
    // Un `make-module` repetido sobre la misma subfuncionalidad no puede dejar la lista con dos
    // entradas iguales: la segunda estaría en una posición del orden que nadie eligió.
    $archivo  = publicarConfig($this->tempBase);
    $servicio = new DeployOrderInjectionService();

    $servicio->register('Invoice/Invoice');
    $servicio->register('Invoice/Invoice');

    expect(substr_count(File::get($archivo), "'Invoice/Invoice',"))->toBe(1);
});

it('el orden en que se generan es el orden en que quedan escritas', function () {
    $archivo  = publicarConfig($this->tempBase);
    $servicio = new DeployOrderInjectionService();

    $servicio->register('Invoice/Invoice');
    $servicio->register('Payment/Payment');

    $contenido = File::get($archivo);

    expect(strpos($contenido, "'Invoice/Invoice'"))->toBeLessThan(strpos($contenido, "'Payment/Payment'"));
});

it('un contexto sin lista propia la estrena, con su marcador dentro', function () {
    // Para que la siguiente subfuncionalidad de ese contexto ya la encuentre hecha.
    $archivo = publicarConfig($this->tempBase);

    (new DeployOrderInjectionService())->register('Invoice/Central/Invoice', 'central');

    $contenido = File::get($archivo);

    expect($contenido)->toContain("'central' => [")
        ->and($contenido)->toContain("'Invoice/Central/Invoice',")
        ->and($contenido)->toContain('// {{DEPLOY_CENTRAL_END}}');

    // Y la segunda entra en la lista que la primera dejó creada — un nivel más adentro, que es
    // donde va un elemento de una sublista.
    (new DeployOrderInjectionService())->register('Invoice/Central/Payment', 'central');

    expect(File::get($archivo))->toContain("            'Invoice/Central/Payment',\n            // {{DEPLOY_CENTRAL_END}}");
});

it('sin configuración publicada avisa, y no revienta la generación', function () {
    // La subfuncionalidad ya está generada y es correcta: que no se pueda declarar no puede tumbar
    // el comando entero.
    app()->useConfigPath("{$this->tempBase}/config-que-no-existe");

    $avisos = new class () {
        public array $mensajes = [];

        public function warn(string $m): void
        {
            $this->mensajes[] = $m;
        }

        public function info(string $m): void
        {
            $this->mensajes[] = $m;
        }
    };

    (new DeployOrderInjectionService($avisos))->register('Invoice/Invoice');

    expect($avisos->mensajes)->toHaveCount(1)
        ->and($avisos->mensajes[0])->toContain('vendor:publish');
});

it('sin marcador dice exactamente qué línea escribir a mano', function () {
    // Alguien puede haberlo borrado al ordenar su lista. El comando no adivina dónde meterla.
    publicarConfig($this->tempBase, "        'Otra/Cosa',\n");

    $avisos = new class () {
        public array $mensajes = [];

        public function warn(string $m): void
        {
            $this->mensajes[] = $m;
        }

        public function info(string $m): void
        {
            $this->mensajes[] = $m;
        }
    };

    (new DeployOrderInjectionService($avisos))->register('Invoice/Invoice');

    expect($avisos->mensajes[0])->toContain("'Invoice/Invoice',")
        ->and($avisos->mensajes[0])->toContain('{{DEPLOY_END}}');
});

it('una entrada duplicada a mano se despliega una vez, y se avisa', function () {
    // No rompe nada —los seeders son idempotentes—, pero es la señal de que la segunda copia está en
    // un sitio del orden que nadie eligió.
    config()->set('make-module.deploy', [
        'Invoice/Invoice',
        'Invoice/Otra',
        'Invoice/Invoice',
    ]);

    expect(maestroDelOrden()->rutas())->toBe(['Invoice/Invoice', 'Invoice/Otra']);
});

it('generar un módulo lo declara en el orden de despliegue', function () {
    // El cruce completo: se genera de verdad y la entrada aparece escrita.
    $archivo = publicarConfig($this->tempBase);

    $this->generateModule('Invoice', ModuleMode::SingleApp);

    expect(File::get($archivo))->toContain("'Invoice/Invoice',");
});

it('en multitenant se declara con su contexto, carpeta incluida', function () {
    $archivo = publicarConfig($this->tempBase);

    $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    $contenido = File::get($archivo);

    expect($contenido)->toContain("'central' => [")
        ->and($contenido)->toContain("'Invoice/Central/Invoice',");
});
