<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('sincroniza manifiesto agregando migraciones y seeders faltantes', function () {
    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $manifestPath = "{$manifestDir}/central_order.json";
    File::put($manifestPath, json_encode([
        'migrations' => [
            'User:Shared/2026_01_01_000001_create_users_table.php',
        ],
        'seeders' => [],
    ], JSON_PRETTY_PRINT));

    $migExisting = $this->tempPath('Modules/User/Database/Migrations/Shared/2026_01_01_000001_create_users_table.php');
    $migMissing = $this->tempPath('Modules/User/Database/Migrations/Shared/2026_01_02_000002_create_profiles_table.php');

    File::ensureDirectoryExists(dirname($migExisting));
    File::put($migExisting, "<?php\n");
    File::put($migMissing, "<?php\n");

    $seederMissing = $this->tempPath('Modules/User/Database/Seeders/Shared/SharedUserSeeder.php');
    File::ensureDirectoryExists(dirname($seederMissing));
    File::put($seederMissing, "<?php\nnamespace Modules\\User\\Database\\Seeders\\Shared; class SharedUserSeeder {}\n");

    $this->artisan('innodite:migration-sync', [
        '--manifest' => 'central_order.json',
    ])->assertSuccessful();

    $updated = json_decode(File::get($manifestPath), true);

    expect($updated['migrations'])->toContain('User:Shared/2026_01_01_000001_create_users_table.php')
        ->and($updated['migrations'])->toContain('User:Shared/2026_01_02_000002_create_profiles_table.php')
        ->and($updated['seeders'])->toContain('User:Shared/SharedUserSeeder');
});

it('no modifica el manifiesto en modo dry-run', function () {
    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $manifestPath = "{$manifestDir}/central_order.json";
    $initial = [
        'migrations' => [],
        'seeders' => [],
    ];

    File::put($manifestPath, json_encode($initial, JSON_PRETTY_PRINT));

    $migMissing = $this->tempPath('Modules/User/Database/Migrations/Shared/2026_01_02_000002_create_profiles_table.php');
    File::ensureDirectoryExists(dirname($migMissing));
    File::put($migMissing, "<?php\n");

    $this->artisan('innodite:migration-sync', [
        '--manifest' => 'central_order.json',
        '--dry-run' => true,
    ])->assertSuccessful();

    $updated = json_decode(File::get($manifestPath), true);
    expect($updated)->toBe($initial);
});
