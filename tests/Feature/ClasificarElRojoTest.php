<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\PhpunitRunner;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * R81 — un rojo se **clasifica** antes de investigarse.
 *
 * Significa tres cosas distintas y las tres se ven igual: base sucia, prueba intermitente o defecto
 * de verdad. Empezar por la tercera —el reflejo natural— convierte una base contaminada en horas de
 * depuración sobre código correcto. Este comando descarta las dos primeras por orden de coste, y
 * cuando la que pasa es la primera **no dice verde**: dice «pasó tras re-clonar», que es una señal de
 * que algo sigue ensuciando la base.
 */

/** Un ejecutor que falla las primeras N veces de una pieza y pasa a partir de ahí. */
function ejecutorQueSeCura(string $pieza, int $fallosAntesDeCurarse): PhpunitRunner
{
    return new class($pieza, $fallosAntesDeCurarse) extends PhpunitRunner
    {
        public int $vistas = 0;

        public function __construct(private string $pieza, private int $fallos)
        {
        }

        public function ejecutar(string $archivo, ?string $filtro = null): array
        {
            if (! str_contains($archivo, $this->pieza)) {
                return ['ok' => true, 'salida' => ''];
            }

            $this->vistas++;

            return [
                'ok'     => $this->vistas > $this->fallos,
                'salida' => "intento {$this->vistas} de {$this->pieza}",
            ];
        }
    };
}

/** Un ejecutor que falla siempre una pieza. */
function ejecutorQueSiempreFalla(string $pieza): PhpunitRunner
{
    return ejecutorQueSeCura($pieza, PHP_INT_MAX);
}

/** Módulo real con su grupo, y una base de pruebas en archivo para que el re-clonado sea de verdad. */
function moduloYBaseDePruebas(): array
{
    $raiz = base_path('Modules');
    File::deleteDirectory($raiz);

    config()->set('make-module.module_path', $raiz);
    config()->set('make-module.mode', ModuleMode::SingleApp->value);

    Artisan::call('innodite:make-module', [
        'name' => 'Ledger', '--no-routes' => true, '--no-interaction' => true,
    ]);

    return ['Ledger', "{$raiz}/Ledger/Tests/Feature/Ledger"];
}

/**
 * Deja la conexión por defecto apuntando a una base de pruebas **en archivo**, con su base real al
 * lado — que es lo que hace posible re-clonar de verdad durante la clasificación.
 */
function conBaseDePruebasEnArchivo(string $carpeta): void
{
    File::ensureDirectoryExists($carpeta);
    File::put("{$carpeta}/negocio.sqlite", '');
    File::put("{$carpeta}/negocio_test.sqlite", '');

    config()->set('database.connections.negocio_test', [
        'driver' => 'sqlite', 'database' => "{$carpeta}/negocio_test.sqlite", 'prefix' => '',
    ]);
    config()->set('database.default', 'negocio_test');
    DB::purge('negocio_test');
}

afterEach(function () {
    File::deleteDirectory(base_path('Modules'));
});

