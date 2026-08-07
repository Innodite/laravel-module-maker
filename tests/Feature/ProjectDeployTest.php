<?php

declare(strict_types=1);

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Innodite\LaravelModuleMaker\Traits\DeploysProject;
use Innodite\LaravelModuleMaker\Traits\ReportsSeederErrors;

/**
 * El despliegue del proyecto: quien lee el orden de punta a punta.
 *
 * Hasta aquí cada maestro leía **solo lo suyo**. La lista es del proyecto entero, así que levantarlo
 * era acordarse de llamar a los maestros uno a uno y en el orden correcto — justo lo que la lista
 * viene a evitar. Lo que estas pruebas vigilan es lo que de verdad puede salir mal en ese escalón:
 *
 *   1. Que un despliegue de producción acabe invocando el maestro de **stage**, el que reconstruye
 *      desde cero. Aquí no se evita con un `if`: el seeder no nombra a ningún maestro, los deriva.
 *   2. Que aparezca un **segundo listado** —el de módulos— que el día que nazca el módulo siguiente
 *      se quede atrás.
 *   3. Que una subfuncionalidad generada y **no declarada** no la despliegue nadie, en silencio.
 *   4. Que un módulo que falla se lleve por delante a los que vienen detrás.
 */

/** Un despliegue de mentira: el trait de verdad, con las llamadas y los avisos anotados. */
function despliegueDePrueba(array $contexts = []): object
{
    return new class ($contexts) extends Seeder {
        use DeploysProject;
        use ReportsSeederErrors;

        /** @var array<int, string> */
        public array $llamados = [];

        /** @var array<int, string> */
        public array $avisos = [];

        /** @param array<int, string> $contexts */
        public function __construct(protected array $contexts)
        {
        }

        public function desplegar(string $piece): void
        {
            $this->runMasters($piece);
        }

        /** @return array<int, string> */
        public function maestros(string $piece): array
        {
            return $this->masterClasses($piece);
        }

        public function cruzar(): void
        {
            $this->reportCoverage();
        }

        public function cerrar(): void
        {
            $this->reportErrors();
        }

        /** @param  class-string  $class */
        public function callWith($class, array $parameters = [])
        {
            $this->llamados[] = $class;

            return $this;
        }

        protected function say(string $message, string $level = 'info'): void
        {
            $this->avisos[] = $message;
        }
    };
}

/** Deja un `DatabaseSeeder.php` como el que trae un proyecto Laravel recién creado. */
function databaseSeederDelProyecto(): string
{
    $ruta = database_path('seeders/DatabaseSeeder.php');

    File::ensureDirectoryExists(dirname($ruta));

    File::put($ruta, <<<'PHP'
        <?php

        namespace Database\Seeders;

        use Illuminate\Database\Seeder;

        class DatabaseSeeder extends Seeder
        {
            public function run(): void
            {
                //
            }
        }
        PHP);

    return $ruta;
}

// ── Lo que escribe el instalador ────────────────────────────────────────────────────────────

it('en una aplicación única escribe un solo despliegue', function () {
    // No hay dos contextos que separar ni dos bases que llenar: un segundo seeder sería un archivo
    // que nadie ejecuta y que, como no se sobreescribe, se queda ahí para siempre.
    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    expect(File::exists(database_path('seeders/InnoditeDeploySeeder.php')))->toBeTrue()
        ->and(File::exists(database_path('seeders/InnoditeCentralDeploySeeder.php')))->toBeFalse()
        ->and(File::exists(database_path('seeders/InnoditeTenantDeploySeeder.php')))->toBeFalse();
});

it('en multitenant escribe los dos, y cada uno declara los contextos que le tocan', function () {
    // Son dos despliegues contra dos bases de datos distintas, y el de tenant se ejecuta una vez por
    // tenant. Qué claves del orden cubre cada uno queda escrito en el archivo generado, no en el
    // paquete: en un proyecto donde 'shared' viva en la central, eso hay que poder corregirlo.
    Artisan::call('innodite:module-setup', ['--mode' => 'multitenant-shared', '--no-interaction' => true]);

    expect(File::exists(database_path('seeders/InnoditeDeploySeeder.php')))->toBeFalse();

    expect(File::get(database_path('seeders/InnoditeCentralDeploySeeder.php')))
        ->toContain("protected array \$contexts = ['central'];");

    expect(File::get(database_path('seeders/InnoditeTenantDeploySeeder.php')))
        ->toContain("protected array \$contexts = ['tenant', 'tenant_shared', 'shared'];");
});

