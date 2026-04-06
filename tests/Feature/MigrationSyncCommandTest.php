<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('sincroniza manifiesto agregando migraciones y seeders faltantes', function () {
    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $manifestPath = "{$manifestDir}/central.order.json";
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
        '--manifest' => 'central.order.json',
    ])->assertSuccessful();

    $updated = json_decode(File::get($manifestPath), true);

    expect($updated['migrations'])->toContain('User:Shared/2026_01_01_000001_create_users_table.php')
        ->and($updated['migrations'])->toContain('User:Shared/2026_01_02_000002_create_profiles_table.php')
        ->and($updated['seeders'])->toContain('User:Shared/SharedUserSeeder');
});

it('no modifica el manifiesto en modo dry-run', function () {
    $manifestDir = $this->tempPath('module-maker-config/migrations');
    File::ensureDirectoryExists($manifestDir);

    $manifestPath = "{$manifestDir}/central.order.json";
    $initial = [
        'migrations' => [],
        'seeders' => [],
    ];

    File::put($manifestPath, json_encode($initial, JSON_PRETTY_PRINT));

    $migMissing = $this->tempPath('Modules/User/Database/Migrations/Shared/2026_01_02_000002_create_profiles_table.php');
    File::ensureDirectoryExists(dirname($migMissing));
    File::put($migMissing, "<?php\n");

    $this->artisan('innodite:migration-sync', [
        '--manifest' => 'central.order.json',
        '--dry-run' => true,
    ])->assertSuccessful();

    $updated = json_decode(File::get($manifestPath), true);
    expect($updated)->toBe($initial);
});

it('detecta contexts y sincroniza manifiestos por central y tenant automaticamente', function () {
    $configDir = $this->tempPath('module-maker-config');
    $manifestDir = $this->tempPath('module-maker-config/migrations');

    File::ensureDirectoryExists($configDir);
    File::ensureDirectoryExists($manifestDir);

    File::put($this->tempPath('module-maker-config/contexts.json'), json_encode([
        'contexts' => [
            'tenant' => [
                [
                    'id' => 'alpha',
                    'permission_prefix' => 'alpha',
                ],
                [
                    'id' => 'beta',
                    'permission_prefix' => 'beta',
                ],
            ],
        ],
    ], JSON_PRETTY_PRINT));

    $centralMigration = $this->tempPath('Modules/User/Database/Migrations/Central/2026_01_01_000001_create_users_table.php');
    $sharedMigration = $this->tempPath('Modules/User/Database/Migrations/Shared/2026_01_01_000000_create_shared_table.php');
    $tenantSharedMigration = $this->tempPath('Modules/User/Database/Migrations/Tenant/Shared/2026_01_01_000002_create_user_tenant_table.php');
    $alphaMigration = $this->tempPath('Modules/Forms/Database/Migrations/Tenant/alpha/2026_01_01_000003_create_alpha_sales_table.php');
    $betaMigration = $this->tempPath('Modules/Forms/Database/Migrations/Tenant/beta/2026_01_01_000004_create_beta_sales_table.php');

    File::ensureDirectoryExists(dirname($centralMigration));
    File::ensureDirectoryExists(dirname($sharedMigration));
    File::ensureDirectoryExists(dirname($tenantSharedMigration));
    File::ensureDirectoryExists(dirname($alphaMigration));
    File::ensureDirectoryExists(dirname($betaMigration));

    File::put($centralMigration, "<?php\n");
    File::put($sharedMigration, "<?php\n");
    File::put($tenantSharedMigration, "<?php\n");
    File::put($alphaMigration, "<?php\n");
    File::put($betaMigration, "<?php\n");

    $this->artisan('innodite:migration-sync', [
        '--yes' => true,
    ])->assertSuccessful();

    $centralPlan = json_decode(File::get("{$manifestDir}/central.order.json"), true);
    $alphaPlan = json_decode(File::get("{$manifestDir}/alpha.order.json"), true);
    $betaPlan = json_decode(File::get("{$manifestDir}/beta.order.json"), true);

    expect($centralPlan['migrations'])->toContain('User:Central/2026_01_01_000001_create_users_table.php')
        ->and($centralPlan['migrations'])->toContain('User:Shared/2026_01_01_000000_create_shared_table.php')
        ->and($centralPlan['migrations'])->not->toContain('Forms:Tenant/alpha/2026_01_01_000003_create_alpha_sales_table.php')
        ->and($alphaPlan['migrations'])->toContain('User:Shared/2026_01_01_000000_create_shared_table.php')
        ->and($alphaPlan['migrations'])->toContain('User:Tenant/Shared/2026_01_01_000002_create_user_tenant_table.php')
        ->and($alphaPlan['migrations'])->toContain('Forms:Tenant/alpha/2026_01_01_000003_create_alpha_sales_table.php')
        ->and($alphaPlan['migrations'])->not->toContain('Forms:Tenant/beta/2026_01_01_000004_create_beta_sales_table.php')
        ->and($betaPlan['migrations'])->toContain('User:Shared/2026_01_01_000000_create_shared_table.php')
        ->and($betaPlan['migrations'])->toContain('User:Tenant/Shared/2026_01_01_000002_create_user_tenant_table.php')
        ->and($betaPlan['migrations'])->toContain('Forms:Tenant/beta/2026_01_01_000004_create_beta_sales_table.php')
        ->and($betaPlan['migrations'])->not->toContain('Forms:Tenant/alpha/2026_01_01_000003_create_alpha_sales_table.php');
});