it('el rojo que se cura al re-clonar se reporta como «pasó tras re-clonar», no como verde', function () {
    // El caso de kapitalizando: la misma pareja falló tres veces por contaminación antes de que
    // alguien lo anotara. Si el comando dijera «verde» tras limpiar, esa reincidencia no existiría
    // para nadie — y el defecto de fondo seguiría sin encontrarse.
    [$modulo] = moduloYBaseDePruebas();
    conBaseDePruebasEnArchivo($this->tempPath('bd'));

    $this->app->instance(PhpunitRunner::class, ejecutorQueSeCura('LedgerSchemaTest', 1));

    $codigo = Artisan::call('innodite:test', [
        'module' => $modulo, 'subfeature' => $modulo, '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect(str_contains($salida, 'Clasificando el rojo'))->toBeTrue(
        "FALLA: no clasifica el rojo, va directo a darlo por roto.\n{$salida}"
    );

    expect(str_contains($salida, 'pasó tras re-clonar'))->toBeTrue(
        "FALLA: no dice que solo pasó después de limpiar.\n{$salida}"
    );

    expect($codigo)->toBe(
        1,
        "FALLA: lo dio por bueno. · FIX: «pasó tras re-clonar» no es un aprobado — algo está "
        . "ensuciando la base y sigue ahí.\n{$salida}"
    );
});

it('con --sin-reclonar no toca la base, que es justo lo que se está investigando', function () {
    [$modulo] = moduloYBaseDePruebas();
    conBaseDePruebasEnArchivo($this->tempPath('bd'));

    $this->app->instance(PhpunitRunner::class, ejecutorQueSeCura('LedgerSchemaTest', 1));

    Artisan::call('innodite:test', [
        'module'           => $modulo,
        'subfeature'       => $modulo,
        '--sin-reclonar'   => true,
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect(str_contains($salida, 'se pidió --sin-reclonar'))->toBeTrue(
        "FALLA: re-clonó igual.\n{$salida}"
    );

    expect(str_contains($salida, 'pasó tras re-clonar'))->toBeFalse(
        "FALLA: dice que re-clonó cuando se le pidió que no.\n{$salida}"
    );
});

it('--repetir distingue la prueba intermitente de la rota', function () {
    // El caso real del informe: un test que falló 2 de 3 corridas porque ordenaba sin desempate. No
    // es la base y no es el código: es la prueba.
    [$modulo] = moduloYBaseDePruebas();

    conBaseDePruebasEnArchivo($this->tempPath('bd'));

    // Falla la primera (el rojo), sigue roja tras re-clonar (la segunda), y a partir de ahí pasa:
    // eso es exactamente lo que se ve cuando algo depende del orden.
    $this->app->instance(PhpunitRunner::class, ejecutorQueSeCura('LedgerSchemaTest', 2));

    Artisan::call('innodite:test', [
        'module'           => $modulo,
        'subfeature'       => $modulo,
        '--repetir'        => 3,
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect(str_contains($salida, '¿intermitente'))->toBeTrue("FALLA: no comprueba la intermitencia.\n{$salida}");
    expect(str_contains($salida, 'SÍ — pasó'))->toBeTrue("FALLA: no la reconoce como intermitente.\n{$salida}");
});

it('lo que no es la base ni intermitencia se declara defecto, y solo entonces', function () {
    [$modulo] = moduloYBaseDePruebas();

    $this->app->instance(PhpunitRunner::class, ejecutorQueSiempreFalla('LedgerSchemaTest'));

    $codigo = Artisan::call('innodite:test', [
        'module'           => $modulo,
        'subfeature'       => $modulo,
        '--repetir'        => 3,
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect($codigo)->toBe(1);
    expect(str_contains($salida, '¿defecto'))->toBeTrue("FALLA: no llega a la tercera hipótesis.\n{$salida}");
    expect(str_contains($salida, 'aquí sí se abre el código'))->toBeTrue(
        "FALLA: no dice que ahora sí toca depurar.\n{$salida}"
    );
});

it('⛔ se niega a correr contra una base que no es de pruebas', function () {
    // La guarda que no tiene excepción: una suite apuntada a la base real no falla — pasa, y de
    // camino se lleva datos por delante.
    [$modulo] = moduloYBaseDePruebas();

    config()->set('database.connections.produccion', [
        'driver' => 'sqlite', 'database' => $this->tempPath('bd/negocio.sqlite'), 'prefix' => '',
    ]);
    config()->set('database.default', 'produccion');

    $codigo = Artisan::call('innodite:test', [
        'module' => $modulo, 'subfeature' => $modulo, '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect($codigo)->toBe(1);
    expect(str_contains($salida, 'no es una base de pruebas'))->toBeTrue(
        "FALLA: corrió el contrato contra una base que no termina en _test.\n{$salida}"
    );
});

it('si la base de pruebas está desfasada respecto de la real, la reclona antes de empezar', function () {
    // Una `_test` clonada hace dos migraciones está verde sobre un esquema que nadie ejecuta. Ese
    // verde es peor que un rojo: es el despliegue que va a fallar con las pruebas en verde.
    [$modulo] = moduloYBaseDePruebas();

    $carpeta = $this->tempPath('bd');
    File::ensureDirectoryExists($carpeta);
    File::put("{$carpeta}/negocio.sqlite", '');
    File::put("{$carpeta}/negocio_test.sqlite", '');

    config()->set('database.connections.negocio_test', [
        'driver' => 'sqlite', 'database' => "{$carpeta}/negocio_test.sqlite", 'prefix' => '',
    ]);
    config()->set('database.default', 'negocio_test');
    DB::purge('negocio_test');

    // La real tiene una tabla que la de pruebas no: eso es estar desfasada.
    config()->set('database.connections.real_aparte', [
        'driver' => 'sqlite', 'database' => "{$carpeta}/negocio.sqlite", 'prefix' => '',
    ]);
    DB::purge('real_aparte');
    DB::connection('real_aparte')->statement('CREATE TABLE invoices (id TEXT PRIMARY KEY)');

    $this->app->instance(PhpunitRunner::class, ejecutorQueSeCura('NoFallaNadie', 0));

    Artisan::call('innodite:test', [
        'module' => $modulo, 'subfeature' => $modulo, '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect(str_contains($salida, 'no coincide con el de la base real'))->toBeTrue(
        "FALLA: arrancó el contrato contra una base desfasada.\n{$salida}"
    );

    DB::purge('negocio_test');
    expect(DB::connection('negocio_test')->select(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='invoices'"
    ))->not->toBe([], 'FALLA: dijo que reclonaba y la tabla nueva no llegó.');
});
