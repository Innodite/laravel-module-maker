<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\EventLog;
use Innodite\LaravelModuleMaker\Support\PackageVersion;

// ─────────────────────────────────────────────────────────────────────────────
// Unit: EventLog
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    // Limpiar el log antes de cada test
    $logPath = storage_path('logs/module_maker.log');
    if (File::exists($logPath)) {
        File::delete($logPath);
    }
});

it('escribe una línea JSON válida en el log', function () {
    EventLog::log('test.event', ['module' => 'TestModule', 'context_key' => 'central']);

    $logPath = storage_path('logs/module_maker.log');
    expect(File::exists($logPath))->toBeTrue();

    $line    = trim(File::get($logPath));
    $decoded = json_decode($line, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['event'])->toBe('test.event')
        ->and($decoded['module'])->toBe('TestModule')
        ->and($decoded['package'])->toBe(PackageVersion::PACKAGE)
        ->and($decoded['version'])->toBe(PackageVersion::current())
        ->and($decoded)->toHaveKey('timestamp');
});

it('la versión de cada línea es la instalada de verdad, no un literal', function () {
    // Este archivo existe para responder «¿quién generó esto y cuándo?». El campo `version` estaba
    // escrito a mano —`'3.0.0'`—, así que un proyecto con la 4.2 instalada acumulaba líneas jurando
    // que las generó la 3.0.0. Un historial que miente sobre su autor es ruido con formato JSON.
    //
    // Y la prueba anterior no lo habría visto nunca: comparaba contra el mismo literal.
    EventLog::log('module.created', ['module' => 'Ledger']);

    $version = json_decode(trim(File::get(EventLog::logPath())), true)['version'];

    expect($version)->toBe(
        PackageVersion::current(),
        'FALLA: la versión del log no es la que Composer reporta. · FIX: se le pregunta a '
        . 'PackageVersion::current(), nunca se escribe a mano.'
    );

    expect($version)->not->toBe(
        '3.0.0',
        'FALLA: volvió el literal «3.0.0» al log de auditoría. · FIX: el paquete va por la v4; una '
        . 'versión escrita a mano es falsa desde la publicación siguiente.'
    );
});

it('acumula múltiples entradas en líneas separadas (NDJSON)', function () {
    EventLog::log('event.one',   ['module' => 'Alpha']);
    EventLog::log('event.two',   ['module' => 'Beta']);
    EventLog::log('event.three', ['module' => 'Gamma']);

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
    expect(EventLog::readLog())->toBeArray()->toBeEmpty();
});

it('readLog() parsea correctamente las entradas existentes', function () {
    // `routes.injected` era el cuarto evento y ya no se emite: nadie inyecta rutas en el proyecto.
    EventLog::log('module.created',    ['module' => 'User', 'context_key' => 'central']);
    EventLog::log('module.components', ['module' => 'User', 'context_key' => 'central']);

    $entries = EventLog::readLog();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['event'])->toBe('module.created')
        ->and($entries[1]['event'])->toBe('module.components');
});

it('logPath() retorna la ruta correcta al archivo de log', function () {
    expect(EventLog::logPath())->toEndWith('logs/module_maker.log');
});
