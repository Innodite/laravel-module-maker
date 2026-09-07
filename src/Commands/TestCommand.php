<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\PrintsHeader;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Services\PhpunitRunner;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Innodite\LaravelModuleMaker\Support\LegacyManifests;
use Innodite\LaravelModuleMaker\Support\TestDatabase;
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
 * **qué** falló, **qué cubría** y **qué queda sin ejecutar**.
 *
 * El tema 6 —la vista— no se ejecuta aquí: es JavaScript y lo corre Vitest, cuya infraestructura es
 * del proyecto anfitrión. Se nombra al final para que nadie lo dé por corrido.
 */
class TestCommand extends Command
{
    use PrintsHeader;
    use ReportsFailures;

    protected $signature = 'innodite:test
        {module      : Módulo al que pertenece (ej: Invoice)}
        {subfeature  : Subfuncionalidad cuyo contrato se ejecuta (ej: Payment)}
        {--context=  : Contexto donde vive, en multitenant: central | tenant. En aplicación única no se pasa}
        {--filter=   : Patrón de PHPUnit, para acotar dentro de una pieza}
        {--continuar : Ejecuta el grupo entero aunque una pieza falle, sin corte temprano}
        {--reclonar : Rehace la base de pruebas antes de empezar, sin mirar en qué estado está}
        {--sin-reclonar : No la rehace tras un rojo — para cuando se está investigando justo eso}
        {--repetir=1 : Repite la pieza que falló N veces, para distinguir intermitente de rota}';

    protected $description = 'Ejecuta el contrato de pruebas de una subfuncionalidad, en cascada y con corte temprano.';

    /**
     * Las piezas que pasaron SOLO después de re-clonar la base.
     *
     * Se guardan porque el informe final no puede llamarlas verdes: que una prueba deje de fallar al
     * limpiar la base no la absuelve — señala que algo la está ensuciando, y eso sigue ahí.
     *
     * @var array<int, string>
     */
    protected array $pasaronTrasReclonar = [];

    public function handle(PhpunitRunner $runner): int
    {
        $modulo     = Str::studly((string) $this->argument('module'));
        $subFuncion = Str::studly((string) $this->argument('subfeature'));

        $this->cabecera("Contrato de {$modulo}/{$subFuncion}");

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

        if (! $this->prepararLaBase()) {
            return self::FAILURE;
        }

        return $this->correrCascada($runner, $grupo, $prefijo, $subFuncion);
    }

    // ─── La base de pruebas, antes de lanzar nada ───────────────────────

    /**
     * Comprueba —y si hace falta clona— la base contra la que se va a correr.
     *
     * Tres preguntas, y solo la primera detiene la ejecución:
     *
     *   1. **¿Es de pruebas?** Una suite apuntada a la base real no falla: pasa, y de camino borra
     *      datos de producción. Aquí se para.
     *   2. **¿Está?** Sin base no hay prueba, y preguntar es una pausa que no decide nada: se clona.
     *   3. **¿Tiene la forma de la real?** Una `_test` clonada hace dos migraciones está verde sobre
     *      un esquema que nadie ejecuta. Ese verde es peor que un rojo — es el despliegue que va a
     *      fallar con las pruebas en verde.
     *
     * ⛔ Y no se re-clona en cada corrida. Limpiar siempre esconde lo que ensucia: en kapitalizando la
     * misma pareja de pruebas falló tres veces por contaminación antes de que alguien lo anotara, y
     * esa reincidencia es lo que llevó al defecto de fondo.
     */
    protected function prepararLaBase(): bool
    {
        // La conexión de la SUITE, no la de la aplicación: este comando corre por artisan, fuera de
        // PHPUnit, así que `database.default` responde por la app —la base real— y con eso se le
        // denegaba la ejecución a un proyecto correctamente configurado (ver TestDatabase::laDeLaSuite).
        $conexion = TestDatabase::laDeLaSuite();
        $nombre   = TestDatabase::nombreDe($conexion);

        if (! $this->baseClonable()) {
            return true;
        }

        if (! TestDatabase::esDePruebas($nombre)) {
            $this->fallo(
                "la conexión '{$conexion}' apunta a {$nombre}, que no es una base de pruebas.",
                'apunta la conexión de testing a una base terminada en ' . TestDatabase::SUFIJO
                . ' — en phpunit.xml, DB_DATABASE=' . $nombre . TestDatabase::SUFIJO,
                'Una suite contra la base real no falla: pasa, y de camino se lleva datos por delante.'
            );

            return false;
        }

        if ((bool) $this->option('reclonar')) {
            return $this->clonar('porque se pidió con --reclonar');
        }

        if (TestDatabase::desfasada($conexion)) {
            return $this->clonar('porque falta o su esquema no coincide con el de la base real');
        }

        return true;
    }

    /**
     * ¿Tiene sentido hablar de clonar esta base?
     *
     * No lo tiene sin base declarada, y no lo tiene con `:memory:` — que no puede ser la base de
     * nadie: nace vacía en cada proceso y muere con él. No hay datos que proteger ni esquema real del
     * que desfasarse, así que las tres preguntas de la clasificación sobran ahí.
     */
    protected function baseClonable(): bool
    {
        $nombre = TestDatabase::nombreDe(TestDatabase::laDeLaSuite());

        return $nombre !== '' && $nombre !== ':memory:';
    }

