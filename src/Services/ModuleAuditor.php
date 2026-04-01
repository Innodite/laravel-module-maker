<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Support\Facades\File;

/**
 * ModuleAuditor — Sistema de Auditoría en NDJSON para ModuleMaker v3.0.0
 *
 * Cada operación exitosa del generador escribe una línea JSON en:
 *   storage/logs/module_maker.log
 *
 * Formato NDJSON (Newline-Delimited JSON) — una entrada por línea:
 *   {"timestamp":"2025-01-01T12:00:00+00:00","event":"module.created","module":"User",...}
 *
 * Este formato permite que herramientas de IA y monitoreo analicen el
 * historial de cambios sin necesidad de parsear texto plano.
 */
final class ModuleAuditor
{
    private const LOG_FILE = 'logs/module_maker.log';

    /**
     * Registra una entrada de auditoría en formato NDJSON.
     *
     * Eventos disponibles:
     *   - module.created        → Módulo completo generado
     *   - module.components     → Componentes individuales añadidos
     *   - routes.injected       → Rutas inyectadas en archivo del proyecto
     *   - module.rollback       → Rollback ejecutado tras error
     *
     * @param string               $event  Identificador del evento
     * @param array<string, mixed> $data   Datos adicionales del evento
     */
    public static function log(string $event, array $data = []): void
    {
        $entry = json_encode(
            array_merge(
                [
                    'timestamp' => now()->toIso8601String(),
                    'event'     => $event,
                    'package'   => 'innodite/laravel-module-maker',
                    'version'   => '3.0.0',
                ],
                $data
            ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($entry === false) {
            return;
        }

        $logPath = storage_path(self::LOG_FILE);
        $logDir  = dirname($logPath);

        if (!File::isDirectory($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }

        File::append($logPath, $entry . PHP_EOL);
    }

    /**
     * Retorna el contenido del log como array de entradas decodificadas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function readLog(): array
    {
        $logPath = storage_path(self::LOG_FILE);

        if (!File::exists($logPath)) {
            return [];
        }

        $lines   = explode(PHP_EOL, trim(File::get($logPath)));
        $entries = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }

    /**
     * Retorna la ruta absoluta al archivo de log.
     */
    public static function logPath(): string
    {
        return storage_path(self::LOG_FILE);
    }
}
