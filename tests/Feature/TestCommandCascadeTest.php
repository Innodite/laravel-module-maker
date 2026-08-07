<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\PhpunitRunner;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\TestNames;

/**
 * El contrato en cascada: el orden, y el corte al primer fallo.
 *
 * **Lo que aquí NO se prueba, a propósito, es que PHPUnit funcione.** Lanzar un PHPUnit de verdad
 * dentro de otro tarda minutos, necesita el proyecto anfitrión montado, y lo que demostraría es que
 * el binario de terceros hace su trabajo. Lo que sí decide este comando —y lo único que puede
 * romperse aquí— es **en qué orden lanza las piezas y cuándo deja de lanzarlas**.
 *
 * Por eso el ejecutor se sustituye por un doble que anota lo que le piden y devuelve el resultado
 * que la prueba decida. Es la razón de que `PhpunitRunner` sea una clase entera para una llamada a
 * `Process`: la frontera con el exterior, aislada, es lo que hace comprobable lo de dentro.
 */

/** Un ejecutor que no ejecuta: anota lo que le piden y responde lo que se le diga. */
function ejecutorFalso(array $fallan = []): PhpunitRunner
{
    return new class($fallan) extends PhpunitRunner
    {
        /** @var array<int, string> */
        public array $lanzados = [];

        public function __construct(private array $fallan)
        {
        }

        public function ejecutar(string $archivo, ?string $filtro = null): array
        {
            $pieza = basename($archivo, '.php');

            $this->lanzados[] = $pieza;

            return [
                'ok'     => ! in_array($pieza, $this->fallan, true),
                'salida' => "salida simulada de {$pieza}",
            ];
        }
    };
}

/** Genera un módulo real y devuelve su grupo de pruebas ya escrito por el generador. */
function moduloConGrupo(string $nombre = 'Ledger'): array
{
    $raiz = base_path('Modules');

    File::deleteDirectory($raiz);

    config()->set('make-module.module_path', $raiz);
    config()->set('make-module.mode', ModuleMode::SingleApp->value);

    Illuminate\Support\Facades\Artisan::call('innodite:make-module', [
        'name'             => $nombre,
        '--no-routes'      => true,
        '--no-interaction' => true,
    ]);

    return [$nombre, "{$raiz}/{$nombre}/Tests/Feature/{$nombre}"];
}

afterEach(function (): void {
    File::deleteDirectory(base_path('Modules'));
});

it('lanza las cinco piezas en el orden de la cascada', function () {
    [$modulo] = moduloConGrupo();

    $ejecutor = ejecutorFalso();
    $this->app->instance(PhpunitRunner::class, $ejecutor);

    $this->artisan('innodite:test', ['module' => $modulo, 'subfeature' => $modulo])
        ->assertSuccessful();

    expect($ejecutor->lanzados)->toBe(
        ['LedgerScaffoldTest', 'LedgerSchemaTest', 'LedgerPermissionsTest', 'LedgerDeploymentTest', 'LedgerHttpTest'],
        'FALLA: el orden de la cascada no es el de dependencia. · FIX: cada pieza da por supuesto lo '
        . 'que comprobó la anterior — andamiaje, esquema, permisos, despliegue y por último el '
        . 'comportamiento. Lanzarlas en otro orden hace que la primera en fallar no sea la causa.'
    );
});

it('al primer fallo corta, y no lanza lo que viene detrás', function () {
    [$modulo] = moduloConGrupo();

    // Falla el esquema: la segunda de cinco. Los permisos, el despliegue y el HTTP fallarían todos
    // por lo mismo —no hay tablas—, y eso son tres pantallazos rojos de una sola causa.
    $ejecutor = ejecutorFalso(['LedgerSchemaTest']);
    $this->app->instance(PhpunitRunner::class, $ejecutor);

    $this->artisan('innodite:test', ['module' => $modulo, 'subfeature' => $modulo])
        ->assertFailed();

    expect($ejecutor->lanzados)->toBe(
        ['LedgerScaffoldTest', 'LedgerSchemaTest'],
        'FALLA: la cascada siguió después de un fallo. · FIX: lo que viene detrás depende de lo que '
        . 'acaba de romperse y fallaría por la misma causa. Treinta rojos de un solo problema '
        . 'cuestan más de leer que uno (R31 · R33).'
    );
});

it('el corte dice qué se quedó sin ejecutar, y por qué', function () {
    [$modulo] = moduloConGrupo();

    $this->app->instance(PhpunitRunner::class, ejecutorFalso(['LedgerScaffoldTest']));

    $this->artisan('innodite:test', ['module' => $modulo, 'subfeature' => $modulo])
        ->expectsOutputToContain('LedgerSchemaTest')
        ->expectsOutputToContain('LedgerHttpTest')
        ->assertFailed();

    // Cortar sin decirlo se lee como «el resto pasó». Lo que se afirma es lo contrario: que el resto
    // no se sabe.
});

it('nombra el tema 6 aunque no lo ejecute', function () {
    [$modulo] = moduloConGrupo();

    $this->app->instance(PhpunitRunner::class, ejecutorFalso());

    $this->artisan('innodite:test', ['module' => $modulo, 'subfeature' => $modulo])
        ->expectsOutputToContain(TestNames::VITEST_SUFFIX)
        ->assertSuccessful();

    // Un contrato «en verde» que se saltó un tema sin decirlo es la clase de silencio que esta fase
    // vino a quitar: el tema 6 lo corre Vitest, y quien lea el verde tiene que saberlo.
});

it('con el grupo incompleto no ejecuta nada, y nombra lo que falta', function () {
    [$modulo, $grupo] = moduloConGrupo();

    File::delete("{$grupo}/LedgerContract.php");

    $ejecutor = ejecutorFalso();
    $this->app->instance(PhpunitRunner::class, $ejecutor);

    $this->artisan('innodite:test', ['module' => $modulo, 'subfeature' => $modulo])
        ->expectsOutputToContain('LedgerContract.php')
        ->assertFailed();

    expect($ejecutor->lanzados)->toBe(
        [],
        'FALLA: se lanzó la cascada sobre un grupo incompleto. · FIX: sin el manifiesto ninguna pieza '
        . 'puede derivar rutas ni permisos; lo que saldría son fallos que describen el síntoma y '
        . 'esconden la causa.'
    );
});

it('un módulo sin grupo lo dice, en vez de dar el contrato por cumplido', function () {
    moduloConGrupo();

    $this->app->instance(PhpunitRunner::class, ejecutorFalso());

    // Una subfuncionalidad que no existe: lo peligroso sería salir en verde por no haber encontrado
    // nada que ejecutar, que es como una suite vacía pasa por una suite que pasa.
    $this->artisan('innodite:test', ['module' => 'Ledger', 'subfeature' => 'NoExiste'])
        ->assertFailed();
});
