<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\ModuleAuditor;

// ─────────────────────────────────────────────────────────────────────────────
// Unit: ModuleAuditor
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    // Limpiar el log antes de cada test
    $logPath = storage_path('logs/module_maker.log');
    if (File::exists($logPath)) {
        File::delete($logPath);
    }
});

it('escribe una línea JSON válida en el log', function () {
    ModuleAuditor::log('test.event', ['module' => 'TestModule', 'context_key' => 'central']);

    $logPath = storage_path('logs/module_maker.log');
    expect(File::exists($logPath))->toBeTrue();

    $line    = trim(File::get($logPath));
    $decoded = json_decode($line, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['event'])->toBe('test.event')
        ->and($decoded['module'])->toBe('TestModule')
        ->and($decoded['package'])->toBe('innodite/laravel-module-maker')
        ->and($decoded['version'])->toBe('3.0.0')
        ->and($decoded)->toHaveKey('timestamp');
});

it('acumula múltiples entradas en líneas separadas (NDJSON)', function () {
    ModuleAuditor::log('event.one',   ['module' => 'Alpha']);
    ModuleAuditor::log('event.two',   ['module' => 'Beta']);
    ModuleAuditor::log('event.three', ['module' => 'Gamma']);

    $logPath = storage_path('logs/module_maker.log');
    $lines   = array_filter(explode(PHP_EOL, trim(File::get($logPath))));

    expect(count($lines))->toBe(3);

    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE)
            ->and($decoded)->toHaveKey('event')
            ->and($decoded)->toHaveKey('timestamp');
    }
});

it('readLog() retorna array vacío cuando el log no existe', function () {
    expect(ModuleAuditor::readLog())->toBeArray()->toBeEmpty();
});

it('readLog() parsea correctamente las entradas existentes', function () {
    ModuleAuditor::log('module.created',  ['module' => 'User', 'context_key' => 'central']);
    ModuleAuditor::log('routes.injected', ['module' => 'User', 'route_file'  => 'web.php']);

    $entries = ModuleAuditor::readLog();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['event'])->toBe('module.created')
        ->and($entries[1]['event'])->toBe('routes.injected');
});

it('logPath() retorna la ruta correcta al archivo de log', function () {
    expect(ModuleAuditor::logPath())->toEndWith('logs/module_maker.log');
});
