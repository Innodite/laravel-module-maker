<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\DryRun;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El ensayo: lo que un comando HARÍA, sin que haga nada.
 *
 * Un generador escribe decenas de archivos por el proyecto del usuario y un despliegue corre
 * seeders contra una base real. Las dos cosas merecen verse antes, y por el mismo motivo por el que
 * existe el paquete entero: lo que producen se multiplica por cada módulo de cada proyecto, así que
 * el momento barato para notar un contexto equivocado es antes del primer archivo.
 *
 * Lo que estas pruebas fijan no es que la opción exista, sino que **no haya una segunda vía**: una
 * sola escritura que se salte la compuerta convierte el ensayo en una mentira, y encima silenciosa
 * —el comando dice «escribiría» y el archivo ya está—.
 */

afterEach(function () {
    // El interruptor es del proceso: una prueba que lo deje puesto convierte a la siguiente en un
    // ensayo, y esa falla después y en otro sitio.
    DryRun::reset();
});

it('make-module en ensayo no escribe ni un archivo, y enumera lo que escribiría', function () {
    $codigo = Artisan::call('innodite:make-module', [
        'name'             => 'Invoice',
        '--context'        => 'central',
        '--no-routes'      => true,
        '--dry-run'        => true,
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect($codigo)->toBe(0, "El ensayo falló:\n{$salida}");

    expect(File::isDirectory($this->tempPath('Modules/Invoice')))->toBeFalse(
        "FALLA: el ensayo escribió en disco. · FIX: toda escritura pasa por Disk, que consulta el "
        . "interruptor; la que no pasa por ahí es la que rompe la promesa.\n{$salida}"
    );

    expect(str_contains($salida, 'ENSAYO'))->toBeTrue(
        "FALLA: no se avisa de que es un ensayo antes de correr nada.\n{$salida}"
    );

    foreach (['Controller', 'Service', 'Repository', 'Models'] as $pieza) {
        expect(str_contains($salida, $pieza))->toBeTrue(
            "FALLA: el ensayo no dice que escribiría el {$pieza}. · FIX: «escribiría 41 archivos» no "
            . "es una vista previa; lo que hay que poder mirar es CUÁLES.\n{$salida}"
        );
    }
});

it('el interruptor se apaga al terminar: el comando siguiente sí escribe', function () {
    // El precio de tener una sola compuerta en vez de un booleano paseado por veinte firmas: el
    // interruptor vive en el proceso. Si un ensayo lo dejara puesto, el comando de después no
    // escribiría nada y diría que todo fue bien.
    Artisan::call('innodite:make-module', [
        'name'             => 'Invoice',
        '--context'        => 'central',
        '--no-routes'      => true,
        '--dry-run'        => true,
        '--no-interaction' => true,
    ]);

    expect(DryRun::active())->toBeFalse('FALLA: el ensayo quedó encendido al terminar el comando.');

    Artisan::call('innodite:make-module', [
        'name'             => 'Invoice',
        '--context'        => 'central',
        '--no-routes'      => true,
        '--no-interaction' => true,
    ]);

    expect(File::isDirectory($this->tempPath('Modules/Invoice')))->toBeTrue(
        'FALLA: la ejecución de verdad, después de un ensayo, tampoco escribió. · FIX: el ensayo se '
        . 'apaga en un `finally`, pase lo que pase.'
    );
});

it('el despliegue en ensayo dice qué correría y no toca la base', function () {
    // Es el comando donde más falta hacía y el único de los cinco que no tenía la opción: los otros
    // escriben archivos, que se borran; este corre un seeder contra una base real, y `stage` con el
    // modo destructivo puesto reconstruye tablas desde cero.
    $this->withMode(ModuleMode::SingleApp);

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    cargarSeederDelProyecto('InnoditeDeploySeeder');

    Artisan::call('innodite:deploy', [
        'environment'      => 'production',
        '--dry-run'        => true,
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect(str_contains($salida, 'ejecutaría'))->toBeTrue(
        "FALLA: el ensayo del despliegue no dice qué ejecutaría.\n{$salida}"
    );

    expect(str_contains($salida, 'Nada de lo anterior se ha hecho'))->toBeTrue(
        "FALLA: no queda claro que no se hizo nada.\n{$salida}"
    );
});

it('la opción está en los que escriben o tocan una base, y en ninguno más', function () {
    // D2. Ponerla en los diez habría quedado más simétrico y habría sido peor: una opción que no
    // cambia nada enseña que la opción no cambia nada, y entonces se omite el día que sí importaba.
    $comandos = Artisan::all();

    $conEnsayo = [
        'innodite:make-module',      // escribe el módulo entero
        'innodite:add-entity',       // escribe la subfuncionalidad nueva
        'innodite:module-setup',     // escribe la instalación en el proyecto
        'innodite:publish-frontend', // escribe en resources/js
        'innodite:deploy',           // corre seeders contra una base real
        'innodite:migrate-one',      // ya la tenía: aplica una migración
        'innodite:migrate-plan',     // ya la tenía: aplica el plan
        'innodite:crear-bd-test',    // crea una base de datos
    ];

    $sinEnsayo = [
        'innodite:doctor',           // diagnostica — los dos que había se fusionaron aquí
        'innodite:test',             // ejecuta pruebas, no escribe el proyecto
    ];

    foreach ($conEnsayo as $nombre) {
        expect(isset($comandos[$nombre]))->toBeTrue("El comando {$nombre} no está registrado.");
        expect($comandos[$nombre]->getDefinition()->hasOption('dry-run'))->toBeTrue(
            "FALLA: {$nombre} escribe o despliega y no admite --dry-run."
        );
    }

    foreach ($sinEnsayo as $nombre) {
        expect(isset($comandos[$nombre]))->toBeTrue("El comando {$nombre} no está registrado.");
        expect($comandos[$nombre]->getDefinition()->hasOption('dry-run'))->toBeFalse(
            "FALLA: {$nombre} solo lee, así que su --dry-run no previene nada."
        );
    }
});
