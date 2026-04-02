<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('crea Tests/test-config.json para un modulo especifico', function () {
    $modulePath = $this->tempPath('Modules/UserManagement');
    File::ensureDirectoryExists($modulePath);

    $this->artisan('innodite:test-sync', [
        'module' => 'UserManagement',
    ])
        ->expectsOutputToContain('Sincronizado: UserManagement')
        ->assertSuccessful();

    $configPath = $this->tempPath('Modules/UserManagement/Tests/test-config.json');
    expect(File::exists($configPath))->toBeTrue();

    $config = json_decode(File::get($configPath), true);
    expect($config)->toBeArray();
    expect($config['contexts'])->toBeArray();
    expect($config['contexts'])->toHaveKey('central');
    expect($config['contexts'])->toHaveKey('shared');
    expect($config['contexts'])->toHaveKey('tenant_shared');
    expect($config['contexts'])->toHaveKey('tenant_one');
    expect($config['contexts'])->toHaveKey('tenant_two');
    expect($config['contexts']['central']['db_connection'])->toBeNull();
    expect($config['contexts']['central']['db_database'])->toBeNull();
});

it('mantiene overrides existentes y agrega contextos faltantes al sincronizar', function () {
    $modulePath = $this->tempPath('Modules/Forms');
    File::ensureDirectoryExists("{$modulePath}/Tests");

    $existingConfig = [
        '_readme' => 'custom',
        'contexts' => [
            'central' => [
                'key' => 'central',
                'label' => 'Central',
                'folder' => 'Central',
                'group' => 'central',
                'db_connection' => 'mysql',
                'db_database' => 'neocenter_test',
                'enabled' => true,
                'seeder' => 'Modules\\Forms\\Database\\Seeders\\Central\\CentralFormsSeeder',
                'env' => [
                    'APP_LOCALE' => 'es',
                ],
            ],
            'shared' => [
                'key' => 'shared',
                'label' => 'Shared',
                'folder' => 'Shared',
                'group' => 'shared',
                'db_connection' => 'mysql',
                'db_database' => 'should_disappear',
                'enabled' => true,
                'seeder' => null,
                'env' => [],
            ],
        ],
    ];

    File::put(
        "{$modulePath}/Tests/test-config.json",
        json_encode($existingConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );

    $this->artisan('innodite:test-sync', [
        'module' => 'Forms',
    ])->assertSuccessful();

    $config = json_decode((string) File::get("{$modulePath}/Tests/test-config.json"), true);

    expect($config['contexts']['central']['db_connection'])->toBe('mysql');
    expect($config['contexts']['central']['db_database'])->toBe('neocenter_test');
    expect($config['contexts']['central']['seeder'])->toBe('Modules\\Forms\\Database\\Seeders\\Central\\CentralFormsSeeder');
    expect($config['contexts'])->toHaveKey('shared');
    expect($config['contexts'])->toHaveKey('tenant_shared');
    expect($config['contexts'])->toHaveKey('tenant_one');
    expect($config['contexts'])->toHaveKey('tenant_two');
});
