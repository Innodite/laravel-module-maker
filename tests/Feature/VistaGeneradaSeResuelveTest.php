<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Traits\RendersInertiaModule;

/**
 * Que la pantalla generada **se resuelva**, no solo que se genere.
 *
 * ⭐ **La prueba que faltaba, y por eso el fallo llegó a un proyecto real.** Todo lo que había
 * comprobaba que el archivo `.vue` se escribe; nadie comprobaba que el controlador generado sepa
 * encontrarlo. Y no lo sabía **en ninguno de los dos modos**:
 *
 *   · El generador escribe en `Pages/{Contexto}/{SubFuncionalidad}/`, y el controlador pedía solo el
 *     nombre del archivo, así que el trait buscaba en `Pages/{Contexto}/`.
 *   · En una aplicación única, además, el trait exigía que el componente empezara por un prefijo de
 *     contexto —`Central`, `Shared`, `Tenant*`— que en single-app no existe: **toda** pantalla
 *     moría al abrirse.
 */
function resolutor(): object
{
    return new class {
        use RendersInertiaModule;

        public function resolver(string $module, string $component): string
        {
            // `resolveView` es privado a propósito: se llega por donde se llega en producción, que
            // es `renderModule`, y aquí se abre solo para poder comprobarlo sin levantar Inertia.
            $metodo = new ReflectionMethod($this, 'resolveView');
            $metodo->setAccessible(true);

            return $metodo->invoke($this, $module, $component);
        }
    };
}

it('en una aplicación única la pantalla generada se encuentra', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    config()->set('make-module.module_path', dirname($modulo->path()));

    expect(resolutor()->resolver('Invoice', 'Invoice/InvoiceIndex'))
        ->toBe('Invoice::Invoice/InvoiceIndex');
});

it('en multitenant la carpeta del contexto y la de la subfuncionalidad van las dos', function () {
    // El contexto lo pone el prefijo del nombre; la subfuncionalidad, la carpeta que trae el
    // componente. Faltando cualquiera de las dos, la ruta apunta a un archivo que no está.
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    config()->set('make-module.module_path', dirname($modulo->path()));

    expect(resolutor()->resolver('Invoice', 'Invoice/CentralInvoiceIndex'))
        ->toBe('Invoice::Central/Invoice/CentralInvoiceIndex');
});

it('el controlador generado pide la vista con su carpeta, no solo con su nombre', function () {
    // Es la mitad que le toca al generador: el trait sabe resolver, pero solo si le dan la carpeta.
    $controlador = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Http/Controllers/Invoice/InvoiceController.php');

    expect($controlador)->toContain("renderModule('Invoice', 'Invoice/InvoiceIndex')");
});
