<?php

declare(strict_types=1);

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
