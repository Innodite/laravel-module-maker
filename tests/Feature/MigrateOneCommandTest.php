<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Una migración suelta, contra la base de datos de **su** contexto.
 *
 * La coordenada ya lleva encima la carpeta —`Probe:Central/…`—, así que la conexión sale de ahí. El
 * manifiesto añadía un dato de más que decía lo mismo, y cuando los dos discrepaban ganaba el
 * nombre del archivo JSON: se ejecutaba contra otra base sin un aviso.
 */

/** Deja el archivo de una migración donde la coordenada dice que está. */
function migracionEn(string $relativa): void
{
    $ruta = test()->tempPath($relativa);

    File::ensureDirectoryExists(dirname($ruta));
    File::put($ruta, "<?php\n");
}

it('deriva el contexto y la conexión de la propia coordenada', function () {
    migracionEn('Modules/Probe/Database/Migrations/Central/2026_01_01_000001_crea_cosas.php');

    $this->artisan('innodite:migrate-one', [
        'coordinate' => 'Probe:Central/2026_01_01_000001_crea_cosas.php',
        '--force'    => true,
        '--dry-run'  => true,
    ])
        ->expectsOutputToContain('Contexto:      central')
        ->expectsOutputToContain('Conexión:      central')
        ->expectsOutputToContain('Dry-run completado')
        ->assertSuccessful();
});

it('una coordenada que no apunta a ningún archivo se rechaza diciendo dónde se buscó', function () {
    $this->artisan('innodite:migrate-one', [
        'coordinate' => 'Probe:Central/no_existe.php',
        '--force'    => true,
        '--dry-run'  => true,
    ])
        ->expectsOutputToContain('Coordenada inválida')
        ->assertFailed();
});

it('el contexto se puede forzar, y entonces manda sobre el de la coordenada', function () {
    // Para el caso legítimo de una migración compartida que hay que aplicar en otra base.
    migracionEn('Modules/Probe/Database/Migrations/Central/2026_01_01_000001_crea_cosas.php');

    $this->artisan('innodite:migrate-one', [
        'coordinate' => 'Probe:Central/2026_01_01_000001_crea_cosas.php',
        '--context'  => 'tenant-one',
        '--force'    => true,
        '--dry-run'  => true,
    ])
        ->expectsOutputToContain('Conexión:      tenant_one')
        ->assertSuccessful();
});

it('aplica de verdad la migración, y no solo dice que la aplicaría', function () {
    // Las tres pruebas de arriba usan `--dry-run`: comprueban que el comando *decide* bien, nunca
    // que *ejecuta* bien. Y ahí vivía el defecto — la coordenada se resolvía contra `module_path` y
    // la ruta se le entregaba a `migrate` relativa a `base_path()`, dos raíces que solo coinciden
    // mientras nadie mueva la carpeta de módulos. El síntoma es el peor de todos: `migrate` no se
    // queja de que falte el archivo, simplemente no aplica nada y devuelve éxito.
    requiereBaseDeDatos();

    $baseDatos = $this->tempPath('database/test-central.sqlite');
    File::ensureDirectoryExists(dirname($baseDatos));
    touch($baseDatos);

    config()->set('database.connections.central', [
        'driver'   => 'sqlite',
        'database' => $baseDatos,
        'prefix'   => '',
    ]);

    $ruta = $this->tempPath('Modules/Probe/Database/Migrations/Central/2026_01_01_000001_crea_cosas.php');
    File::ensureDirectoryExists(dirname($ruta));
    File::put($ruta, <<<'MIGRACION'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration {
            public function up(): void
            {
                Schema::create('cosas', function (Blueprint $tabla): void {
                    $tabla->id();
                    $tabla->string('nombre');
                });
            }
        };
        MIGRACION);

    $this->artisan('innodite:migrate-one', [
        'coordinate' => 'Probe:Central/2026_01_01_000001_crea_cosas.php',
        '--force'    => true,
    ])->assertSuccessful();

    expect(Schema::connection('central')->hasTable('cosas'))->toBeTrue(
        'FALLA: el comando terminó en éxito y la tabla no existe. · FIX: la ruta que recibe '
        . '`migrate --path` tiene que resolverse contra la misma raíz con la que se localizó la '
        . 'coordenada (`module_path`), y pasarse con `--realpath`.'
    );
});
