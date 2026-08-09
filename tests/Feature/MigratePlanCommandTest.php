<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * El esquema se aplica desde los traits, y la base de datos la dice el contexto.
 *
 * Las dos mitades de la retirada del manifiesto JSON. Antes **el orden** salía de una lista dentro
 * del archivo —que no viajaba con el módulo al copiarlo y se desincronizaba en silencio— y **la
 * conexión** salía de su nombre: `tenant-one.order.json` → contexto `tenant-one`. Pasar otro
 * `--manifest` ejecutaba contra otra base de datos sin que nada lo advirtiera.
 *
 * Ahora el orden lo declara cada subfuncionalidad en su trait `MigrationsList`, y el contexto se
 * dice en voz alta.
 */

/** Deja un trait MigrationsList con las migraciones que declara, y los archivos que nombra. */
function traitConMigraciones(string $carpeta, array $migraciones): void
{
    $lista = implode("\n", array_map(
        static fn (string $m): string => "            '{$m}',",
        $migraciones
    ));

    $destino = test()->tempPath("Modules/Probe/Database/Seeders/{$carpeta}/Thing");
    File::ensureDirectoryExists($destino);

    File::put("{$destino}/ProbeThingMigrationsList.php", "<?php\n\ntrait ProbeThingMigrationsList\n{\n"
        . "    protected function migrations(): array\n    {\n        return [\n{$lista}\n        ];\n    }\n}\n");

    foreach ($migraciones as $migracion) {
        $ruta = test()->tempPath(str_replace('Modules/', 'Modules/', $migracion));
        File::ensureDirectoryExists(dirname($ruta));
        File::put($ruta, "<?php\n");
    }
}

it('lista las migraciones que declaran los traits, sin ejecutar nada', function () {
    traitConMigraciones('Central', [
        'Modules/Probe/Database/Migrations/Central/2026_01_01_000001_crea_cosas.php',
        'Modules/Probe/Database/Migrations/Central/2026_01_01_000002_crea_otras.php',
    ]);

    $this->artisan('innodite:migrate-plan', ['--context' => 'central', '--dry-run' => true])
        ->expectsOutputToContain('2026_01_01_000001_crea_cosas.php')
        ->expectsOutputToContain('2026_01_01_000002_crea_otras.php')
        ->expectsOutputToContain('Dry-run completado')
        ->assertSuccessful();
});

it('sin contexto no adivina contra qué base de datos ejecutar', function () {
    // Es el fallo que el manifiesto escondía: la base de datos salía del nombre de un archivo, así
    // que siempre había una «por defecto» aunque nadie la hubiera elegido.
    $this->artisan('innodite:migrate-plan')
        ->expectsOutputToContain('--context=central')
        ->assertFailed();
});

it('un contexto sin migraciones declaradas lo dice, en vez de fingir que desplegó', function () {
    $this->artisan('innodite:migrate-plan', ['--context' => 'central', '--dry-run' => true])
        ->expectsOutputToContain('No hay ninguna migración declarada')
        ->assertSuccessful();
});

it('ejecuta las migraciones de verdad sobre una base sqlite temporal', function () {
    requiereBaseDeDatos();

    $baseDatos = $this->tempPath('database/test-central.sqlite');
    File::ensureDirectoryExists(dirname($baseDatos));
    touch($baseDatos);

    config()->set('database.connections.central', [
        'driver'   => 'sqlite',
        'database' => $baseDatos,
        'prefix'   => '',
    ]);

    $migracion = 'Modules/Probe/Database/Migrations/Central/2026_01_01_000001_crea_cosas.php';

    traitConMigraciones('Central', [$migracion]);

    // La migración de verdad: `migrate --path` la incluye y la ejecuta.
    File::put($this->tempPath($migracion), <<<'PHP'
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
        PHP);

    $this->artisan('innodite:migrate-plan', ['--context' => 'central'])
        ->assertSuccessful();

    expect(Schema::connection('central')->hasTable('cosas'))->toBeTrue();
});
