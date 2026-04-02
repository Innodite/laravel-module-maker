<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

it('ejecuta migrate-plan en dry-run resolviendo coordenadas de migración', function () {
    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $migrationPath = $this->tempPath('Modules/User/Database/Migrations/Shared/2026_01_01_000001_create_users_table.php');
    File::ensureDirectoryExists(dirname($migrationPath));
    File::put($migrationPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
PHP);

    $manifestPath = "{$manifestDir}/central_order.json";
    File::put($manifestPath, json_encode([
        'migrations' => [
            'User:Shared/2026_01_01_000001_create_users_table.php',
        ],
        'seeders' => [],
    ], JSON_PRETTY_PRINT));

    $this->artisan('innodite:migrate-plan', [
        '--manifest' => 'central_order.json',
        '--dry-run' => true,
    ])->assertSuccessful();
});

it('falla cuando una coordenada de migración no existe', function () {
    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $manifestPath = "{$manifestDir}/central_order.json";
    File::put($manifestPath, json_encode([
        'migrations' => [
            'User:Shared/2026_01_01_999999_missing_table.php',
        ],
        'seeders' => [],
    ], JSON_PRETTY_PRINT));

    $this->artisan('innodite:migrate-plan', [
        '--manifest' => 'central_order.json',
        '--dry-run' => true,
    ])->assertFailed();
});

it('valida primero que la base de datos del tenant exista antes de ejecutar', function () {
    config()->set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => $this->tempPath('database/missing-tenant.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $migrationPath = $this->tempPath('Modules/User/Database/Migrations/Tenant/Shared/2026_01_01_000001_create_users_table.php');
    File::ensureDirectoryExists(dirname($migrationPath));
    File::put($migrationPath, "<?php\n");

    $manifestPath = "{$manifestDir}/tenant_alpha_order.json";
    File::put($manifestPath, json_encode([
        'migrations' => [
            'User:Tenant/Shared/2026_01_01_000001_create_users_table.php',
        ],
        'seeders' => [],
    ], JSON_PRETTY_PRINT));

    $this->artisan('innodite:migrate-plan', [
        '--manifest' => 'tenant_alpha_order.json',
    ])
        ->expectsOutputToContain("La base de datos '")
        ->assertFailed();
});

it('ejecuta migraciones y seeders reales sobre una base sqlite temporal', function () {
    if (!extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('pdo_sqlite no está disponible en este entorno.');
    }

    $databasePath = $this->tempPath('database/test-central.sqlite');
    File::ensureDirectoryExists(dirname($databasePath));
    touch($databasePath);

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => $databasePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $migrationPath = $this->tempPath('Modules/Probe/Database/Migrations/Shared/2026_01_01_000001_create_probe_items_table.php');
    File::ensureDirectoryExists(dirname($migrationPath));
    File::put($migrationPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('probe_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('probe_items');
    }
};
PHP);

    $seederPath = $this->tempPath('Modules/Probe/Database/Seeders/Shared/SharedProbeSeeder.php');
    File::ensureDirectoryExists(dirname($seederPath));
    File::put($seederPath, <<<'PHP'
<?php

namespace Modules\Probe\Database\Seeders\Shared;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SharedProbeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('probe_items')->insert([
            'name' => 'seeded-item',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
PHP);

    require_once $seederPath;

    $manifestPath = "{$manifestDir}/central_order.json";
    File::put($manifestPath, json_encode([
        'migrations' => [
            'Probe:Shared/2026_01_01_000001_create_probe_items_table.php',
        ],
        'seeders' => [
            'Probe:Shared/SharedProbeSeeder',
        ],
    ], JSON_PRETTY_PRINT));

    $this->artisan('innodite:migrate-plan', [
        '--manifest' => 'central_order.json',
        '--seed' => true,
    ])->assertSuccessful();

    expect(DB::connection('sqlite')->table('probe_items')->count())->toBe(1)
        ->and(DB::connection('sqlite')->table('probe_items')->value('name'))->toBe('seeded-item');
});