    /** Rehace la base de pruebas invocando al comando que sabe hacerlo, y lo dice. */
    protected function clonar(string $motivo): bool
    {
        $this->components->twoColumnDetail('Base de pruebas', "<fg=yellow>se reclona {$motivo}</>");

        $codigo = $this->call('innodite:crear-bd-test', [
            '--connection' => TestDatabase::registrarReal(TestDatabase::laDeLaSuite()),
            '--force'      => true,
        ]);

        if ($codigo !== self::SUCCESS) {
            $this->fallo(
                'no se pudo rehacer la base de pruebas.',
                'míralo con php artisan innodite:crear-bd-test --dry-run, que enseña qué haría.',
                'Sin base con la forma real, lo que salga de aquí no dice nada del despliegue.'
            );

            return false;
        }

        return true;
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

    /**
     * `Modules/{Modulo}/{SubFuncion}/Tests/Feature/{contexto}` — donde el generador las escribe.
     *
     * El orden del árbol: la subfuncionalidad manda, la capa va dentro y el contexto es la hoja. Es
     * el mismo que compone `AbstractComponentGenerator::buildPath()` al generarlas; si los dos
     * dejan de coincidir, el comando anuncia que «no hay grupo de pruebas» sobre una subfuncionalidad
     * que lo tiene entero.
     */
    protected function carpetaDelGrupo(string $modulo, string $carpetaContexto, string $subFuncion): string
    {
        return base_path("Modules/{$modulo}/{$subFuncion}/Tests/Feature")
            . ($carpetaContexto !== '' ? '/' . $carpetaContexto : '');
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
            'regenera la subfuncionalidad con innodite:add-entity, o escribe las piezas que faltan '
            . 'tomando como modelo las que ya están.',
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

            $this->components->twoColumnDetail('', '<fg=red>FALLÓ</>');
            $this->newLine();
            $this->line($resultado['salida']);

            if ($this->clasificar($runner, $archivo, $filtro, $pieza)) {
                continue;   // era la base, y ya se dijo que pasó TRAS re-clonar
            }

            $fallidas[] = $pieza;

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
                'ya se descartó base sucia e intermitencia: lo que queda es un defecto, y ahí sí se '
                . 'abre el código.',
                'Empezar por el código convierte una base contaminada en horas de depuración sobre '
                . 'código correcto.'
            );

            return self::FAILURE;
        }

        if ($this->pasaronTrasReclonar !== []) {
            $this->components->warn(
                'Pasó tras re-clonar: ' . implode(', ', $this->pasaronTrasReclonar)
                . '. ⛔ No es verde limpio.'
            );
            $this->line('  <fg=gray>Algo está ensuciando la base y sigue ahí. El sospechoso habitual es un '
                . 'seeder cuyo runMigrations ejecuta DDL: MySQL commitea implícitamente y las filas');
            $this->line('  sobreviven a la transacción de la prueba.</>');

            return self::FAILURE;
        }

        $this->components->info("El contrato de {$subFuncion} está en verde: {$ejecutadas} piezas.");

        return self::SUCCESS;
    }

    /**
     * Clasifica el rojo antes de que nadie abra el código — los tres pasos en orden.
     *
     * Un rojo significa tres cosas distintas y las tres se ven igual, así que se descartan por orden
     * de coste: **base sucia** (re-clonar y repetir solo esa pieza), **intermitente** (repetirla), y
     * solo entonces **defecto**. Empezar por el tercero —el reflejo natural— convierte una base
     * contaminada en horas de depuración sobre código correcto.
     *
     * @param  array{clase: string, sufijo: string, cubre: string}  $pieza
     * @return bool  true si era la base y la pieza pasó al repetirla
     */
    protected function clasificar(PhpunitRunner $runner, string $archivo, ?string $filtro, array $pieza): bool
    {
        $this->line('  <fg=cyan>Clasificando el rojo antes de investigarlo:</>');

        // 1 · ¿Base sucia?
        if ((bool) $this->option('sin-reclonar')) {
            $this->line('  <fg=gray>1 · ¿base sucia? — sin comprobar, se pidió --sin-reclonar</>');
        } elseif (! $this->baseClonable()) {
            $this->line('  <fg=gray>1 · ¿base sucia? — sin comprobar: esta conexión no se puede clonar</>');
        } elseif ($this->clonar('para descartar que la base esté sucia')) {
            if ($runner->ejecutar($archivo, $filtro)['ok']) {
                $this->components->twoColumnDetail(
                    '1 · ¿base sucia?',
                    '<fg=yellow>SÍ — pasó tras re-clonar</>'
                );

                $this->pasaronTrasReclonar[] = $pieza['clase'];

                return true;
            }

            $this->components->twoColumnDetail('1 · ¿base sucia?', '<fg=gray>no — sigue roja</>');
        }

        // 2 · ¿Intermitente?
        $repeticiones = max(1, (int) $this->option('repetir'));

        if ($repeticiones > 1) {
            $pasadas = 0;

            for ($i = 0; $i < $repeticiones; $i++) {
                $pasadas += $runner->ejecutar($archivo, $filtro)['ok'] ? 1 : 0;
            }

            if ($pasadas > 0) {
                $this->components->twoColumnDetail(
                    '2 · ¿intermitente?',
                    "<fg=yellow>SÍ — pasó {$pasadas} de {$repeticiones}</>"
                );
                $this->line('  <fg=gray>Le falta un desempate, un orden o un instante fijo. No es la base '
                    . 'y no es el código: es la prueba.</>');

                return false;
            }

            $this->components->twoColumnDetail(
                '2 · ¿intermitente?',
                "<fg=gray>no — falló {$repeticiones} de {$repeticiones}</>"
            );
        } else {
            $this->line('  <fg=gray>2 · ¿intermitente? — sin comprobar: pásale --repetir=3</>');
        }

        // 3 · Defecto
        $this->components->twoColumnDetail('3 · ¿defecto?', '<fg=red>sí — aquí sí se abre el código</>');

        return false;
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
