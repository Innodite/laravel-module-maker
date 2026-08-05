<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Exceptions\GeneratedFileRejectedException;
use Innodite\LaravelModuleMaker\Support\GeneratedFileCheck;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use PHPUnit\Framework\Assert;

/**
 * El arnés: genera un módulo de verdad y contrasta lo que quedó escrito.
 *
 * Las pruebas de la v3 comprobaban que los archivos **se crearan**. Los cinco críticos que
 * costaron la primera fase —B13, B15, B17, la segunda mitad de B13 y el marcador de B14— pasaban
 * esa comprobación sin despeinarse: los archivos existían. Ninguno es un error de lógica; todos
 * son **dos mitades que dejaron de coincidir** —la clave con otro formato, el stub pidiendo lo que
 * nadie entrega, el namespace contra su carpeta, el import contra el archivo que nadie escribió—.
 * Un análisis estático las lee bien todas, y por eso el arnés no lee: **genera y contrasta**.
 *
 * De ahí su forma, que es la división del trabajo con el chequeo de salida:
 *
 *   Lo que se puede juzgar de un archivo A SOLAS  → `assertEveryFileWouldBeAccepted()`, que
 *     delega en `GeneratedFileCheck` en vez de reimplementar sus preguntas. Ese es el sitio donde
 *     vive el criterio, y cuando la red gane una cuarta pregunta —como ganó la tercera con B17—
 *     el arnés la aplica el mismo día, sin tocarse.
 *
 *   Lo que SOLO se ve con el árbol entero delante → los cruces de aquí abajo: un import que
 *     apunta a un archivo que nadie escribió, una clase que no se llama como su archivo. Ningún
 *     chequeo por archivo puede verlos, porque le falta el otro lado del par.
 *
 * Las fases 2 a 6 lo usan en una línea: `$this->generateModule('Invoice')->assertCoherent()`, y
 * encima de eso afirman lo suyo. Es también lo que dice R77 —el andamiaje no se prueba por
 * existencia, se evalúa por contenido— aplicado a lo que este paquete genera.
 */
final class GeneratedModule
{
    /** @var array<int, string>|null */
    private ?array $tree = null;

    private string $output = '';

