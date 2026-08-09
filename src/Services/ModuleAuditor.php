<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Innodite\LaravelModuleMaker\Support\Disk;
use Innodite\LaravelModuleMaker\Support\PackageVersion;
use Illuminate\Support\Facades\File;

/**
 * ModuleAuditor — el historial de lo que el generador ha creado en este proyecto.
 *
 * Cada operación exitosa del generador escribe una línea JSON en:
 *   storage/logs/module_maker.log
 *
 * Formato NDJSON (Newline-Delimited JSON) — una entrada por línea:
 *   {"timestamp":"2025-01-01T12:00:00+00:00","event":"module.created","module":"User",...}
 *
 * Este formato permite que herramientas de IA y monitoreo analicen el
 * historial de cambios sin necesidad de parsear texto plano.
 *
 * **El campo `version` dice qué versión del paquete generó esa línea**, y por eso se le pregunta a
 * Composer en vez de escribirla a mano. Estaba escrita: `'3.0.0'`, un literal. Un proyecto que
 * instalase la 4.2 y generase diez módulos obtenía diez líneas jurando que las hizo la 3.0.0 — y
 * este archivo existe precisamente para responder «¿quién generó esto y cuándo?». Un historial que
 * miente sobre su autor no es un historial, es ruido con formato JSON.
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
     *   - module.rollback       → Rollback ejecutado tras error
     *
     * Había un cuarto, `routes.injected`, de cuando el generador escribía en el `routes/web.php`
     * del proyecto. Esa vía se retiró: las rutas viven dentro del módulo y nadie las inyecta.
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
                    'package'   => PackageVersion::PACKAGE,
                    'version'   => PackageVersion::current(),
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
            Disk::makeDirectory($logDir, 0755, true);
        }

        Disk::append($logPath, $entry . PHP_EOL);
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
