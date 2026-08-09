<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * D5 — el motor que ejecuta el tema 6, publicado en el proyecto anfitrión.
 *
 * El generador escribe la prueba de la vista desde la fase 4 y **nadie la ejecutaba**: Vitest, jsdom
 * y el plugin de Vue son dependencias del proyecto, no del paquete, así que el archivo nacía correcto
 * y sin motor. Se notó al cerrar F5, cuando hubo que montarlo todo a mano para correr una prueba que
 * llevaba una fase entera generándose — y la primera vez que se ejecutó falló 3 de sus 8 casos.
 *
 * Una prueba que nunca se ha ejecutado no es una prueba: es un archivo con la forma de una.
 */

beforeEach(function () {
    File::ensureDirectoryExists(resource_path('js'));
});

afterEach(function () {
    File::delete(base_path('vitest.config.js'));
    File::delete(base_path('package.json'));
});

it('publica la configuración de Vitest en el proyecto', function () {
    Artisan::call('innodite:publish-frontend', ['--no-interaction' => true]);

    $config = base_path('vitest.config.js');
    $salida = Artisan::output();

    expect(File::exists($config))->toBeTrue("FALLA: no publicó vitest.config.js.\n{$salida}");

    $contenido = File::get($config);

    // Las pruebas de vista viven DENTRO del módulo, junto al componente que prueban. Sin esa ruta en
    // el include, `vitest run` no encuentra ninguna y termina en verde sin haber ejecutado nada —
    // que es la peor forma de tener pruebas.
    expect(str_contains($contenido, 'Modules/**/resources/js/__tests__/**/*.test.js'))->toBeTrue(
        'FALLA: el include no mira dentro de los módulos, que es donde el generador las escribe.'
    );

    // El componente generado importa sus composables por '@'. Sin el alias, el vi.mock de la prueba
    // no resuelve y el fallo se lee como si el composable no existiera.
    expect(str_contains($contenido, "'@'"))->toBeTrue('FALLA: sin el alias @ los imports no resuelven.');
    expect(str_contains($contenido, 'jsdom'))->toBeTrue('FALLA: sin DOM no hay componente que montar.');
});

it('deja npm run test:js en el package.json, sin pisar el que ya hubiera', function () {
    File::put(base_path('package.json'), json_encode([
        'scripts'         => ['dev' => 'vite'],
        'devDependencies' => ['vitest' => '^2.0', '@vue/test-utils' => '^2.4', 'jsdom' => '^25.0',
                              '@vitejs/plugin-vue' => '^5.0'],
    ]));

    Artisan::call('innodite:publish-frontend', ['--no-interaction' => true]);

    $paquete = json_decode(File::get(base_path('package.json')), true);

    expect($paquete['scripts']['test:js'])->toBe('vitest run');
    expect($paquete['scripts']['dev'])->toBe('vite', 'FALLA: pisó un script del proyecto.');

    // Y si ya estaba, no lo toca: el package.json es del proyecto.
    File::put(base_path('package.json'), json_encode([
        'scripts'         => ['test:js' => 'vitest run --coverage'],
        'devDependencies' => ['vitest' => '^2.0', '@vue/test-utils' => '^2.4', 'jsdom' => '^25.0',
                              '@vitejs/plugin-vue' => '^5.0'],
    ]));

    Artisan::call('innodite:publish-frontend', ['--force' => true, '--no-interaction' => true]);

    expect(json_decode(File::get(base_path('package.json')), true)['scripts']['test:js'])
        ->toBe('vitest run --coverage', 'FALLA: pisó el script que el proyecto ya tenía.');
});

it('nombra las dependencias que faltan, con el npm exacto', function () {
    File::put(base_path('package.json'), json_encode(['devDependencies' => ['vitest' => '^2.0']]));

    Artisan::call('innodite:publish-frontend', ['--no-interaction' => true]);

    $salida = Artisan::output();

    foreach (['@vue/test-utils', 'jsdom', '@vitejs/plugin-vue'] as $dependencia) {
        expect(str_contains($salida, $dependencia))->toBeTrue(
            "FALLA: no dice que falta {$dependencia}.\n{$salida}"
        );
    }

    expect(str_contains($salida, 'npm i -D'))->toBeTrue(
        "FALLA: dice qué falta y no cómo instalarlo (R30).\n{$salida}"
    );

    // Y no reclama la que sí está: el comando que ofrece instala solo lo que falta.
    expect(str_contains($salida, '-D vitest'))->toBeFalse(
        "FALLA: pide instalar vitest, que ya está declarada.\n{$salida}"
    );
});

it('no pisa un vitest.config.js que el proyecto ya tenga, salvo --force', function () {
    File::put(base_path('vitest.config.js'), '// el mío');

    Artisan::call('innodite:publish-frontend', ['--no-interaction' => true]);

    expect(File::get(base_path('vitest.config.js')))->toBe(
        '// el mío',
        'FALLA: pisó la configuración del proyecto.'
    );

    Artisan::call('innodite:publish-frontend', ['--force' => true, '--no-interaction' => true]);

    expect(File::get(base_path('vitest.config.js')))->not->toBe('// el mío');
});

it('en ensayo no escribe ni la configuración ni el script', function () {
    File::put(base_path('package.json'), json_encode(['scripts' => ['dev' => 'vite']]));

    Artisan::call('innodite:publish-frontend', ['--dry-run' => true, '--no-interaction' => true]);

    expect(File::exists(base_path('vitest.config.js')))->toBeFalse('FALLA: el ensayo publicó el archivo.');

    expect(isset(json_decode(File::get(base_path('package.json')), true)['scripts']['test:js']))
        ->toBeFalse('FALLA: el ensayo tocó el package.json.');
});
