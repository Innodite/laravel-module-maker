<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * Que la pantalla generada **se resuelva**, no solo que se genere.
 *
 * ⭐ **La prueba que faltaba, y por eso el fallo llegó a un proyecto real.** Todo lo que había
 * comprobaba que el archivo `.vue` se escribe; nadie comprobaba que el controlador generado sepa
 * encontrarlo — y no lo sabía en ninguno de los dos modos.
 *
 * **Ahora se mide de la forma más directa que hay**: se lee la cadena que el controlador le pasa a
 * `Inertia::render()` y se comprueba que el archivo al que apunta **está en el disco**. Antes había
 * que instanciar un trait y llamar por reflexión a un método privado para averiguarlo, porque la
 * ruta se componía en tiempo de ejecución leyendo la configuración del proyecto. Ya no: la escribe
 * el generador, que es quien elige la carpeta, así que la prueba compara dos hechos y no simula uno.
 */

/** La ruta que el controlador generado le pasa a Inertia, sin el prefijo del módulo. */
function vistaQuePide(string $controlador): string
{
    expect($controlador)->toContain('Inertia::render(');

    preg_match("/Inertia::render\('([^']+)'\)/", $controlador, $m);

    expect($m)->not->toBeEmpty(
        "FALLA: el controlador no llama a Inertia::render con una ruta literal. · FIX: el stub "
        . "escribe Inertia::render('{modulo}::{ruta}') y el generador rellena la ruta.\n{$controlador}"
    );

    [$modulo, $ruta] = explode('::', $m[1], 2);

    return $ruta;
}

it('en una aplicación única la pantalla generada existe donde el controlador la pide', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $ruta = vistaQuePide($modulo->contents('Http/Controllers/Invoice/InvoiceController.php'));

    expect($ruta)->toBe('Invoice/InvoiceIndex');
    expect(File::exists($modulo->path() . "/resources/js/Pages/{$ruta}.vue"))->toBeTrue(
        "FALLA: el controlador pide '{$ruta}' y ahí no hay ninguna vista. · FIX: la ruta la compone "
        . 'ControllerGenerator con la carpeta de contexto, la subfuncionalidad y el componente — el '
        . 'mismo trío con el que VueGenerator elige dónde escribir el archivo.'
    );
});

it('en multitenant la carpeta del contexto y la de la subfuncionalidad van las dos', function () {
    // Faltando cualquiera de las dos, la ruta apunta a un archivo que no está. Es exactamente el
    // fallo que tenía parado un proyecto real: la pantalla existía y el controlador miraba al lado.
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    $ruta = vistaQuePide($modulo->contents('Http/Controllers/Central/Invoice/CentralInvoiceController.php'));

    expect($ruta)->toBe('Central/Invoice/CentralInvoiceIndex');
    expect(File::exists($modulo->path() . "/resources/js/Pages/{$ruta}.vue"))->toBeTrue(
        "FALLA: el controlador pide '{$ruta}' y ahí no hay ninguna vista."
    );
});

it('la ruta se resuelve al generar, sin leer nada en tiempo de ejecución', function () {
    // ⛔ Lo que esta prueba impide que vuelva: que el controlador delegue la resolución en una pieza
    // del paquete que, en cada petición, lea contexts.json y pregunte por el modo del proyecto. Eso
    // mezclaba los dos modos en un solo camino y ponía un `if` de configuración delante de cada
    // pantalla — un fallo de configuración se convertía en una pantalla que no abre.
    $controlador = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Http/Controllers/Invoice/InvoiceController.php');

    expect(str_contains($controlador, 'RendersInertiaModule'))->toBeFalse(
        'FALLA: el controlador generado vuelve a depender del trait de resolución. · FIX: la ruta la '
        . 'escribe el generador; el controlador llama a Inertia::render() con una cadena literal.'
    );

    expect(str_contains($controlador, 'use Inertia\\Inertia;'))->toBeTrue(
        'FALLA: falta el import de Inertia. · FIX: el stub lo declara junto a InertiaResponse.'
    );
});

it('las cuatro vistas se generan bajo la misma carpeta que pide el controlador', function () {
    // La coherencia entre las dos mitades: quien escribe el .vue y quien lo pide tienen que estar
    // usando el mismo trío —carpeta de contexto, subfuncionalidad, componente—.
    foreach ([[ModuleMode::SingleApp, null], [ModuleMode::MultitenantPerTenant, 'central']] as [$modo, $ctx]) {
        // El generador se niega a regenerar encima —y hace bien—, así que cada vuelta parte de cero.
        File::deleteDirectory($this->tempPath('Modules/Invoice'));

        $modulo = $this->generateModule('Invoice', $modo, $ctx);

        $controlador = collect(File::allFiles($modulo->path() . '/Http/Controllers'))
            ->first(fn ($f) => str_ends_with($f->getFilename(), 'Controller.php'));

        $ruta    = vistaQuePide((string) File::get($controlador->getPathname()));
        $carpeta = dirname($ruta);

        $vistas = File::glob($modulo->path() . "/resources/js/Pages/{$carpeta}/*.vue");

        expect(count($vistas))->toBe(4,
            "FALLA: bajo Pages/{$carpeta}/ hay " . count($vistas) . ' vistas, y son cuatro. · FIX: '
            . 'VueGenerator y ControllerGenerator tienen que componer la misma carpeta.'
        );
    }
});
