<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Cómo se nombra **una** migración, y dónde está el archivo que nombra.
 *
 * **Ya no resuelve ningún plan, y por eso ya no ordena nada.** Hasta la retirada de
 * `innodite:migrate-plan` esta clase también reunía las migraciones de todo el proyecto recorriendo
 * el árbol, y las ordenaba con `ksort` sobre la ruta del archivo — el abecedario de las carpetas—,
 * ignorando el orden que el proyecto declara en `deploy`. Ese era el defecto: `carts` se migraba
 * antes que `customers`. Se retiró junto con su único llamador, porque el orden de despliegue tiene
 * **un solo dueño** y es `deploy`, leído por los maestros de cada módulo.
 *
 * Lo que queda es la otra mitad, que nunca ordenó nada: traducir la coordenada que escribe una
 * persona —`Factura:Central/2026_01_01_crea_facturas.php`— a la ruta real del archivo, para
 * `innodite:migrate-one`.
 *
 * **Y no queda nada del manifiesto JSON.** Describía lo que la carpeta ya dice, no viajaba con el
 * módulo al copiarlo a otro proyecto y se desincronizaba en silencio; peor aún, de su NOMBRE se
 * derivaba la base de datos contra la que se ejecutaba. Hoy las migraciones de una subfuncionalidad
 * las declara su trait `MigrationsList` y el orden entre subfuncionalidades lo declara `deploy`.
 */
class MigrationPlanResolver
{
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
