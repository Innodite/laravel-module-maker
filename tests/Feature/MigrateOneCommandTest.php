<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('detecta el manifiesto central para una migracion individual y muestra lo que hara en dry-run', function () {
    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);
    File::put("{$manifestDir}/central.order.json", json_encode([
        'migrations' => [],
        'seeders' => [],
    ], JSON_PRETTY_PRINT));

    $migrationPath = $this->tempPath('Modules/Probe/Database/Migrations/Central/2026_01_01_000001_create_probe_table.php');
    File::ensureDirectoryExists(dirname($migrationPath));
    File::put($migrationPath, "<?php\n");

    $this->artisan('innodite:migrate-one', [
        'coordinate' => 'Probe:Central/2026_01_01_000001_create_probe_table.php',
        '--yes' => true,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Destino: central.order.json')
        ->expectsOutputToContain('se agregara al manifiesto antes de ejecutar')
        ->expectsOutputToContain('[DRY-RUN] Se agregaria la coordenada al manifiesto.')
        ->expectsOutputToContain('[DRY-RUN] Se ejecutaria la migracion especificada.')
        ->assertSuccessful();

    $plan = json_decode(File::get("{$manifestDir}/central.order.json"), true);
    expect($plan['migrations'])->toBe([]);
});