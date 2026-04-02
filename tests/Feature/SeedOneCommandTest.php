<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('detecta el manifiesto tenant para un seeder individual y muestra lo que hara en dry-run', function () {
    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);
    File::put("{$manifestDir}/tenant_energy_spain_order.json", json_encode([
        'migrations' => [],
        'seeders' => [],
    ], JSON_PRETTY_PRINT));

    $seederPath = $this->tempPath('Modules/Probe/Database/Seeders/Tenant/Shared/TenantSharedProbeSeeder.php');
    File::ensureDirectoryExists(dirname($seederPath));
    File::put($seederPath, <<<'PHP'
<?php

namespace Modules\Probe\Database\Seeders\Tenant\Shared;

use Illuminate\Database\Seeder;

class TenantSharedProbeSeeder extends Seeder
{
    public function run(): void
    {
    }
}
PHP);

    require_once $seederPath;

    $this->artisan('innodite:seed-one', [
        'coordinate' => 'Probe:Tenant/Shared/TenantSharedProbeSeeder',
        '--manifest' => 'tenant_energy_spain_order.json',
        '--yes' => true,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Destino: tenant_energy_spain_order.json')
        ->expectsOutputToContain('Tipo:          seeder')
        ->expectsOutputToContain('[DRY-RUN] Se ejecutaria el seeder especificado.')
        ->assertSuccessful();

    $plan = json_decode(File::get("{$manifestDir}/tenant_energy_spain_order.json"), true);
    expect($plan['seeders'])->toBe([]);
});

it('permite forzar un manifiesto especifico para un seeder con --manifest', function () {
    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);
    File::put("{$manifestDir}/central_order.json", json_encode([
        'migrations' => [],
        'seeders' => ['Probe:Central/ExistingSeeder'],
    ], JSON_PRETTY_PRINT));

    $seederPath = $this->tempPath('Modules/Probe/Database/Seeders/Central/ProbePermissionSeeder.php');
    File::ensureDirectoryExists(dirname($seederPath));
    File::put($seederPath, <<<'PHP'
<?php

namespace Modules\Probe\Database\Seeders\Central;

use Illuminate\Database\Seeder;

class ProbePermissionSeeder extends Seeder
{
    public function run(): void
    {
    }
}
PHP);

    require_once $seederPath;

    $this->artisan('innodite:seed-one', [
        'coordinate' => 'Probe:Central/ProbePermissionSeeder',
        '--manifest' => 'central_order.json',
        '--yes' => true,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Destino: central_order.json')
        ->expectsOutputToContain('se agregara al manifiesto antes de ejecutar')
        ->expectsOutputToContain('[DRY-RUN] Se agregaria la coordenada al manifiesto.')
        ->assertSuccessful();

    $plan = json_decode(File::get("{$manifestDir}/central_order.json"), true);
    expect($plan['seeders'])->toBe(['Probe:Central/ExistingSeeder']);
});
