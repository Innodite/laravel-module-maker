<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * La guarda que impide desplegar contra una conexión que no existe.
 *
 * `contexts.json` declara `connection_key`, pero quien tiene que tener esa conexión configurada es
 * `config/database.php` del proyecto. Cuando no la tiene, Laravel falla mucho más adentro y con un
 * mensaje que no dice qué contexto la pedía: por eso se comprueba antes, nombrando a los dos.
 *
 * **El vehículo es `innodite:migrate-one`** desde que `innodite:migrate-plan` se retiró. La guarda
 * es la misma —vive en `MigrationTargetService`, que los dos usaban—; lo que cambia es que aquí se
 * nombra UNA migración, así que el archivo tiene que existir para llegar hasta la comprobación.
 *
 * **Y la salida se lee cruda, con `Artisan::output()`.** El ayudante `$this->artisan()` envuelve el
 * párrafo por el ancho del terminal y parte los nombres a mitad de palabra: la prueba pasaba a
 * depender de dónde cortaba la línea y no de lo que decía el mensaje.
 */

/** Ejecuta el comando y devuelve [código, salida sin envolver]. */
function migracionSuelta(array $opciones): array
{
    $codigo = Artisan::call('innodite:migrate-one', $opciones + ['--no-interaction' => true]);

    return [$codigo, Artisan::output()];
}

/** Deja el trait y el archivo de una migración en el contexto indicado. */
function migracionDeclaradaEn(string $carpeta, string $modulo, string $archivo): void
{
    $migracion = "Modules/{$modulo}/Database/Migrations/{$carpeta}/{$archivo}";

    $rutaMigracion = test()->tempPath($migracion);
    File::ensureDirectoryExists(dirname($rutaMigracion));
    File::put($rutaMigracion, "<?php\n");

    $destinoTrait = test()->tempPath("Modules/{$modulo}/Database/Seeders/{$carpeta}/Thing");
    File::ensureDirectoryExists($destinoTrait);
    File::put(
        "{$destinoTrait}/{$modulo}ThingMigrationsList.php",
        "<?php\n\ntrait {$modulo}ThingMigrationsList\n{\n    protected function migrations(): array\n"
        . "    {\n        return [\n            '{$migracion}',\n        ];\n    }\n}\n"
    );
}

it('el eje del inquilino NO exige conexión: la conmuta la tenencia', function () {
    // El contexto `tenant` no declara `connection_key` a propósito, así que la migración se ejecuta
    // contra la conexión activa —la que conmutó el paquete de tenencia al entrar en el cliente—.
    // Exigirle una dejaría el eje entero sin poder migrar.
    // Se mide en el servicio que decide, no lanzando el comando entero: lo que aquí importa es que
    // el contexto del inquilino RESUELVE su destino sin `connection_key`, y no que una migración de
    // mentira llegue a ejecutarse.
    config()->set('make-module.mode', 'multitenant');

    $conexion = (new \Innodite\LaravelModuleMaker\Services\MigrationTargetService())
        ->resolveExecutionConnection('tenant', true);

    expect($conexion)->toBe(
        (string) config('database.default'),
        'El inquilino migra contra la conexión ACTIVA —la que conmutó la tenencia al entrar en el '
        . 'cliente—. Exigirle un connection_key dejaría el eje entero sin poder migrar.'
    );
});

it('falla igual cuando la que falta es la de la aplicación central', function () {
    migracionDeclaradaEn('Central', 'User', '2026_01_01_000001_crea_usuarios.php');

    [$codigo, $salida] = migracionSuelta([
        'coordinate' => 'User:Central/2026_01_01_000001_crea_usuarios.php',
        '--force' => true,
    ]);

    expect($salida)->toContain("'central' del contexto 'central'");
    expect($codigo)->not->toBe(0);
});

it('en dry-run no se exige la conexión: se está mirando, no ejecutando', function () {
    migracionDeclaradaEn('Central', 'User', '2026_01_01_000001_crea_usuarios.php');

    [$codigo] = migracionSuelta([
        'coordinate' => 'User:Central/2026_01_01_000001_crea_usuarios.php',
        '--dry-run' => true,
    ]);

    expect($codigo)->toBe(0);
});

it('un contexto que no existe se rechaza diciendo dónde se buscó', function () {
    // Y no «no hay migraciones para ese contexto», que es lo que haría creer que el trait falta
    // cuando lo que está mal escrito es el nombre del contexto.
    //
    // La migración existe a propósito: si no, el comando se detiene antes por la coordenada y la
    // prueba pasaría por el motivo equivocado — verde sin haber llegado a mirar el contexto.
    migracionDeclaradaEn('Central', 'User', '2026_01_01_000001_crea_usuarios.php');

    [$codigo, $salida] = migracionSuelta([
        'coordinate' => 'User:Central/2026_01_01_000001_crea_usuarios.php',
        '--context' => 'inventado',
        '--dry-run' => true,
    ]);

    expect($salida)->toContain("No se encontró el contexto 'inventado'");
    expect($codigo)->not->toBe(0);
});
