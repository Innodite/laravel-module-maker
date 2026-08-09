<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Services\PhpunitRunner;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Innodite\LaravelModuleMaker\Support\LegacyManifests;
use Innodite\LaravelModuleMaker\Support\TestNames;
use Throwable;

/**
 * Ejecuta el contrato de pruebas de **una subfuncionalidad**, en cascada y con corte temprano.
 *
 *     php artisan innodite:test Invoice Invoice
 *     php artisan innodite:test Invoice Payment --context=central
 *
 * **Por qué por subfuncionalidad y no por módulo.** El contrato del patrón es de la subfuncionalidad:
 * son sus nueve temas, su manifiesto y sus seis piezas. Un módulo con cuatro subfuncionalidades no
 * tiene un contrato, tiene cuatro — y ejecutarlos juntos mezcla el resultado de cosas que se
 * despliegan y fallan por separado.
 *
 * **Por qué en cascada, y por qué corta.** El orden de las piezas es de dependencia: cada una da por
 * supuesto lo que comprobó la anterior. Si el andamiaje no está, el esquema falla por lo mismo; si el
 * esquema no está, los permisos fallan por lo mismo; y el comportamiento por HTTP falla de treinta
 * formas distintas por la misma causa única.
 *
 * Treinta fallos rojos de un solo problema no informan treinta veces mejor: informan **peor**, porque
 * hay que leerlos todos para descubrir que eran el mismo. Por eso al primer fallo se para y se dice
 * **qué** falló, **qué cubría** y **qué queda sin ejecutar** (R31 · R33).
 *
 * El tema 6 —la vista— no se ejecuta aquí: es JavaScript y lo corre Vitest, cuya infraestructura es
 * del proyecto anfitrión. Se nombra al final para que nadie lo dé por corrido.
 */
class TestCommand extends Command
{
    use ReportsFailures;

    protected $signature = 'innodite:test
        {module      : Módulo al que pertenece (ej: Invoice)}
        {subfeature  : Subfuncionalidad cuyo contrato se ejecuta (ej: Payment)}
        {--context=  : Contexto donde vive, en multitenant: central | tenant-uno | …}
        {--filter=   : Patrón de PHPUnit, para acotar dentro de una pieza}
        {--continuar : Ejecuta el grupo entero aunque una pieza falle, sin corte temprano}';

    protected $description = 'Ejecuta el contrato de pruebas de una subfuncionalidad, en cascada y con corte temprano.';

    public function handle(PhpunitRunner $runner): int
    {
        $modulo     = Str::studly((string) $this->argument('module'));
        $subFuncion = Str::studly((string) $this->argument('subfeature'));

        $this->newLine();
        $this->line("  <fg=blue;options=bold>Innodite ModuleMaker — Contrato de {$modulo}/{$subFuncion}</>");
        $this->newLine();

        // El último manifiesto JSON del paquete se retiró en esta fase. Un proyecto que actualice lo
        // sigue teniendo en disco, con sus contextos dentro y con toda la pinta de seguir mandando.
        // Se avisa aquí, que es donde tocaba usarlo — y **solo se avisa**: hacer fallar las pruebas
        // por un archivo que ya no lee nadie sería al revés de lo que hace falta.
        LegacyManifests::noticeTestConfig($this);

        $contexto = $this->resolverContexto();

        if ($contexto === false) {
            return self::FAILURE;
        }

        [$prefijo, $carpetaContexto] = $contexto;

        $grupo = $this->carpetaDelGrupo($modulo, $carpetaContexto, $subFuncion);

        if (! File::isDirectory($grupo)) {
            $this->fallo(
                "no hay grupo de pruebas en {$grupo}.",
                "créalo con innodite:add-entity, o regenera la subfuncionalidad.",
                'El grupo lo escribe el generador: si el módulo es anterior a esta versión, nació sin él.'
            );

            return self::FAILURE;
        }

        if (! $this->grupoCompleto($grupo, $prefijo, $subFuncion)) {
            return self::FAILURE;
        }

        return $this->correrCascada($runner, $grupo, $prefijo, $subFuncion);
    }

    /**
     * El prefijo de clase y la carpeta del contexto, o `false` si el contexto no existe.
     *
     * Los dos salen del **mismo** contexto resuelto, y no de dos sitios: derivarlos por separado es
     * lo que permitiría buscar las pruebas de un contexto en la carpeta de otro — y encontrarlas
     * vacías, que es lo mismo que decir que están en verde.
     *
     * @return array{0: string, 1: string}|false
     */
    protected function resolverContexto()
    {
        $opcion = trim((string) $this->option('context'));

        if ($opcion === '') {
            return ['', ''];   // sin eje de contexto: single-app
        }

        try {
            $item = ContextResolver::find($opcion);
        } catch (Throwable $e) {
            // El resolutor ya enumera los contextos que sí existen: repetir aquí una lista propia
            // sería una segunda que se quedaría atrás en cuanto apareciera un tenant nuevo.
            $this->components->error($e->getMessage());

            return false;
        }

        return [
            (string) ($item['class_prefix'] ?? ''),
            (string) ($item['folder'] ?? ''),
        ];
    }

    /** `Modules/{Modulo}/Tests/Feature/{contexto}/{SubFuncion}` */
    protected function carpetaDelGrupo(string $modulo, string $carpetaContexto, string $subFuncion): string
    {
        return base_path("Modules/{$modulo}/Tests/Feature")
            . ($carpetaContexto !== '' ? '/' . $carpetaContexto : '')
            . '/' . $subFuncion;
    }

