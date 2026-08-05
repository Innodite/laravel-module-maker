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
        'R30: el error dice qué está mal y cuáles son los valores válidos.'
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

it('los tres modos que ofrece el instalador son los tres del enum', function () {
    // Si alguien añade un cuarto modo al enum y no aparece en el instalador, el proyecto no podría
    // elegirlo al instalar — que es el único momento en que la norma permite elegirlo.
    expect(array_column(ModuleMode::cases(), 'value'))->toBe([
        'single-app',
        'multitenant-shared',
        'multitenant-per-tenant',
    ]);
});
