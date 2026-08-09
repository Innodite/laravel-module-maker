<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\SeederNames;

/**
 * Escribe los tres maestros `Application` de un módulo en un contexto.
 *
 * **Son tres y no seis** porque un maestro no tiene esquema ni datos propios: solo llama en orden a
 * las subfuncionalidades de su módulo. Los tres traits de contenido —migraciones, deltas y datos—
 * pertenecen a cada subfuncionalidad, no al módulo.
 *
 * **Generador aparte del de las piezas, a propósito.** Las piezas de subfuncionalidad conocen tablas,
 * permisos, conexiones y datos; los maestros solo saben en qué orden llamar a sus hijos. Juntar los
 * dos trabajos daba las 6 piezas + los 3 maestros + los permisos + la entrada del array en un solo
 * archivo: el que nadie quiere tocar, declarado como riesgo en el plan de la fase.
 *
 * **Uno por módulo y contexto, no uno por subfuncionalidad.** Un módulo con cinco subfuncionalidades
 * sigue teniendo tres maestros; por eso no se reescriben cuando se genera la segunda.
 */
class ModuleMasterSeederGenerator extends AbstractComponentGenerator
{
    /**
     * @param  array<string, mixed>  $componentConfig
     */
    public function __construct(string $moduleName, string $modulePath, bool $isClean, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
    }

    public function generate(): void
    {
        $directorio = $this->masterDirectory();

        $this->ensureDirectoryExists($directorio);

        $prefijo = $this->getClassPrefix();

        foreach (SeederNames::RUNNABLE as $pieza) {
            $this->writeMaster($directorio, $pieza, SeederNames::masterFor($prefijo, $this->moduleName, $pieza));
        }
    }

    /**
     * `Database/Seeders/{Contexto}/Application/` — el contexto sí, la subfuncionalidad no.
     *
     * No se usa `buildPath()` porque ese añade la subfuncionalidad como último tramo, y un maestro no
     * pertenece a ninguna: pertenece al módulo.
     */
    protected function masterDirectory(): string
    {
        $base   = $this->getComponentBasePath() . '/Database/Seeders';
        $folder = $this->getContextFolder();

        return ($folder ? "{$base}/{$folder}" : $base) . '/' . SeederNames::MASTER_FOLDER;
    }

    /** El namespace que espeja esa carpeta. */
    protected function masterNamespace(): string
    {
        $base  = "Modules\\{$this->moduleName}\\Database\\Seeders";
        $ctxNs = $this->getContextNamespacePath();

        return ($ctxNs ? "{$base}\\{$ctxNs}" : $base) . '\\' . SeederNames::MASTER_FOLDER;
    }

    protected function writeMaster(string $directorio, string $pieza, string $className): void
    {
        $destino = "{$directorio}/{$className}.php";

        if (File::exists($destino)) {
            // Un módulo con cinco subfuncionalidades genera sus maestros una vez; y si alguien añadió
            // un paso propio al `run()`, regenerar no puede llevárselo.
            return;
        }

        $stub = $pieza === 'Permissions' ? 'master-permissions-seeder.stub' : 'master-seeder.stub';

        $contenido = $this->getStubContent($stub, $this->isClean, [
            'namespace'  => $this->masterNamespace(),
            'seederName' => $className,
            'moduleName' => $this->moduleName,
            'piece'      => $pieza,
            'pieceLabel' => $this->pieceLabel($pieza),
            'context'    => $this->contextLiteral(),
        ]);

        $this->putFile($destino, $contenido, "Maestro '{$className}' creado.");
    }

    /**
     * La clave del contexto por la que el maestro filtra el orden de despliegue.
     *
     * Donde no hay eje de contexto llega `null`, y entonces el maestro lee la lista entera: no hay
     * contexto por el que agrupar porque no hay contextos.
     */
    protected function contextLiteral(): string
    {
        if (! $this->mode()->hasContextAxis()) {
            return 'null';
        }

        $contexto = ($this->componentConfig['context'] ?? '') ?: null;

        return $contexto === null ? 'null' : "'{$contexto}'";
    }

    /** Cómo se presenta la pieza en la primera línea del docblock. */
    protected function pieceLabel(string $pieza): string
    {
        return match ($pieza) {
            'Stage'      => 'Despliegue de stage',
            'Production' => 'Despliegue de producción',
            default      => 'Despliegue',
        };
    }
}
