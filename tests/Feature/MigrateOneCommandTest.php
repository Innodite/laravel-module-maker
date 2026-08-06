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
        '--yes'      => true,
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
        '--yes'      => true,
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
        '--yes'      => true,
        '--dry-run'  => true,
    ])
        ->expectsOutputToContain('Conexión:      tenant_one')
        ->assertSuccessful();
});
