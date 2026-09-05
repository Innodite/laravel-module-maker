<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\Ziggy;

/**
 * Las rutas de la vista generada: se piden por el NOMBRE, y quien traduce el nombre tiene que estar.
 *
 * **Las dos mitades de esto son inseparables y por eso viven en el mismo archivo.**
 *
 * La primera es una decisión de diseño: las vistas piden `route(contextRoute('invoices.list'))` y
 * nunca escriben la dirección. Es lo correcto —cambiar un prefijo en el archivo de rutas no obliga
 * a tocar ni una vista— y lo que se comprueba abajo es que siga siendo así, porque la tentación de
 * «simplificar» a una URL literal aparece cada vez que algo no resuelve.
 *
 * La segunda es la consecuencia: `route()` la pone Ziggy, que es un paquete aparte. Cuando falta —o
 * está sin la directiva `@routes` en el layout— la pantalla **muere al montarse**, antes de pintar
 * nada, con un `route is not defined` que no menciona ni al módulo, ni al paquete, ni a Ziggy. Y en
 * el servidor no queda absolutamente nada, porque la petición respondió 200. Era el defecto que
 * dejaba sin abrir toda pantalla generada sobre un proyecto que no lo llevaba.
 *
 * Elegir el nombre y no comprobar quién lo resuelve es lo que convertía un requisito en una
 * suposición. Aquí se comprueban las dos cosas juntas.
 */
beforeEach(function () {
    // El proyecto de prueba, aislado: el skeleton de Testbench vive en `vendor/`, sobrevive a la
    // prueba y no aparece en el repositorio, así que escribirle un composer.json o un layout
    // contaminaría todas las corridas siguientes sin dejar rastro.
    $this->app->setBasePath($this->tempPath());
});

/** Lanza el diagnóstico saltándose el corte de la etapa 1, que aquí no es lo que se mide. */
function diagnosticoDeZiggy(): string
{
    Artisan::call('innodite:doctor', ['--continuar' => true, '--no-interaction' => true]);

    return Artisan::output();
}

/** Declara Ziggy en el composer.json del proyecto de prueba. */
function conZiggyInstalado(): void
{
    File::put(test()->tempPath('composer.json'), json_encode([
        'require' => ['laravel/framework' => '^11.0', 'tightenco/ziggy' => '^2.0'],
    ], JSON_PRETTY_PRINT));
}

/** Escribe un layout Blade que publica —o no— el mapa de rutas. */
function conLayout(string $contenido): void
{
    File::ensureDirectoryExists(test()->tempPath('resources/views'));
    File::put(test()->tempPath('resources/views/app.blade.php'), $contenido);
}

// ─── La decisión: por nombre, nunca por dirección ─────────────────────────────

it('las cuatro vistas piden sus rutas por el nombre, nunca escribiendo la dirección', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    foreach (['Index', 'Create', 'Edit', 'Show'] as $pieza) {
        $vista = $modulo->contents("resources/js/Pages/Invoice/Invoice{$pieza}.vue");

        expect(str_contains($vista, 'contextRoute('))->toBeTrue(
            "FALLA: Invoice{$pieza}.vue ya no pide sus rutas por el nombre. · FIX: la vista llama a "
            . 'route(contextRoute(...)); escribir la dirección a mano ata cada pantalla al prefijo '
            . 'del archivo de rutas.'
        );

        // Una dirección literal de este módulo dentro de una llamada a axios es exactamente lo que
        // no puede volver: funciona el día que se escribe y deja de funcionar al cambiar el prefijo,
        // sin que nada avise.
        expect(preg_match('#axios\.\w+\(\s*[`\'"]/#', $vista))->toBe(0,
            "FALLA: Invoice{$pieza}.vue le pasa a axios una dirección escrita a mano. · FIX: pídela "
            . 'por su nombre con route(contextRoute(...)).'
        );
    }
});