it('no sobreescribe lo que el desarrollador ya tenía', function () {
    // El `run()` del despliegue es la secuencia de pasos que se lee y se amplía. Volver a instalar
    // no puede llevarse un paso añadido a mano.
    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    $archivo = database_path('seeders/InnoditeDeploySeeder.php');

    File::put($archivo, File::get($archivo) . "\n// un paso añadido a mano\n");

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    expect(File::get($archivo))->toContain('un paso añadido a mano');
});

it('el despliegue generado carga y se instancia', function () {
    // No basta con que parezca PHP: un `use TraitQueNoExiste;` pasa el parser y revienta al
    // instanciar. Es la lección de B21, aplicada al archivo que levanta el proyecto entero.
    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    cargarSeederDelProyecto('InnoditeDeploySeeder');

    expect(new Database\Seeders\InnoditeDeploySeeder())->toBeInstanceOf(Seeder::class);
});

it('el archivo generado no nombra a ningún maestro, ni al despliegue destructivo', function () {
    // Las dos mitades de «la cadena no se cruza», un nivel por encima del maestro:
    //   · no menciona ninguna clase Application → no puede invocar una en vez de derivarla;
    //   · no menciona 'Stage' → el que reconstruye desde cero solo se ejecuta si se pide.
    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    $codigo = soloCodigo(File::get(database_path('seeders/InnoditeDeploySeeder.php')));

    expect(str_contains($codigo, 'Application'))->toBeFalse(
        'Nombrar un maestro es poder nombrar el equivocado. Se derivan de la carpeta declarada.'
    );

    expect(str_contains($codigo, "'Stage'"))->toBeFalse(
        'El despliegue de stage puede reconstruir desde cero: se pide, no se escribe dentro.'
    );
});

it('engancha el DatabaseSeeder del proyecto, sin importar nada', function () {
    // Viven en el mismo namespace —`Database\Seeders`—, así que un `use` sobraría. Y en multitenant
    // se engancha solo el central: `db:seed` corre contra una base de datos, y el de tenant se
    // ejecuta una vez por tenant, dentro del contexto de cada uno.
    $ruta = databaseSeederDelProyecto();

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    $contenido = File::get($ruta);

    expect($contenido)->toContain('$this->call(InnoditeDeploySeeder::class);')
        ->and(str_contains($contenido, 'use Database\Seeders\InnoditeDeploySeeder;'))->toBeFalse();
});

it('un run() que no reconoce no se toca, y lo dice con la línea exacta', function () {
    // A15: no se anuncia un éxito que no ocurrió. El proyecto puede tener su run() escrito de otra
    // forma, y ahí lo correcto es decir qué añadir, no fingir que se añadió.
    File::put(database_path('seeders/DatabaseSeeder.php'), "<?php\n\nnamespace Database\Seeders;\n\nclass DatabaseSeeder\n{\n    public function run()\n    {\n    }\n}\n");

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    expect(Artisan::output())->toContain('$this->call(InnoditeDeploySeeder::class);');

    expect(File::get(database_path('seeders/DatabaseSeeder.php')))
        ->not->toContain('InnoditeDeploySeeder::class');
});

// ── El fan-out: orden, contexto y una sola llamada por maestro ───────────────────────────────

it('llama a los maestros en el orden declarado, y solo los de sus contextos', function () {
    config()->set('make-module.deploy', [
        'central' => ['User/Central/Role', 'Invoice/Central/Invoice'],
        'tenant'  => ['Billing/Tenant/Shared/Plan'],
    ]);

    expect(despliegueDePrueba(['central'])->maestros('Production'))->toBe([
        'Modules\User\Database\Seeders\Central\Application\CentralUserApplicationProductionSeeder',
        'Modules\Invoice\Database\Seeders\Central\Application\CentralInvoiceApplicationProductionSeeder',
    ]);

    expect(despliegueDePrueba(['tenant', 'tenant_shared', 'shared'])->maestros('Stage'))->toBe([
        'Modules\Billing\Database\Seeders\Tenant\Shared\Application\TenantSharedBillingApplicationStageSeeder',
    ]);
});

it('dos subfuncionalidades del mismo módulo son un solo maestro', function () {
    // El maestro es del módulo y del contexto, no de una subfuncionalidad: él ya despliega a las dos,
    // en el orden de la lista. Invocarlo dos veces las desplegaría dos veces.
    config()->set('make-module.deploy', [
        'central' => ['Invoice/Central/Invoice', 'Invoice/Central/Payment', 'Invoice/Central/Note'],
    ]);

    expect(despliegueDePrueba(['central'])->maestros('Permissions'))->toHaveCount(1);
});

