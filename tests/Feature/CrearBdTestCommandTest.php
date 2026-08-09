<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * `innodite:crear-bd-test` — la base de pruebas como clon del esquema real, sin una sola fila.
 *
 * El clonado se prueba **de verdad**, contra una base SQLite en archivo: un comando que crea bases de
 * datos y del que solo se comprueban sus guardas es un comando que nadie ha visto correr. Lo que aquí
 * se ejecuta es el mismo camino que corre en MySQL —resolver el destino, validarlo, copiar el DDL
 * tabla a tabla—, con el clonador del driver como única pieza distinta.
 */

/** Deja una base «real» con dos tablas y un índice, y devuelve su ruta. */
function baseRealDePrueba(string $carpeta): string
{
    $ruta = "{$carpeta}/negocio.sqlite";

    File::ensureDirectoryExists($carpeta);
    File::put($ruta, '');

    config()->set('database.connections.negocio', [
        'driver'   => 'sqlite',
        'database' => $ruta,
        'prefix'   => '',
    ]);

    DB::purge('negocio');

    DB::connection('negocio')->statement(
        'CREATE TABLE invoices (id TEXT PRIMARY KEY, total INTEGER NOT NULL)'
    );
    DB::connection('negocio')->statement(
        'CREATE TABLE payments (id TEXT PRIMARY KEY, invoice_id TEXT NOT NULL)'
    );
    DB::connection('negocio')->statement(
        'CREATE INDEX payments_invoice_id_index ON payments (invoice_id)'
    );

    // Con datos dentro, que es lo que NO debe viajar.
    DB::connection('negocio')->insert("INSERT INTO invoices (id, total) VALUES ('uno', 100)");

    return $ruta;
}

