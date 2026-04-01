<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

// ─────────────────────────────────────────────────────────────────────────────
// Feature: innodite:make-module
// ─────────────────────────────────────────────────────────────────────────────

it('genera un módulo con contexto central y crea la estructura de directorios', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'User',
        '--context'   => 'central',
        '--no-routes' => true,
    ])->assertSuccessful();

    $modulePath = $this->tempPath('Modules/User');

    expect(File::isDirectory($modulePath))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Http/Controllers"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Models"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Services"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Repositories"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Providers"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Database/Migrations"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Routes"))->toBeTrue()
        ->and(File::isDirectory("{$modulePath}/Docs"))->toBeTrue();
});

it('escribe una entrada en el log de auditoría tras la generación exitosa', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'Product',
        '--context'   => 'central',
        '--no-routes' => true,
    ])->assertSuccessful();

    $logPath = storage_path('logs/module_maker.log');
    expect(File::exists($logPath))->toBeTrue();

    $lastEntry = json_decode(trim(collect(explode(PHP_EOL, File::get($logPath)))->filter()->last()), true);

    expect($lastEntry)->toBeArray()
        ->and($lastEntry['event'])->toBe('module.created')
        ->and($lastEntry['module'])->toBe('Product')
        ->and($lastEntry['context_key'])->toBe('central')
        ->and($lastEntry['version'])->toBe('3.0.0');
});

it('rechaza nombres que son palabras reservadas de PHP', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'class',
        '--context'   => 'central',
        '--no-routes' => true,
    ])->assertFailed();
});

it('rechaza nombres de módulo inválidos (no PascalCase)', function () {
    $this->artisan('innodite:make-module', [
        'name'        => '123invalid',
        '--context'   => 'central',
        '--no-routes' => true,
    ])->assertFailed();
});

it('impide la creación de un módulo duplicado', function () {
    $args = ['name' => 'Invoice', '--context' => 'central', '--no-routes' => true];

    $this->artisan('innodite:make-module', $args)->assertSuccessful();
    $this->artisan('innodite:make-module', $args)->assertFailed();
});

it('crea los archivos de documentación Docs/', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'Role',
        '--context'   => 'central',
        '--no-routes' => true,
    ])->assertSuccessful();

    $docsPath = $this->tempPath('Modules/Role/Docs');

    expect(File::exists("{$docsPath}/history.md"))->toBeTrue()
        ->and(File::exists("{$docsPath}/architecture.md"))->toBeTrue()
        ->and(File::exists("{$docsPath}/schema.md"))->toBeTrue();
});

it('genera el ServiceProvider del módulo con namespace correcto', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'Permission',
        '--context'   => 'central',
        '--no-routes' => true,
    ])->assertSuccessful();

    $providerFile = $this->tempPath('Modules/Permission/Providers/PermissionServiceProvider.php');

    expect(File::exists($providerFile))->toBeTrue();

    $content = File::get($providerFile);
    expect($content)->toContain('namespace Modules\\Permission\\Providers');
});

it('lee correctamente el contexts.json y valida el contexto', function () {
    $this->artisan('innodite:make-module', [
        'name'        => 'Tenant',
        '--context'   => 'invalid-context-xyz',
        '--no-routes' => true,
    ])->assertFailed();
});