it('el mismo módulo en dos contextos son dos maestros distintos', function () {
    // Viven en carpetas distintas y llevan prefijos distintos, y llenan bases de datos distintas.
    config()->set('make-module.deploy', [
        'central' => ['Invoice/Central/Invoice'],
        'tenant'  => ['Invoice/Tenant/Shared/Invoice'],
    ]);

    expect(despliegueDePrueba(['central', 'tenant'])->maestros('Stage'))->toBe([
        'Modules\Invoice\Database\Seeders\Central\Application\CentralInvoiceApplicationStageSeeder',
        'Modules\Invoice\Database\Seeders\Tenant\Shared\Application\TenantSharedInvoiceApplicationStageSeeder',
    ]);
});

it('sin contextos declarados lee la lista entera', function () {
    // Es el caso de una aplicación única: no hay contexto por el que agrupar porque no hay contextos.
    config()->set('make-module.deploy', ['Invoice/Invoice', 'User/Role']);

    expect(despliegueDePrueba()->maestros('Production'))->toBe([
        'Modules\Invoice\Database\Seeders\Application\InvoiceApplicationProductionSeeder',
        'Modules\User\Database\Seeders\Application\UserApplicationProductionSeeder',
    ]);
});

it('si ninguno de sus contextos está en el orden, avisa en vez de terminar en verde sin hacer nada', function () {
    // El desfase silencioso de siempre: dos declaraciones que dejaron de coincidir. Aquí el síntoma
    // sería un despliegue impecable que no desplegó nada.
    config()->set('make-module.deploy', ['central' => ['Invoice/Central/Invoice']]);

    $despliegue = despliegueDePrueba(['tenant']);

    expect($despliegue->maestros('Stage'))->toBe([]);
    expect(implode("\n", $despliegue->avisos))->toContain('central');
});

it('una pieza que no existe se rechaza, no se adivina', function () {
    // Quien pide 'stage' en minúscula cree estar desplegando algo. Normalizarlo convertiría un error
    // de quien llama en un despliegue que hace otra cosa — y la de stage reconstruye desde cero.
    config()->set('make-module.deploy', ['Invoice/Invoice']);

    expect(fn () => despliegueDePrueba()->desplegar('stage'))
        ->toThrow(InvalidArgumentException::class, 'no es una pieza desplegable');
});

// ── Ejecución de verdad, sobre un módulo generado ────────────────────────────────────────────

it('despliega el módulo generado llamando a su maestro', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    cargarPiezas($modulo, 'Database/Seeders/Application', SeederNames::masterPieces('', 'Invoice'));

    config()->set('make-module.deploy', ['Invoice/Invoice']);

    $despliegue = despliegueDePrueba();
    $despliegue->desplegar('Production');

    expect($despliegue->llamados)
        ->toBe(['Modules\Invoice\Database\Seeders\Application\InvoiceApplicationProductionSeeder']);
});

it('un módulo que falla no detiene a los que vienen detrás', function () {
    // Sin esto, un proyecto de diez módulos se levanta a un despliegue por fallo.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    cargarPiezas($modulo, 'Database/Seeders/Application', SeederNames::masterPieces('', 'Invoice'));

    config()->set('make-module.deploy', ['NoGenerado/Cosa', 'Invoice/Invoice']);

    $despliegue = despliegueDePrueba();
    $despliegue->desplegar('Stage');

    expect($despliegue->llamados)
        ->toBe(['Modules\Invoice\Database\Seeders\Application\InvoiceApplicationStageSeeder']);

    expect(fn () => $despliegue->cerrar())
        ->toThrow(RuntimeException::class, 'NoGenerado');
});

// ── El cruce final: lo generado contra lo declarado ──────────────────────────────────────────

it('avisa, nombrándolas, de las subfuncionalidades que nadie despliega', function () {
    // El desfase no rompe ningún archivo: la subfuncionalidad existe, compila y tiene sus seis
    // piezas. Simplemente no la despliega nadie, y la aplicación queda a medio levantar.
    $this->generateModule('Invoice', ModuleMode::SingleApp);

    config()->set('make-module.deploy', []);

    $despliegue = despliegueDePrueba();
    $despliegue->cruzar();

    $avisos = implode("\n", $despliegue->avisos);

    expect($avisos)->toContain('Invoice/Invoice')
        ->and($avisos)->toContain('sin declarar');

    $despliegue->cerrar();   // es un aviso: lo desplegado se desplegó bien
});

it('cuando todo lo generado está declarado, no avisa de nada', function () {
    $this->generateModule('Invoice', ModuleMode::SingleApp);

    config()->set('make-module.deploy', ['Invoice/Invoice']);

    $despliegue = despliegueDePrueba();
    $despliegue->cruzar();

    expect($despliegue->avisos)->toBe([]);
});