it('clona el esquema y no se trae ni una fila', function () {
    $real    = baseRealDePrueba($this->tempPath('bd'));
    $destino = $this->tempPath('bd/negocio_test.sqlite');

    $codigo = Artisan::call('innodite:crear-bd-test', [
        '--connection'     => 'negocio',
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect($codigo)->toBe(0, "FALLA: el clonado falló.\n{$salida}");
    expect(File::exists($destino))->toBeTrue("FALLA: no se creó la base de pruebas.\n{$salida}");

    config()->set('database.connections.clon', [
        'driver' => 'sqlite', 'database' => $destino, 'prefix' => '',
    ]);
    DB::purge('clon');

    $tablas = array_map(
        fn ($f) => $f->name,
        DB::connection('clon')->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )
    );

    expect($tablas)->toBe(['invoices', 'payments'], "FALLA: el esquema no llegó entero.\n{$salida}");

    expect(DB::connection('clon')->select('SELECT * FROM invoices'))->toBe(
        [],
        'FALLA: se copiaron filas. · FIX: el clon lleva estructura y nada más — los datos canónicos '
        . 'los pone el seeder de la subfuncionalidad, que es lo que el contrato prueba.'
    );

    // El índice también viaja: es parte del esquema real, y un índice único ausente convierte un
    // duplicado prohibido en una prueba que pasa.
    $indices = DB::connection('clon')->select("SELECT name FROM sqlite_master WHERE type='index'");
    expect(collect($indices)->pluck('name')->contains('payments_invoice_id_index'))->toBeTrue(
        "FALLA: los índices no se copiaron.\n{$salida}"
    );

    expect(File::exists($real))->toBeTrue('FALLA: se tocó la base real.');
});

it('⛔ aborta si el destino no termina en _test', function () {
    // La única guarda que separa este comando de rehacer la base real. Por eso no tiene excepción.
    $carpeta = $this->tempPath('bd');
    File::ensureDirectoryExists($carpeta);
    File::put("{$carpeta}/negocio_test.sqlite", '');

    config()->set('database.connections.ya_es_test', [
        'driver' => 'sqlite', 'database' => "{$carpeta}/negocio_test.sqlite", 'prefix' => '',
    ]);

    $codigo = Artisan::call('innodite:crear-bd-test', [
        '--connection'     => 'ya_es_test',
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect($codigo)->toBe(1);
    expect(str_contains($salida, 'ya apunta a la base de pruebas'))->toBeTrue(
        "FALLA: clonó una base de pruebas sobre sí misma.\n{$salida}"
    );
});

it('el driver que no sabe clonar lo dice, en vez de inventarse el SQL', function () {
    config()->set('database.connections.postgres', [
        'driver' => 'pgsql', 'database' => 'negocio', 'prefix' => '',
    ]);

    $codigo = Artisan::call('innodite:crear-bd-test', [
        '--connection'     => 'postgres',
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect($codigo)->toBe(1);
    expect(str_contains($salida, "driver 'pgsql'"))->toBeTrue("FALLA: no nombra el driver.\n{$salida}");
    expect(str_contains($salida, 'FIX:'))->toBeTrue("FALLA: no dice qué hacer.\n{$salida}");
});

it('la conexión que no existe se nombra, en vez de reventar', function () {
    $codigo = Artisan::call('innodite:crear-bd-test', [
        '--connection'     => 'fantasma',
        '--no-interaction' => true,
    ]);

    expect($codigo)->toBe(1);
    expect(str_contains(Artisan::output(), "'fantasma'"))->toBeTrue('FALLA: no dice cuál falta.');
});

it('en ensayo enumera las tablas y no crea nada', function () {
    baseRealDePrueba($this->tempPath('bd'));

    $codigo = Artisan::call('innodite:crear-bd-test', [
        '--connection'     => 'negocio',
        '--dry-run'        => true,
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect($codigo)->toBe(0);
    expect(File::exists($this->tempPath('bd/negocio_test.sqlite')))->toBeFalse(
        "FALLA: el ensayo creó la base.\n{$salida}"
    );

    foreach (['invoices', 'payments'] as $tabla) {
        expect(str_contains($salida, $tabla))->toBeTrue(
            "FALLA: el ensayo no dice que copiaría {$tabla}. · FIX: «copiaría 2 tablas» no es una "
            . "vista previa; lo que hay que poder mirar es CUÁLES.\n{$salida}"
        );
    }
});

it('si ya existe no la toca, salvo que se le diga --force', function () {
    baseRealDePrueba($this->tempPath('bd'));
    $destino = $this->tempPath('bd/negocio_test.sqlite');

    Artisan::call('innodite:crear-bd-test', ['--connection' => 'negocio', '--no-interaction' => true]);

    // Algo que solo está en el clon: si sobrevive, es que no se rehízo.
    config()->set('database.connections.clon', [
        'driver' => 'sqlite', 'database' => $destino, 'prefix' => '',
    ]);
    DB::purge('clon');
    DB::connection('clon')->statement('CREATE TABLE marca (id INTEGER)');
    DB::purge('clon');

    Artisan::call('innodite:crear-bd-test', ['--connection' => 'negocio', '--no-interaction' => true]);
    expect(str_contains(Artisan::output(), 'ya existe'))->toBeTrue('FALLA: no avisa de que ya estaba.');

    DB::purge('clon');
    expect(tieneTabla('clon', 'marca'))->toBeTrue('FALLA: la rehízo sin que se lo pidieran.');

    Artisan::call('innodite:crear-bd-test', [
        '--connection'     => 'negocio',
        '--force'          => true,
        '--no-interaction' => true,
    ]);

    DB::purge('clon');
    expect(tieneTabla('clon', 'marca'))->toBeFalse('FALLA: --force no la rehízo desde cero.');
    expect(tieneTabla('clon', 'invoices'))->toBeTrue('FALLA: --force la dejó sin el esquema real.');
});

/** ¿Existe la tabla en esa conexión? */
function tieneTabla(string $conexion, string $tabla): bool
{
    return DB::connection($conexion)->select(
        "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
        [$tabla]
    ) !== [];
}