it('la llamada va por window.route, que es lo que define la directiva del layout', function () {
    // `@routes` define `route` y `Ziggy` en el ámbito global del navegador, y ese es el único punto
    // de entrada que vale igual en las dos versiones de Ziggy: importarlo de `ziggy-js` solo existe
    // en la v2. Es la misma razón por la que las vistas usan `window.axios`.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    foreach (['Index', 'Create', 'Edit', 'Show'] as $pieza) {
        $vista = $modulo->contents("resources/js/Pages/Invoice/Invoice{$pieza}.vue");

        preg_match_all('/(?<!window\.)\broute\(contextRoute\(/', $vista, $sueltas);

        expect($sueltas[0])->toBeEmpty(
            "FALLA: Invoice{$pieza}.vue llama a route() sin `window.`. · FIX: `window.route(...)`. "
            . 'Sin el prefijo depende de que el proyecto haya importado route en el módulo, y con '
            . 'la directiva del layout la función vive en el ámbito global.'
        );
    }
});

// ─── La consecuencia: quien resuelve el nombre tiene que estar ────────────────

it('el doctor falla si Ziggy no está instalado, y dice cómo instalarlo', function () {
    conLayout('<html><head>@routes</head></html>');

    $salida = diagnosticoDeZiggy();

    expect($salida)->toContain('Ziggy no está instalado')
        ->and($salida)->toContain('composer require tightenco/ziggy')
        ->and($salida)->toContain('@routes');
});

it('el doctor falla si Ziggy está pero ningún layout publica el mapa', function () {
    // La mitad que más se olvida: `composer require` deja la sensación de que ya está, y el síntoma
    // en pantalla es idéntico al de no tenerlo instalado.
    conZiggyInstalado();
    conLayout('<html><head><title>Sin la directiva</title></head></html>');

    $salida = diagnosticoDeZiggy();

    expect($salida)->toContain('ningún layout Blade publica el mapa')
        ->and($salida)->toContain('@routes');
});

it('el doctor pasa cuando están las dos mitades, y dice en qué archivo encontró la directiva', function () {
    conZiggyInstalado();
    conLayout('<html><head>@routes</head></html>');

    expect(diagnosticoDeZiggy())->toContain('resources/views/app.blade.php');
});

it('make-module avisa de que la pantalla no abrirá, y distingue cuál de las dos mitades falta', function () {
    // El gemelo del aviso del autoload, en el otro lado: aquel mira si el módulo carga en el
    // servidor; este, si su vista sobrevive en el navegador.
    $this->withMode(ModuleMode::SingleApp);
    conLayout('<html><head>@routes</head></html>');

    Artisan::call('innodite:make-module', [
        'name'             => 'Invoice',
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect(str_contains($salida, 'su pantalla todavía NO abre'))->toBeTrue(
        "FALLA: make-module generó el módulo sin avisar de que su pantalla no abrirá. · FIX: el "
        . "aviso sale de avisarSiLaPantallaNoVaAAbrir(), junto al del autoload.\nDijo:\n" . $salida
    );

    expect($salida)->toContain('route is not defined')
        ->and($salida)->toContain('composer require tightenco/ziggy');
});

// ─── La pieza que lo responde ─────────────────────────────────────────────────

it('reconoce Ziggy por cualquiera de sus dos nombres en Packagist', function () {
    // Cambió de organización en la v2 y los dos siguen instalados por ahí: comprobar solo uno da un
    // falso «no está» en la mitad de los proyectos.
    expect(Ziggy::instalado())->toBeFalse();

    File::put($this->tempPath('composer.json'), json_encode(['require' => ['tighten/ziggy' => '^2.0']]));
    expect(Ziggy::instalado())->toBeTrue();

    File::put($this->tempPath('composer.json'), json_encode(['require' => ['tightenco/ziggy' => '^1.0']]));
    expect(Ziggy::instalado())->toBeTrue();
});

it('falta() cuenta las dos mitades, no solo la instalación', function () {
    conZiggyInstalado();
    conLayout('<html><head></head></html>');

    expect(Ziggy::instalado())->toBeTrue()
        ->and(Ziggy::layoutConDirectiva())->toBeNull()
        ->and(Ziggy::falta())->toBeTrue(
            'FALLA: con Ziggy instalado y sin @routes, falta() dice que no falta nada. · FIX: las '
            . 'dos mitades cuentan — el síntoma en pantalla es el mismo.'
        );
});
