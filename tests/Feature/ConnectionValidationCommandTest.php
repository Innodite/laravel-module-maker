<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('falla con mensaje claro cuando la connection_key del tenant no existe en config/database.php', function () {
    // tenant-one tiene connection_key: 'tenant_one' en contexts.json
    // No configuramos database.connections.tenant_one → debe fallar con guard R03

    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $migrationPath = $this->tempPath('Modules/User/Database/Migrations/Tenant/TenantOne/2026_01_01_000001_create_tenant_users_table.php');
    File::ensureDirectoryExists(dirname($migrationPath));
    File::put($migrationPath, "<?php\n");

    File::put("{$manifestDir}/tenant-one.order.json", json_encode([
        'migrations' => [
            'User:Tenant/TenantOne/2026_01_01_000001_create_tenant_users_table.php',
        ],
        'seeders' => [],
    ], JSON_PRETTY_PRINT));

    $this->artisan('innodite:migrate-plan', [
        '--manifest' => 'tenant-one.order.json',
    ])
        ->expectsOutputToContain("'tenant_one' del contexto 'tenant-one'")
        ->assertFailed();
});

it('falla con mensaje claro cuando la connection_key de central no existe en config/database.php', function () {
    // central tiene connection_key: 'central' en contexts.json
    // No configuramos database.connections.central → debe fallar con guard R03

    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $migrationPath = $this->tempPath('Modules/User/Database/Migrations/Central/2026_01_01_000001_create_central_users_table.php');
    File::ensureDirectoryExists(dirname($migrationPath));
    File::put($migrationPath, "<?php\n");

    File::put("{$manifestDir}/central.order.json", json_encode([
        'migrations' => [
            'User:Central/2026_01_01_000001_create_central_users_table.php',
        ],
        'seeders' => [],
    ], JSON_PRETTY_PRINT));

    $this->artisan('innodite:migrate-plan', [
        '--manifest' => 'central.order.json',
    ])
        ->expectsOutputToContain('central')
        ->assertFailed();
});

it('omite la validacion de conexion en dry-run aunque la connection_key no exista', function () {
    // dry-run no debe activar el guard ni la validacion de BD

    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $migrationPath = $this->tempPath('Modules/User/Database/Migrations/Tenant/TenantOne/2026_01_01_000001_create_tenant_users_table.php');
    File::ensureDirectoryExists(dirname($migrationPath));
    File::put($migrationPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {}
    public function down(): void {}
};
PHP);

    File::put("{$manifestDir}/tenant-one.order.json", json_encode([
        'migrations' => [
            'User:Tenant/TenantOne/2026_01_01_000001_create_tenant_users_table.php',
        ],
        'seeders' => [],
    ], JSON_PRETTY_PRINT));

    $this->artisan('innodite:migrate-plan', [
        '--manifest' => 'tenant-one.order.json',
        '--dry-run' => true,
    ])->assertSuccessful();
});
