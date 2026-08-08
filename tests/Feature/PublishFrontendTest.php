<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Innodite\LaravelModuleMaker\LaravelModuleMakerServiceProvider;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El frontend que el paquete publica, contrastado con el que la vista generada importa.
 *
 * **Por qué hace falta.** La pantalla generada no trae su lógica dentro: importa composables y
 * componentes de `resources/js/`, que llegan ahí por un comando aparte. Son dos piezas que se
 * escriben en momentos distintos y nadie las obliga a coincidir — y cuando dejan de coincidir, el
 * síntoma no es un error del paquete: es una pantalla en blanco y un `Failed to resolve import` en
 * la consola del navegador de otro.
 *
 * Por eso aquí no se comprueba que el comando copie archivos, sino que copie **exactamente los que
 * la vista va a pedir**.
 */

/**
 * El `resources/js` donde se publica es el del esqueleto de pruebas, dentro de `vendor/`.
 *
 * Por eso la limpieza va en un `afterEach` y no al final de cada prueba: una que falle a mitad
 * dejaría los archivos publicados ahí dentro, sobreviviendo a la corrida, sin aparecer en el
 * repositorio y midiendo lo de la vez anterior en la siguiente. Ya pasó al escribir este archivo.
 */
$jsExistia = false;

beforeEach(function () use (&$jsExistia) {
    $jsExistia = File::isDirectory(resource_path('js'));
    File::ensureDirectoryExists(resource_path('js'));
});

afterEach(function () use (&$jsExistia) {
    if ($jsExistia) {
        File::deleteDirectory(resource_path('js/Composables'));
        File::deleteDirectory(resource_path('js/Components'));
        return;
    }

    File::deleteDirectory(resource_path('js'));
});

it('publica los composables y los componentes, los dos grupos', function () {
    $salida = Artisan::call('innodite:publish-frontend');

    expect($salida)->toBe(0, 'El comando falló: ' . Artisan::output());

    foreach ([
        'js/Composables/useModuleContext.js',
        'js/Composables/usePermissions.js',
        'js/Composables/useAvisos.js',
        'js/Components/InnoditeAviso.vue',
        'js/Components/InnoditeModal.vue',
    ] as $archivo) {
        expect(File::exists(resource_path($archivo)))->toBeTrue(
            "FALLA: el comando no publicó {$archivo}. · FIX: los grupos que publica están en "
            . 'PublishFrontendCommand::GRUPOS, y cada uno se copia entero. Un archivo nuevo en '
            . 'stubs/resources/js/ viaja solo; una carpeta nueva hay que declararla.'
        );
    }
});

it('no pisa un archivo del proyecto salvo que se lo pidan con --force', function () {
    Artisan::call('innodite:publish-frontend');

    $publicado = resource_path('js/Components/InnoditeModal.vue');
    File::put($publicado, '// lo cambió el proyecto');

    Artisan::call('innodite:publish-frontend');

    expect(File::get($publicado))->toBe(
        '// lo cambió el proyecto',
        'FALLA: el comando sobreescribió un archivo que el proyecto ya había tocado. · FIX: sin '
        . '--force se omite y se avisa; estos archivos se publican para editarlos.'
    );

    Artisan::call('innodite:publish-frontend', ['--force' => true]);

    expect(str_contains(File::get($publicado), 'InnoditeModal'))->toBeTrue(
        'FALLA: con --force el archivo no volvió a la versión del paquete. · FIX: --force existe '
        . 'para recuperar el original después de haberlo editado.'
    );
});

it('el tag de publicación declara los dos grupos, no solo los composables', function () {
    // `vendor:publish --tag=module-maker-frontend` es el otro camino hacia los mismos archivos.
    // Si declara un grupo y el comando dos, quien use el tag se queda con la mitad — y la mitad que
    // falta son los componentes que la vista importa.
    $rutas = ServiceProvider::pathsToPublish(
        LaravelModuleMakerServiceProvider::class,
        'module-maker-frontend'
    );

    $origenes = array_map(
        fn (string $ruta): string => basename($ruta),
        array_keys($rutas)
    );

    expect(in_array('Composables', $origenes, true))->toBeTrue(
        'FALLA: el tag ya no publica los composables. · FIX: la vista generada los importa; sin '
        . 'ellos no monta.'
    );

    expect(in_array('Components', $origenes, true))->toBeTrue(
        'FALLA: el tag no publica los componentes. · FIX: van en el mismo publishes() que los '
        . 'composables; la vista generada importa de ambos.'
    );
});

it('todo lo que la vista generada importa de resources/js existe en el paquete', function () {
    // Esta es la que ata los dos lados. Un import nuevo en un stub Vue —de un componente que aún
    // no existe, o mal escrito— pasa desapercibido: el stub no se ejecuta al generarse, y el
    // módulo se escribe igual de bien. El fallo aparece al abrir la pantalla.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $raizStubs = dirname(__DIR__, 2) . '/stubs/resources/js';

    foreach (['Index', 'Create', 'Edit', 'Show'] as $pieza) {
        $vista = $modulo->contents("resources/js/Pages/Invoice/Invoice{$pieza}.vue");

        preg_match_all("#from '@/(Composables|Components)/([^']+)'#", $vista, $encontrados, PREG_SET_ORDER);

        foreach ($encontrados as [$completo, $grupo, $archivo]) {
            // Los composables se importan sin extensión; los componentes, con `.vue`.
            $candidatos = str_ends_with($archivo, '.vue')
                ? ["{$raizStubs}/{$grupo}/{$archivo}"]
                : ["{$raizStubs}/{$grupo}/{$archivo}.js", "{$raizStubs}/{$grupo}/{$archivo}"];

            $existe = array_filter($candidatos, fn (string $ruta): bool => File::exists($ruta));

            expect($existe)->not->toBeEmpty(
                "FALLA: Invoice{$pieza}.vue importa `{$completo}` y el paquete no publica ese "
                . "archivo. · FIX: créalo en stubs/resources/js/{$grupo}/ — si la vista lo pide y "
                . 'nadie lo publica, la pantalla no monta.'
            );
        }
    }
});
