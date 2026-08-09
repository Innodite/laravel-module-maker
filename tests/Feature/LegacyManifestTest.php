<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\LegacyManifests;

/**
 * Los manifiestos de la v3, que ya no lee nadie.
 *
 * Un proyecto que actualiza los sigue teniendo en el disco, con su lista dentro, y todo indica que
 * siguen mandando: están ahí, tienen contenido y nada dice lo contrario. Es la peor forma de quedarse
 * obsoleto —un archivo que parece la fuente de la verdad y ya no lo es—, así que el paquete lo dice
 * al ejecutar los comandos que antes los usaban.
 *
 * **Aviso, no error:** el proyecto funciona perfectamente sin tocarlos, y hacer fallar un despliegue
 * por un archivo que nadie lee sería exactamente al revés de lo que hace falta.
 */

/** Deja un manifiesto de la v3 donde la v3 los guardaba. */
function manifiestoV3(string $nombre = 'central.order.json'): void
{
    $dir = test()->tempPath('module-maker-config/migrations');

    File::ensureDirectoryExists($dir);
    File::put("{$dir}/{$nombre}", json_encode(['migrations' => [], 'seeders' => []]));
}

it('los encuentra donde la v3 los dejaba', function () {
    manifiestoV3();
    manifiestoV3('tenant-one.order.json');

    expect(LegacyManifests::found())->toBe(['central.order.json', 'tenant-one.order.json']);
});

it('un proyecto sin ellos no tiene nada que reportar', function () {
    expect(LegacyManifests::found())->toBe([]);
});

it('el comando avisa, y sigue adelante', function () {
    manifiestoV3();

    $this->artisan('innodite:migrate-plan', ['--context' => 'central', '--dry-run' => true])
        ->expectsOutputToContain('manifiesto(s) de la v3')
        ->expectsOutputToContain('MigrationsList')
        ->assertSuccessful();
});

it('sin manifiestos no dice nada: un proyecto nuevo no tiene por qué oír hablar de la v3', function () {
    $this->artisan('innodite:migrate-plan', ['--context' => 'central', '--dry-run' => true])
        ->doesntExpectOutputToContain('manifiesto(s) de la v3')
        ->assertSuccessful();
});
