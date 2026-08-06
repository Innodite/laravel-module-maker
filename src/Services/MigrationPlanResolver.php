<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * De dónde salen las migraciones del proyecto, y cómo se nombra una sola.
 *
 * **Ya no queda nada del manifiesto JSON.** Describía lo que la carpeta ya dice, no viajaba con el
 * módulo al copiarlo a otro proyecto y se desincronizaba en silencio; peor aún, de su NOMBRE se
 * derivaba la base de datos contra la que se ejecutaba. Las migraciones salen de los traits
 * `MigrationsList` desde F2 y los seeders del array `deploy` desde F3: el orden vive en código,
 * dentro del módulo, y la conexión la dicen la coordenada y el modo.
 */
class MigrationPlanResolver
{
    /**
     * Las migraciones del proyecto, **leídas de los traits `MigrationsList`** (P2).
     *
     * Esta es la fuente a partir de la v4. El manifiesto JSON describía lo que ya dice la carpeta:
     * no viajaba con el módulo al copiarlo a otro proyecto y se desincronizaba en silencio, así que
     * el orden de despliegue vive ahora **en código**, dentro del módulo.
     *
     * Se leen por texto y no instanciando el trait, a propósito: este paquete corre dentro de la
     * aplicación de otro, donde el autoload de `Modules\…` puede no estar registrado todavía —
     * justo cuando se despliega por primera vez, que es cuando más falta hace.
     *
     * @param  string|null  $contextFilter  'Central', 'Tenant/Shared'… o null para todos
     * @return array<int, string> Rutas relativas a la raíz del proyecto, en el orden de los traits
     */
    public function migrationsFromTraits(?string $contextFilter = null): array
    {
        $modulesPath = rtrim((string) config('make-module.module_path'), '/\\');

        if (! File::isDirectory($modulesPath)) {
            return [];
        }

        $traits = [];

        foreach (File::allFiles($modulesPath) as $archivo) {
            if (! str_ends_with($archivo->getFilename(), 'MigrationsList.php')) {
                continue;
            }

            $relativo = str_replace('\\', '/', $archivo->getRelativePathname());

            if ($contextFilter !== null && ! str_contains($relativo, "/{$contextFilter}/")) {
                continue;
            }

            $traits[$relativo] = $archivo->getRealPath();
        }

        ksort($traits);   // orden estable entre módulos: el del árbol

        $migraciones = [];

        foreach ($traits as $ruta) {
            preg_match_all("/'(Modules\/[^']+\.php)'/", (string) File::get($ruta), $encontradas);

            foreach ($encontradas[1] as $migracion) {
                $migraciones[] = $migracion;   // el orden DENTRO del módulo lo fija su trait
            }
        }

        return $migraciones;
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