it('la carpeta de los maestros no cuenta como subfuncionalidad', function () {
    // Sus archivos también terminan en `Seeder.php` —`…ApplicationStageSeeder`—, así que contarla
    // declararía como subfuncionalidad al módulo entero.
    $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    expect(Innodite\LaravelModuleMaker\Support\DeployCoverage::onDisk())
        ->toBe(['Invoice/Central/Invoice']);
});

it('avisa también de lo declarado que nadie generó', function () {
    // El maestro ya rechaza una carpeta que no existe cuando le toca desplegarla; esto la ve cuando
    // el maestro no llega a ejecutarse siquiera.
    config()->set('make-module.deploy', ['Fantasma/Cosa']);

    $despliegue = despliegueDePrueba();
    $despliegue->cruzar();

    expect(implode("\n", $despliegue->avisos))->toContain('Fantasma/Cosa');
});

// ── El comando ──────────────────────────────────────────────────────────────────────────────

it('rechaza un entorno que no es stage ni production', function () {
    Artisan::call('innodite:deploy', ['entorno' => 'preprod', '--no-interaction' => true]);

    expect(Artisan::output())->toContain('stage | production');
});

it('en multitenant exige el contexto, y dice cuáles hay', function () {
    // Son dos bases de datos distintas: desplegar el contexto equivocado llena la que no era.
    $salida = Artisan::call('innodite:deploy', ['entorno' => 'production', '--no-interaction' => true]);

    expect($salida)->toBe(1);
    expect(Artisan::output())->toContain('--context=central');
});

it('en una aplicación única rechaza el contexto, en vez de ignorarlo', function () {
    $this->withMode(ModuleMode::SingleApp);

    Artisan::call('innodite:deploy', [
        'entorno'          => 'production',
        '--context'        => 'central',
        '--no-interaction' => true,
    ]);

    expect(Artisan::output())->toContain('no tiene contextos');
});

it('el comando arranca de verdad el seeder del proyecto', function () {
    // El circuito completo, sin base de datos: el comando encuentra la clase, la instancia, le pasa
    // la pieza y ejecuta su `run()`. Con el orden vacío no hay nada que desplegar, así que lo que se
    // ve es el aviso — que es la salida correcta, no un fallo.
    $this->withMode(ModuleMode::SingleApp);

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    cargarSeederDelProyecto('InnoditeDeploySeeder');

    config()->set('make-module.deploy', []);

    Artisan::call('innodite:deploy', ['entorno' => 'stage', '--no-interaction' => true]);

    expect(Artisan::output())->toContain('desplegando el proyecto (Stage)');
});

it('con el orden vacío, el despliegue termina en verde', function () {
    // No desplegar nada no es un error: es un proyecto recién instalado. Necesita base de datos
    // porque el paso del webmaster la abre para ver si las tablas de permisos ya existen.
    requiereBaseDeDatos();

    $this->withMode(ModuleMode::SingleApp);

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    cargarSeederDelProyecto('InnoditeDeploySeeder');

    // Y el del webmaster, que es **el otro paso** del despliegue: el instalador escribe los dos, y el
    // seeder de despliegue instancia el segundo por su FQCN. Cargar solo el primero dejaba el
    // despliegue terminando en 1 con «Target class does not exist» — el fallo que esta prueba
    // llevaba escondiendo desde F3, porque sin `pdo_sqlite` se saltaba entera.
    cargarSeederDelProyecto('WebmasterSeeder');

    config()->set('make-module.deploy', []);

    expect(Artisan::call('innodite:deploy', ['entorno' => 'stage', '--no-interaction' => true]))->toBe(
        0,
        'FALLA: desplegar un proyecto sin nada declarado termina en error. · FIX: no desplegar nada '
        . 'no es un fallo, es un proyecto recién instalado; el despliegue debe avisar y salir en '
        . 'verde. Lee la salida: si dice «Target class does not exist», falta cargar una de las dos '
        . "piezas que escribe el instalador.\n\n" . Artisan::output()
    );
});

it('sin el seeder de despliegue dice quién lo escribe', function () {
    // Con el contexto central, que ninguna otra prueba carga en memoria: las clases requeridas por
    // una prueba siguen declaradas en las siguientes, y `class_exists` las encontraría.
    Artisan::call('innodite:deploy', [
        'entorno'          => 'production',
        '--context'        => 'central',
        '--no-interaction' => true,
    ]);

    expect(Artisan::output())->toContain('innodite:module-setup');
});
