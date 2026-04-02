<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MigrationPlanResolver
{
    /**
     * Resuelve la ruta del manifiesto de migración.
     */
    public function resolveManifestPath(?string $manifestOption): string
    {
        $manifest = trim((string) ($manifestOption ?: 'central_order.json'));

        if ($manifest === '') {
            throw new InvalidArgumentException('El nombre del manifiesto no puede estar vacío.');
        }

        if (File::exists($manifest)) {
            return $manifest;
        }

        $base = rtrim((string) config('make-module.config_path'), '/\\') . '/migrations';
        $candidate = $base . '/' . $manifest;

        if (!File::exists($candidate)) {
            throw new InvalidArgumentException(
                "No se encontró el manifiesto '{$manifest}'.\n"
                . "Buscado en: {$candidate}"
            );
        }

        return $candidate;
    }

    /**
     * Carga y valida el JSON del manifiesto.
     *
     * @return array{migrations: array<int, string>, seeders: array<int, string>}
     */
    public function loadPlan(string $manifestPath): array
    {
        $raw = File::get($manifestPath);
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new InvalidArgumentException(
                "JSON inválido en '{$manifestPath}': " . json_last_error_msg()
            );
        }

        $migrations = $data['migrations'] ?? [];
        $seeders = $data['seeders'] ?? [];

        if (!is_array($migrations) || !is_array($seeders)) {
            throw new InvalidArgumentException(
                "El manifiesto '{$manifestPath}' debe contener arrays 'migrations' y 'seeders'."
            );
        }

        return [
            'migrations' => array_values(array_filter($migrations, static fn ($item) => is_string($item) && trim($item) !== '')),
            'seeders' => array_values(array_filter($seeders, static fn ($item) => is_string($item) && trim($item) !== '')),
        ];
    }

    /**
     * @return array{module: string, contextPath: string, file: string, path: string}
     */
    public function resolveMigrationCoordinate(string $coordinate): array
    {
        [$module, $contextPath, $file] = $this->splitCoordinate($coordinate);

        $moduleStudly = Str::studly($module);
        $path = rtrim((string) config('make-module.module_path'), '/\\')
            . "/{$moduleStudly}/Database/Migrations/{$contextPath}/{$file}";

        if (!File::exists($path)) {
            throw new InvalidArgumentException(
                "Coordenada inválida: {$coordinate}\n"
                . "Ruta esperada: {$path}"
            );
        }

        return [
            'module' => $moduleStudly,
            'contextPath' => $contextPath,
            'file' => $file,
            'path' => $path,
        ];
    }

    /**
     * @return array{module: string, contextPath: string, className: string, fqcn: string}
     */
    public function resolveSeederCoordinate(string $coordinate): array
    {
        [$module, $contextPath, $className] = $this->splitCoordinate($coordinate);

        $moduleStudly = Str::studly($module);
        $fqcn = "Modules\\{$moduleStudly}\\Database\\Seeders\\" . str_replace('/', '\\', $contextPath) . "\\{$className}";

        return [
            'module' => $moduleStudly,
            'contextPath' => $contextPath,
            'className' => $className,
            'fqcn' => $fqcn,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitCoordinate(string $coordinate): array
    {
        $normalized = trim($coordinate);
        if ($normalized === '') {
            throw new InvalidArgumentException('La coordenada no puede estar vacía.');
        }

        if (!str_contains($normalized, ':')) {
            throw new InvalidArgumentException(
                "Coordenada inválida '{$coordinate}'. Formato esperado: Modulo:Contexto/Archivo"
            );
        }

        [$module, $contextAndTarget] = explode(':', $normalized, 2);
        $module = trim($module);
        $contextAndTarget = trim($contextAndTarget);

        if ($module === '' || $contextAndTarget === '') {
            throw new InvalidArgumentException(
                "Coordenada inválida '{$coordinate}'. Formato esperado: Modulo:Contexto/Archivo"
            );
        }

        $lastSlash = strrpos($contextAndTarget, '/');
        if ($lastSlash === false || $lastSlash === 0 || $lastSlash === strlen($contextAndTarget) - 1) {
            throw new InvalidArgumentException(
                "Coordenada inválida '{$coordinate}'. Debe incluir contexto y archivo/clase."
            );
        }

        $contextPath = trim(substr($contextAndTarget, 0, $lastSlash), '/');
        $target = trim(substr($contextAndTarget, $lastSlash + 1));

        if ($contextPath === '' || $target === '') {
            throw new InvalidArgumentException(
                "Coordenada inválida '{$coordinate}'. Debe incluir contexto y archivo/clase."
            );
        }

        return [$module, $contextPath, $target];
    }
}