    /**
     * Comprueba que el grupo está entero **antes** de ejecutar nada.
     *
     * Faltar una pieza no es «una prueba menos»: sin el manifiesto ninguna de las otras puede derivar
     * las rutas ni los permisos, y sin la base todas repiten la derivación o fallan al instanciarse.
     * Lanzar la cascada sobre un grupo incompleto produce fallos que describen el síntoma y esconden
     * la causa.
     */
    protected function grupoCompleto(string $grupo, string $prefijo, string $subFuncion): bool
    {
        $faltan = [];

        foreach (TestNames::allPieces($prefijo, $subFuncion) as $pieza) {
            if (! File::exists("{$grupo}/{$pieza}.php")) {
                $faltan[] = "{$pieza}.php";
            }
        }

        if ($faltan === []) {
            return true;
        }

        $this->fallo(
            'al grupo le faltan ' . count($faltan) . " pieza(s):\n    " . implode("\n    ", $faltan),
            'complétalas con /ajustar-pruebas, o regenera la subfuncionalidad.',
            'No se ejecuta nada: sin el manifiesto las demás no pueden derivar rutas ni permisos, y '
            . 'lo que saldría serían fallos que describen el síntoma y esconden la causa.'
        );

        return false;
    }

    /**
     * Las piezas, en orden, hasta que una falle.
     */
    protected function correrCascada(PhpunitRunner $runner, string $grupo, string $prefijo, string $subFuncion): int
    {
        $piezas    = TestNames::cascadeFor($prefijo, $subFuncion);
        $filtro    = trim((string) $this->option('filter')) ?: null;
        $continuar = (bool) $this->option('continuar');

        $fallidas   = [];
        $ejecutadas = 0;

        foreach ($piezas as $indice => $pieza) {
            $archivo = "{$grupo}/{$pieza['clase']}.php";

            $this->line("  <options=bold>{$pieza['clase']}</>  <fg=gray>{$pieza['cubre']}</>");

            $resultado = $runner->ejecutar($archivo, $filtro);
            $ejecutadas++;

            if ($resultado['ok']) {
                $this->components->twoColumnDetail('', '<fg=green>pasó</>');

                continue;
            }

            $fallidas[] = $pieza;

            $this->components->twoColumnDetail('', '<fg=red>FALLÓ</>');
            $this->newLine();
            $this->line($resultado['salida']);

            if ($continuar) {
                continue;
            }

            $this->cortar($piezas, $indice, $pieza);

            return self::FAILURE;
        }

        $this->recordarElTemaSeis($prefijo, $subFuncion);

        if ($fallidas !== []) {
            $this->fallo(
                count($fallidas) . ' de ' . $ejecutadas . ' piezas fallaron.',
                'clasifica el rojo antes de depurar: ¿base sucia (re-clona y repite solo esa pieza)? '
                . '¿intermitente (repítela 3 veces)? Solo si no es ninguna de las dos, es un defecto.',
                'Empezar por el código convierte una base contaminada en horas de depuración sobre '
                . 'código correcto.'
            );

            return self::FAILURE;
        }

        $this->components->info("El contrato de {$subFuncion} está en verde: {$ejecutadas} piezas.");

        return self::SUCCESS;
    }

    /**
     * Dice qué se dejó de ejecutar y por qué — que es la mitad útil del corte.
     *
     * Cortar sin decirlo se lee como «el resto pasó». Lo que se está afirmando es lo contrario: que
     * el resto **no se sabe**, y que averiguarlo antes de arreglar esto no serviría de nada.
     *
     * @param array<int, array{clase: string, sufijo: string, cubre: string}> $piezas
     * @param array{clase: string, sufijo: string, cubre: string}             $fallida
     */
    protected function cortar(array $piezas, int $indice, array $fallida): void
    {
        $pendientes = array_slice($piezas, $indice + 1);

        $this->newLine();
        $this->components->error(
            "Se corta en {$fallida['clase']} — {$fallida['cubre']}."
        );

        if ($pendientes === []) {
            return;
        }

        $this->line('  <fg=yellow>Sin ejecutar (' . count($pendientes) . '), porque dependen de lo anterior:</>');

        foreach ($pendientes as $pendiente) {
            $this->line("    · {$pendiente['clase']}  <fg=gray>{$pendiente['cubre']}</>");
        }

        $this->newLine();
        $this->line('  <fg=gray>Arregla lo de arriba y vuelve a lanzarlo: lo que viene después falla por');
        $this->line('  la misma causa, y treinta rojos del mismo problema cuestan más de leer que uno.</>');

        $this->newLine();
    }

    /**
     * El tema 6 existe, y no lo ejecuta este comando.
     *
     * Se nombra siempre —también cuando todo pasa— porque un contrato «en verde» que se ha saltado un
     * tema sin decirlo es exactamente la clase de silencio que esta fase vino a quitar.
     */
    protected function recordarElTemaSeis(string $prefijo, string $subFuncion): void
    {
        $componente = $prefijo . $subFuncion . TestNames::VITEST_SUFFIX;

        $this->newLine();
        $this->line("  <fg=gray>Tema 6 — la vista ({$componente}) la ejecuta Vitest, no este comando:");
        $this->line('  su runner es del proyecto anfitrión. No está incluido en lo de arriba.</>');
        $this->newLine();
    }
}