    private function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly ModuleMode $mode,
    ) {
    }

    /**
     * Ejecuta el comando real y devuelve el módulo escrito, o falla con la salida completa.
     *
     * Que el fallo de generación se distinga del fallo de la afirmación importa: hasta ahora cada
     * prueba tenía que recordarlo en un mensaje suyo («si el árbol está vacío, lee la salida del
     * comando»). Aquí lo hace el arnés, con la salida delante.
     *
     * @param array<string, mixed> $options Opciones extra del comando; sobrescriben las de serie
     */
    public static function generate(
        string $name,
        string $modulesRoot,
        ModuleMode $mode,
        ?string $context = null,
        array $options = [],
    ): self {
        config()->set('make-module.mode', $mode->value);

        $module = new self($name, rtrim($modulesRoot, '/') . '/' . $name, $mode);

        // Sin rutas por defecto: la inyección necesita el `routes/web.php` del proyecto anfitrión,
        // que en una prueba no es el objeto de estudio. Quien lo necesite lo pide con options.
        $defaults = [
            'name'             => $name,
            '--no-routes'      => true,
            '--no-interaction' => true,
        ];

        if ($context !== null) {
            $defaults['--context'] = $context;
        }

        $exitCode        = Artisan::call('innodite:make-module', array_merge($defaults, $options));
        $module->output  = Artisan::output();

        if ($exitCode !== 0) {
            Assert::fail(
                "La generación de '{$name}' ({$mode->value}) terminó con código {$exitCode}.\n\n"
                . "Salida del comando:\n" . $module->output
            );
        }

        if ($module->tree() === []) {
            Assert::fail(
                "La generación de '{$name}' ({$mode->value}) no escribió un solo archivo en {$module->path}.\n\n"
                . "Salida del comando:\n" . $module->output
            );
        }

        return $module;
    }

    // ── Lo que quedó en el disco ────────────────────────────────────────────────────────────

    /** @return array<int, string> Rutas relativas de los archivos, ordenadas. */
    public function tree(): array
    {
        if ($this->tree !== null) {
            return $this->tree;
        }

        if (! File::isDirectory($this->path)) {
            return $this->tree = [];
        }

        $rutas = [];

        foreach (File::allFiles($this->path) as $archivo) {
            $rutas[] = str_replace('\\', '/', $archivo->getRelativePathname());
        }

        sort($rutas);

        return $this->tree = $rutas;
    }

    /**
     * @return array<int, string> Rutas relativas de las CARPETAS, ordenadas.
     *
     * Una carpeta vacía no aparece en el árbol de archivos, y ahí se escondía lo de 004b:
     * `Central/` se sembraba en las doce capas de un proyecto sin tenants sin que nada lo viera.
     * No rompe, pero sugiere una estructura que el modo dice que no existe — y alguien la usa.
     */
    public function directories(): array
    {
        if (! File::isDirectory($this->path)) {
            return [];
        }

        $rutas = [];
        $base  = strlen($this->path) + 1;

        $pendientes = [$this->path];

        while ($pendientes !== []) {
            foreach (File::directories(array_pop($pendientes)) as $dir) {
                $rutas[]      = str_replace('\\', '/', substr($dir, $base));
                $pendientes[] = $dir;
            }
        }

        sort($rutas);

        return $rutas;
    }

    /** @return array<int, string> Rutas relativas de los archivos PHP. */
    public function phpFiles(): array
    {
        return array_values(array_filter(
            $this->tree(),
            static fn (string $ruta): bool => str_ends_with(strtolower($ruta), '.php'),
        ));
    }

    public function path(?string $relative = null): string
    {
        return $relative === null ? $this->path : "{$this->path}/{$relative}";
    }

    public function has(string $relative): bool
    {
        return File::exists($this->path($relative));
    }

    /** Contenido de un archivo generado; si no está, el fallo trae el árbol entero. */
    public function contents(string $relative): string
    {
        if (! $this->has($relative)) {
            Assert::fail(
                "El módulo {$this->name} no tiene '{$relative}'.\n\nLo generado fue:\n  - "
                . implode("\n  - ", $this->tree())
            );
        }

        return File::get($this->path($relative));
    }

    /** La salida del comando, para cuando una prueba quiera afirmar sobre lo que se le dijo al usuario. */
    public function output(): string
    {
        return $this->output;
    }

    // ── Los contrastes ──────────────────────────────────────────────────────────────────────

    /**
     * Las tres afirmaciones que toda fase quiere, en una línea.
     *
     * Lo de serie, para que ninguna fase tenga que acordarse: nada sin resolver, cada clase donde
     * su nombre dice, y ningún import apuntando al vacío.
     */
    public function assertCoherent(): self
    {
        return $this
            ->assertEveryFileWouldBeAccepted()
            ->assertClassNamesMatchFiles()
            ->assertInternalImportsExist();
    }

    /**
     * Cada archivo del árbol, pasado por el chequeo de salida.
     *
     * No repite sus preguntas: se las hace. La red es la que sabe qué es un placeholder sin
     * resolver, qué PHP no parsea y qué namespace no espeja su carpeta — y cada vez que aprende
     * una pregunta nueva, esta afirmación la hereda sin tocarse.
     *
     * Y no es redundante con el chequeo que corre al escribir: aquel valida el archivo **en el
     * momento de escribirlo**; este valida **el estado final del árbol**, incluido lo que se
     * reescribió después, lo que inyectó otro paso y lo que se escribió por una vía que todavía
     * no pasa por el trait. Es la distinción de R77 aplicada al paquete.
     */
    public function assertEveryFileWouldBeAccepted(): self
    {
        $rechazados = [];

        foreach ($this->tree() as $relativa) {
            try {
                GeneratedFileCheck::assertWritable($this->path($relativa), $this->contents($relativa));
            } catch (GeneratedFileRejectedException $e) {
                $rechazados[$relativa] = $e->getMessage();
            }
        }

        Assert::assertSame(
            [],
            $rechazados,
            "El chequeo de salida rechaza " . count($rechazados) . " archivo(s) del módulo {$this->name}:\n\n"
            . implode("\n\n", $rechazados)
        );

        return $this;
    }

    /**
     * ¿La clase declarada se llama como su archivo?
     *
     * El otro par de mitades de PSR-4. El chequeo de salida ya compara el namespace con la
     * carpeta —esa fue la tercera pregunta, la que nació de B17—, pero el namespace correcto con
     * el nombre de clase cambiado deja la clase igual de inencontrable, y las dos primeras
     * preguntas dicen que sí. Las migraciones quedan fuera a propósito: declaran una clase
     * anónima, que es lo que Laravel espera de ellas.
     */
    public function assertClassNamesMatchFiles(): self
    {
        $desalineados = [];

        foreach ($this->phpFiles() as $relativa) {
            $contenido = $this->contents($relativa);

            $declaracion = '/^\s*(?:final\s+|abstract\s+)?(class|interface|trait|enum)\s+(\w+)/m';

            if (preg_match($declaracion, $contenido, $encontrado) !== 1) {
                continue;   // migración anónima, archivo de configuración, rutas
            }

            $esperado = basename($relativa, '.php');

            if ($encontrado[2] !== $esperado) {
                $desalineados[$relativa] = "declara {$encontrado[1]} {$encontrado[2]}, "
                    . "y el archivo se llama {$esperado}";
            }
        }

        Assert::assertSame(
            [],
            $desalineados,
            "PSR-4 busca la clase por el nombre del ARCHIVO. Estos no coinciden, así que no se "
            . "pueden autocargar:\n  - " . implode("\n  - ", array_map(
                static fn (string $ruta, string $queja): string => "{$ruta}: {$queja}",
                array_keys($desalineados),
                $desalineados,
            ))
        );

        return $this;
    }

    /**
     * ¿Cada `use Modules\<este módulo>\…` apunta a un archivo que este mismo árbol escribió?
     *
     * El cruce que faltaba cuando se arregló B13. El factory importaba `…\Permission` mientras el
     * modelo generado se llamaba `CentralPermission`: **sintaxis impecable, parser contento, y la
     * clase no existe**. Solo se ve poniendo el import contra el árbol, que es lo que ningún
     * chequeo por archivo puede hacer — le falta el otro lado.
     *
     * Se miran solo los imports del propio módulo: los de Laravel, los del paquete y los de otro
     * módulo del proyecto no se generan aquí, así que su ausencia no dice nada.
     */
    public function assertInternalImportsExist(): self
    {
        $prefijo = "Modules\\{$this->name}\\";
        $rotos   = [];

        foreach ($this->phpFiles() as $relativa) {
            preg_match_all(
                '/^\s*use\s+([A-Za-z_][A-Za-z0-9_\\\\]*)\s*(?:as\s+\w+\s*)?;/m',
                $this->contents($relativa),
                $encontrados,
            );

            foreach ($encontrados[1] as $clase) {
                if (! str_starts_with($clase, $prefijo)) {
                    continue;
                }

                $esperado = str_replace('\\', '/', substr($clase, strlen("Modules\\{$this->name}\\"))) . '.php';

                if (! $this->has($esperado)) {
                    $rotos[] = "{$relativa} importa {$clase}\n      → falta {$esperado}";
                }
            }
        }

        Assert::assertSame(
            [],
            $rotos,
            "Un import a una clase que nadie escribió pasa el parser y revienta al ejecutar. "
            . "Estos apuntan al vacío:\n  - " . implode("\n  - ", $rotos)
            . "\n\nLo generado fue:\n  - " . implode("\n  - ", $this->tree())
        );

        return $this;
    }

    /**
     * El árbol contiene estas rutas relativas — y el fallo dice cuáles faltan, no solo que falta algo.
     *
     * @param array<int, string> $relativas
     */
    public function assertTreeHas(array $relativas, string $porque = ''): self
    {
        $faltan = array_values(array_diff($relativas, $this->tree()));

        Assert::assertSame(
            [],
            $faltan,
            ($porque !== '' ? $porque . "\n" : '')
            . "Faltan en el módulo {$this->name}:\n  - " . implode("\n  - ", $faltan)
            . "\n\nLo generado fue:\n  - " . implode("\n  - ", $this->tree())
        );

        return $this;
    }

    /**
     * Ni una carpeta de contexto, ni un nombre con prefijo: la forma de single-app (R5 · R6).
     *
     * Mira archivos **y** carpetas, porque una carpeta de contexto vacía no aparece en el árbol
     * de archivos y sugiere igual una estructura que el modo dice que no existe.
     */
    public function assertNoContextAxis(): self
    {
        $ejes = ['Central', 'Shared', 'Tenant'];

        $conContexto = array_values(array_filter(
            array_merge($this->tree(), $this->directories()),
            static function (string $ruta) use ($ejes): bool {
                foreach (explode('/', dirname($ruta)) as $segmento) {
                    if (in_array($segmento, $ejes, true)) {
                        return true;
                    }
                }

                return false;
            },
        ));

        Assert::assertSame(
            [],
            $conContexto,
            "R5: en {$this->mode->value} no existe el eje de contexto, y aquí aparece:\n  - "
            . implode("\n  - ", $conContexto)
        );

        $conPrefijo = array_values(array_filter(
            $this->tree(),
            static fn (string $ruta): bool => str_starts_with(basename($ruta), 'Central')
                || str_starts_with(basename($ruta), 'TenantShared'),
        ));

        Assert::assertSame(
            [],
            $conPrefijo,
            "R6: sin contextos no hay nada que desambiguar, así que ningún nombre lleva prefijo:\n  - "
            . implode("\n  - ", $conPrefijo)
        );

        return $this;
    }

    /**
     * En multitenant el contexto entra en la carpeta Y en el nombre, en todas las capas.
     */
    public function assertContextAxis(string $carpeta, string $prefijo): self
    {
        $conCarpeta = array_filter(
            $this->tree(),
            static fn (string $ruta): bool => str_contains($ruta, "/{$carpeta}/"),
        );

        Assert::assertNotSame(
            [],
            $conCarpeta,
            "R5: en {$this->mode->value} el contexto '{$carpeta}' separa las capas, y no aparece en "
            . "ninguna ruta:\n  - " . implode("\n  - ", $this->tree())
        );

        $conPrefijo = array_filter(
            $this->tree(),
            static fn (string $ruta): bool => str_starts_with(basename($ruta), $prefijo),
        );

        Assert::assertNotSame(
            [],
            $conPrefijo,
            "R6: en multitenant el prefijo '{$prefijo}' desambigua clases del mismo nombre en dos "
            . "contextos, y ningún archivo lo lleva:\n  - " . implode("\n  - ", $this->tree())
        );

        return $this;
    }
}
