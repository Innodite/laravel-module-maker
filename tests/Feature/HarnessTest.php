<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use PHPUnit\Framework\AssertionFailedError;

/**
 * El arnés, probado por donde importa: que MUERDA.
 *
 * Una afirmación que nunca falla es peor que no tenerla, porque se lee como una garantía. Las
 * cuatro pruebas de abajo rompen a propósito lo que cada contraste vigila y comprueban dos cosas:
 * que falle, y que el mensaje **nombre el archivo** — sin el nombre hay que buscar a mano entre
 * los 23 archivos de un módulo.
 *
 * Es el mismo método del cruce de stubs: verificar que la prueba muerde,
 * con una clave inventada a propósito.
 */

it('un módulo recién generado pasa los tres contrastes', function () {
    $this->generateModule('Invoice', ModuleMode::SingleApp)->assertCoherent();
});

it('caza un placeholder que nadie resolvió, aunque el archivo ya esté escrito', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    File::append($modulo->path('Services/Invoice/InvoiceService.php'), "\n// {{{ claveHuerfana }}}\n");

    try {
        $modulo->assertEveryFileWouldBeAccepted();
        $this->fail('Un placeholder sin resolver en un archivo del árbol debe hacer fallar el contraste.');
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain('InvoiceService.php')
            ->and($e->getMessage())->toContain('claveHuerfana');
    }
});

it('caza una clase que no se llama como su archivo', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $ruta = $modulo->path('Repositories/Invoice/InvoiceRepository.php');
    File::put($ruta, str_replace('class InvoiceRepository', 'class InvoiceRepositorio', File::get($ruta)));

    try {
        $modulo->assertClassNamesMatchFiles();
        $this->fail('Una clase con otro nombre que su archivo no se autocarga: debe fallar.');
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain('InvoiceRepository.php')
            ->and($e->getMessage())->toContain('InvoiceRepositorio');
    }
});

it('caza un import que apunta a una clase que nadie escribió', function () {
    // Exactamente la segunda mitad de B13: el factory apuntaba a `Permission` cuando la clase
    // generada era `CentralPermission`. Sintaxis impecable, parser contento, clase inexistente.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $ruta = $modulo->path('Http/Controllers/Invoice/InvoiceController.php');
    File::put($ruta, str_replace(
        '<?php',
        "<?php\n\nuse Modules\\Invoice\\Models\\Invoice\\InvoiceQueNadieEscribio;",
        File::get($ruta),
    ));

    try {
        $modulo->assertInternalImportsExist();
        $this->fail('Un import a una clase inexistente pasa el parser y revienta al ejecutar.');
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain('InvoiceQueNadieEscribio')
            ->and($e->getMessage())->toContain('InvoiceController.php');
    }
});

it('el fallo de generación se distingue del fallo de la afirmación', function () {
    // Un contexto que el modo no acepta hace que el comando termine con error. El arnés debe
    // decir eso —con la salida del comando delante— en vez de dejar que la prueba falle más tarde
    // por un árbol vacío, que es lo que cada prueba tenía que recordar en un mensaje suyo.
    try {
        $this->generateModule('Invoice', ModuleMode::SingleApp, 'tenant');
        $this->fail('Generar en single-app pidiendo un contexto de tenant no puede darse por bueno.');
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain('Salida del comando');
    }
});
