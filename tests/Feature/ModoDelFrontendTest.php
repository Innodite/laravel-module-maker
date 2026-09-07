<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\Frontend;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\StubsDeVendor;

/**
 * El modo del frontend — y sobre todo, que un modo que pide algo que nadie aporta lo DIGA.
 *
 * ⭐ **Este archivo existe por un defecto concreto:** `--frontend=innodite` llegó a ser un
 * interruptor que solo escribía una clave en el `.env`. Ningún generador la leía, así que elegirlo
 * producía exactamente lo mismo que `default` — el usuario pedía las vistas de la biblioteca,
 * obtenía las genéricas, y el comando terminaba en verde.
 *
 * Ese es el peor resultado posible: no es un error que se ve, es una diferencia que se descubre al
 * abrir la pantalla, cuando ya hay varios módulos generados con la forma equivocada.
 */
beforeEach(function () {
    StubsDeVendor::olvidar();
    $this->app->setBasePath($this->tempPath());
});

afterEach(fn () => StubsDeVendor::olvidar());

/** Un paquete instalado que aporta las vistas, como lo haría uno de verdad. */
function paqueteConVistas(string $paquete = 'acme/interfaz'): void
{
    $carpeta = test()->tempPath("vendor/{$paquete}/stubs/module-maker/contextual");
    File::ensureDirectoryExists($carpeta);
    File::put("{$carpeta}/vue-index.stub", '<template>LA VISTA DE {{{ subFeatureLabel }}}</template>');
    StubsDeVendor::olvidar();
}

// ─── Lo que decide el modo ────────────────────────────────────────────────────

it('sin declarar nada, el modo es default', function () {
    expect(Frontend::modo())->toBe('default')
        ->and(Frontend::esInnodite())->toBeFalse();
});

it('un modo que no se reconoce cae en default en vez de romper', function () {
    // Generar con un modo inventado no puede detener el trabajo: `default` funciona en cualquier
    // proyecto, así que es la respuesta segura.
    config()->set('make-module.frontend.modo', 'lo-que-sea');

    expect(Frontend::modo())->toBe('default');
});

// ─── El aviso, que es lo que importa ──────────────────────────────────────────

it('avisa cuando se pide la biblioteca y ningún paquete la aporta', function () {
    config()->set('make-module.frontend.modo', 'innodite');

    expect(Frontend::pidioBibliotecaQueNadieAporta())->toBeTrue();

    $this->withMode(ModuleMode::SingleApp);
    Artisan::call('innodite:make-module', ['name' => 'Invoice', '--no-interaction' => true]);
    $salida = Artisan::output();

    expect(str_contains($salida, 'genéricas'))->toBeTrue(
        "FALLA: el módulo se generó con las vistas genéricas y el comando no lo dijo. · FIX: el "
        . "aviso sale de avisarSiLasVistasNoSonLasQueSePidieron().\nDijo:\n" . $salida
    );

    expect($salida)->toContain('FALLA:')
        ->and($salida)->toContain('FIX:')
        ->and($salida)->toContain('MODULE_MAKER_FRONTEND=default');
});

it('no avisa cuando alguien sí aporta las vistas', function () {
    // El aviso tiene que ser raro: uno que sale siempre se deja de leer.
    config()->set('make-module.frontend.modo', 'innodite');
    paqueteConVistas();

    expect(Frontend::pidioBibliotecaQueNadieAporta())->toBeFalse();

    $this->withMode(ModuleMode::SingleApp);
    Artisan::call('innodite:make-module', ['name' => 'Invoice', '--no-interaction' => true]);

    expect(str_contains(Artisan::output(), 'genéricas'))->toBeFalse(
        'FALLA: avisa de vistas genéricas cuando sí se usaron las aportadas.'
    );
});

it('en modo default no avisa nunca, aunque nadie aporte nada', function () {
    expect(Frontend::pidioBibliotecaQueNadieAporta())->toBeFalse(
        'FALLA: default no pide ninguna biblioteca, así que no puede faltarle.'
    );
});

// ─── El layout ────────────────────────────────────────────────────────────────

it('sin layout declarado la pantalla se dibuja suelta', function () {
    $vista = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Invoice/resources/js/Pages/InvoiceIndex.vue');

    expect($vista)->not->toContain('defineOptions')
        ->and($vista)->not->toContain('{{{');
});

it('el layout declarado se importa y se declara, sin tocar el template', function () {
    config()->set('make-module.frontend.layout', '@/Layouts/AppLayout.vue');

    $vista = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Invoice/resources/js/Pages/InvoiceIndex.vue');

    expect($vista)->toContain("import AppLayout from '@/Layouts/AppLayout.vue'")
        ->and($vista)->toContain('defineOptions({ layout: AppLayout })');

    // Por `defineOptions` y no envolviendo el <template>: dos líneas, y la plantilla intacta.
    expect(substr_count($vista, '<template>'))->toBe(1,
        'FALLA: el layout envolvió el template. · FIX: va por defineOptions, que es como Inertia '
        . 'declara un layout persistente.'
    );
});

it('el nombre del componente sale de la ruta del layout, sin pedirlo aparte', function () {
    // Pedir las dos cosas permite que dejen de coincidir, y entonces la vista importa una y usa otra.
    config()->set('make-module.frontend.layout', '@/Layouts/Panel/PanelLayout.vue');

    expect(Frontend::nombreDelLayout())->toBe('PanelLayout');
});

it('solo el listado lleva layout: los otros tres son modales dentro de él', function () {
    // Darle layout propio a un modal dibujaría la aplicación entera dentro de una ventana.
    config()->set('make-module.frontend.layout', '@/Layouts/AppLayout.vue');

    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    foreach (['Create', 'Edit', 'Show'] as $modal) {
        expect($modulo->contents("Invoice/resources/js/Pages/Invoice{$modal}.vue"))
            ->not->toContain('defineOptions');
    }
});
