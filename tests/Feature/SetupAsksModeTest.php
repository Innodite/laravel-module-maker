<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El modo se elige al instalar, no se teclea después en un archivo.
 *
 * La diferencia importa porque el modo no es una preferencia: decide el eje de contexto, el prefijo
 * de cada clase, la conexión del modelo y el middleware de cada ruta. Un proyecto que instala y
 * genera antes de elegirlo produce una estructura equivocada multiplicada por cada módulo — y como
 * los archivos existen y compilan, eso no se descubre hasta mucho después.
 */

it('escribe el modo elegido en el .env', function () {
    $env = base_path('.env');
    File::put($env, "APP_NAME=Testbench\nAPP_ENV=testing\n");

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    expect(File::get($env))->toContain(
        'MODULE_MAKER_MODE=single-app'
    );

    File::delete($env);
});

it('no vuelve a escribir si el .env ya declaraba ese mismo modo', function () {
    $env = base_path('.env');
    File::put($env, "APP_NAME=Testbench\nMODULE_MAKER_MODE=single-app\n");
    $antes = File::get($env);

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    expect(File::get($env))->toBe(
        $antes,
        'Si el modo ya es el que se pide, el archivo del usuario no se toca. Reescribirlo por gusto '
        . 'es tocar el .env de alguien sin motivo.'
    );

    File::delete($env);
});

it('rechaza un modo que no existe, y dice cuáles hay', function () {
    Artisan::call('innodite:module-setup', ['--mode' => 'multi-tenant', '--no-interaction' => true]);

    $salida = Artisan::output();

    expect($salida)->toContain('multitenant-shared');
    expect(str_contains($salida, 'no existe'))->toBeTrue(
        'el error dice qué está mal y cuáles son los valores válidos.'
    );
});

it('sin .env dice exactamente qué línea añadir, en vez de anunciar que lo hizo', function () {
    $env = base_path('.env');

    if (File::exists($env)) {
        File::delete($env);
    }

    Artisan::call('innodite:module-setup', ['--mode' => 'multitenant-shared', '--no-interaction' => true]);

    $salida = Artisan::output();

    expect($salida)->toContain('MODULE_MAKER_MODE=multitenant-shared');
    expect(str_contains($salida, 'no se ha escrito'))->toBeTrue(
        'La lección de A15: el comando anunciaba «DatabaseSeeder modificado» aunque no hubiera '
        . 'tocado nada, y el usuario se quedaba creyendo que estaba configurado. Un éxito que no '
        . 'ocurrió no se anuncia.'
    );
});

it('sin modo y sin poder preguntar, no sigue adelante en silencio', function () {
    Artisan::call('innodite:module-setup', ['--no-interaction' => true]);

    expect(Artisan::output())->toContain(
        '--mode=single-app'
    );
});

// ─── Y con el modo, el paquete de tenencia ───────────────────────────────────────────────────
//
// Misma razón que el modo: decide la forma de lo que se genera —la envoltura de cada archivo de
// rutas— y por eso se elige al instalar. La diferencia es a quién se le pregunta: solo a los modos
// que tienen tenants.

it('en multitenant escribe también el paquete de tenencia en el .env', function () {
    $env = base_path('.env');
    File::put($env, "APP_NAME=Testbench\nAPP_ENV=testing\n");

    Artisan::call('innodite:module-setup', [
        '--mode'           => 'multitenant-shared',
        '--tenancy'        => 'stancl',
        '--no-interaction' => true,
    ]);

    expect(File::get($env))->toContain('MODULE_MAKER_TENANCY_PACKAGE=stancl');

    File::delete($env);
});

it('en single-app no pregunta el paquete de tenencia ni lo escribe', function () {
    // No hay dominios centrales que separar ni tenant que identificar: la respuesta no cambiaría un
    // solo archivo generado, y pedirla es pedir una decisión que no decide nada.
    $env = base_path('.env');
    File::put($env, "APP_NAME=Testbench\nAPP_ENV=testing\n");

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    expect(File::get($env))->not->toContain('MODULE_MAKER_TENANCY_PACKAGE');

    File::delete($env);
});

it('en multitenant no sigue adelante sin elegir el paquete de tenencia', function () {
    // Lo mismo que el modo, y por lo mismo: dejar la clave sin declarar apuesta a que el usuario
    // lea la configuración antes de generar su primer módulo. No la lee — genera, ve archivos
    // escritos y sigue, con las rutas sin envoltura.
    //
    // «none» es respuesta válida, pero elegida: es distinto de no responder.
    Artisan::call('innodite:module-setup', [
        '--mode'           => 'multitenant-per-tenant',
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect($salida)->toContain('--tenancy=stancl');
    expect(str_contains($salida, 'no se puede omitir'))->toBeTrue(
        'FALLA: la instalación siguió sin paquete de tenencia declarado. · FIX: en multitenant el '
        . "paso es obligatorio.\nLa salida dice:\n" . $salida
    );
});

it('rechaza un paquete de tenencia que no soporta, y dice cuáles hay', function () {
    Artisan::call('innodite:module-setup', [
        '--mode'           => 'multitenant-shared',
        '--tenancy'        => 'tenancy-for-laravel',
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect($salida)->toContain('stancl');
    expect(str_contains($salida, 'FIX:'))->toBeTrue(
        'el mensaje trae el error y cómo corregirlo, no solo el rechazo.'
    );
});

it('los tres modos que ofrece el instalador son los tres del enum', function () {
    // Si alguien añade un cuarto modo al enum y no aparece en el instalador, el proyecto no podría
    // elegirlo al instalar — que es el único momento en que la norma permite elegirlo.
    expect(array_column(ModuleMode::cases(), 'value'))->toBe([
        'single-app',
        'multitenant-shared',
        'multitenant-per-tenant',
    ]);
});

// ── Lo que hace falta para poder EMPEZAR en un proyecto limpio ─────────────────────────────────

it('el instalador publica config/make-module.php, que es donde se declara el orden', function () {
    // Medido instalando en un Laravel limpio: el despliegue manda «añádelas a `deploy` en
    // config/make-module.php» y ese archivo no existía en el proyecto. Estaba declarado en
    // `publishes()`, es decir, solo llegaba con un `vendor:publish` que nadie ejecuta — y hay que
    // saber que existe para ejecutarlo.
    //
    // El modo y el paquete de tenencia viven en el .env, así que el paquete arrancaba sin esto; el
    // orden de despliegue es un array y no cabe en una variable de entorno.
    $destino = config_path('make-module.php');

    if (File::exists($destino)) {
        File::delete($destino);
    }

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    expect(File::exists($destino))->toBeTrue(
        'FALLA: sin config/make-module.php no hay dónde declarar el orden de despliegue. · '
        . 'FIX: el instalador tiene que publicarlo.'
    );
    expect(File::get($destino))->toContain("'deploy'");
});

it('no pisa la configuración que el proyecto ya tenía', function () {
    // Dentro vive el orden de despliegue, que crece con cada subfuncionalidad: reinstalar no puede
    // llevárselo por delante.
    $destino = config_path('make-module.php');
    File::ensureDirectoryExists(dirname($destino));
    File::put($destino, "<?php return ['deploy' => ['Lo/Mio']];");

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    expect(File::get($destino))->toContain('Lo/Mio');
});
