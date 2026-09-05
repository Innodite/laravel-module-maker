<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\StubsDeVendor;

/**
 * Los stubs que aporta otro paquete instalado, y el hueco que el generador les deja.
 *
 * **Qué se está midiendo.** El paquete es público y lo que genera tiene que servir en un proyecto
 * pelado; pero un proyecto que lleve una biblioteca de interfaz querría generar contra ella, y su
 * enganche en el menú no puede escribirse aquí sin publicar la forma de esa biblioteca.
 *
 * La salida es que el paquete **no sepa de nadie** y declare dónde mirar: cualquier paquete
 * instalado que traiga `stubs/module-maker/contextual/` participa. Lo que se comprueba abajo es esa
 * cascada —quién gana a quién— y el punto de enganche del `boot()`, que es el que existe solo si
 * alguien lo aporta y cuya ausencia es el caso normal, no un fallo.
 */
beforeEach(function () {
    // El descubrimiento se cachea por proceso, y en una suite el proceso es uno para todos los
    // casos: sin esto, el primero que monte un vendor falso se lo deja puesto a los demás.
    StubsDeVendor::olvidar();
    $this->app->setBasePath($this->tempPath());
});

afterEach(fn () => StubsDeVendor::olvidar());

/** Monta un paquete de mentira que aporta un stub, tal y como lo haría uno instalado de verdad. */
function paqueteQueAporta(string $paquete, string $stub, string $contenido): void
{
    $carpeta = test()->tempPath("vendor/{$paquete}/stubs/module-maker/contextual");
    File::ensureDirectoryExists($carpeta);
    File::put("{$carpeta}/{$stub}", $contenido);
    StubsDeVendor::olvidar();
}

it('un paquete instalado que aporta un stub le gana al del propio paquete', function () {
    paqueteQueAporta('acme/interfaz', 'vue-index.stub', '<template>LA VISTA DE ACME · {{{ subFeatureLabel }}}</template>');

    $vista = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('resources/js/Pages/Invoice/InvoiceIndex.vue');

    expect($vista)->toContain('LA VISTA DE ACME');

    // Y los placeholders se resuelven igual que en los del paquete: un stub aportado no es un
    // archivo que se copia, es un stub de primera clase.
    expect($vista)->toContain('Invoice')
        ->and($vista)->not->toContain('{{{');
});

it('el override del proyecto le gana al paquete que aporta', function () {
    // El orden es el contenido de la decisión: quien aporta sabe más que el generador sobre su
    // biblioteca, y menos que el proyecto sobre su propio código.
    paqueteQueAporta('acme/interfaz', 'vue-index.stub', '<template>ACME</template>');

    $delProyecto = $this->tempPath('module-maker-config/stubs/contextual');
    File::ensureDirectoryExists($delProyecto);
    File::put("{$delProyecto}/vue-index.stub", '<template>LO QUE DICE EL PROYECTO</template>');

    $vista = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('resources/js/Pages/Invoice/InvoiceIndex.vue');

    expect($vista)->toContain('LO QUE DICE EL PROYECTO')
        ->and($vista)->not->toContain('ACME');
});

it('sin nadie que aporte nada, lo generado es exactamente lo del paquete', function () {
    // El caso normal, y el que no puede cambiar: el descubrimiento no tiene que alterar en nada un
    // proyecto que no lleva ninguna biblioteca.
    expect(StubsDeVendor::carpetas())->toBe([]);

    $vista = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('resources/js/Pages/Invoice/InvoiceIndex.vue');

    expect($vista)->toContain('useModuleContext')
        ->and($vista)->toContain('window.route(contextRoute(');
});

it('con dos paquetes aportando el mismo stub gana el primero por orden alfabético', function () {
    // Alfabético es arbitrario, pero es igual en todas las máquinas. El orden de un directorio no
    // lo es, y un empate que se resuelve distinto según dónde corra no hay quien lo diagnostique.
    paqueteQueAporta('zeta/interfaz', 'vue-index.stub', '<template>ZETA</template>');
    paqueteQueAporta('acme/interfaz', 'vue-index.stub', '<template>ACME</template>');

    expect(array_keys(StubsDeVendor::carpetas()))->toBe(['acme/interfaz', 'zeta/interfaz'])
        ->and(StubsDeVendor::paquetesQueAportan('vue-index.stub'))->toBe(['acme/interfaz', 'zeta/interfaz']);

    $vista = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('resources/js/Pages/Invoice/InvoiceIndex.vue');

    expect($vista)->toContain('ACME')->and($vista)->not->toContain('ZETA');
});

it('el boot del proveedor sale vacío cuando nadie aporta el enganche', function () {
    // Es el caso normal y tiene que seguir siéndolo: un módulo generado en un proyecto pelado no
    // llama a nada que no exista. Escribir ahí el enganche a lo que se suponga que hay revienta en
    // el arranque, y eso no deja un módulo invisible: deja la aplicación entera sin responder.
    $proveedor = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Providers/InvoiceServiceProvider.php');

    expect($proveedor)->toContain("public function boot(): void\n    {\n        //\n    }");
});

it('el enganche aportado entra en el boot del proveedor generado', function () {
    paqueteQueAporta(
        'acme/interfaz',
        'provider-boot.stub',
        "\\Acme\\Menu::registrar('{{{ functionality }}}', '{{{ moduleName }}}');"
    );

    $proveedor = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Providers/InvoiceServiceProvider.php');

    expect($proveedor)->toContain("\\Acme\\Menu::registrar('invoices', 'Invoice');")
        ->and($proveedor)->not->toContain('{{{');

    // Y entra sangrado donde va, no pegado al margen: un proveedor generado que hay que reindentar
    // a mano es un archivo que nadie vuelve a mirar.
    expect($proveedor)->toContain("    {\n        \\Acme\\Menu::registrar(");
});

it('un enganche de varias líneas conserva su forma dentro del boot', function () {
    paqueteQueAporta(
        'acme/interfaz',
        'provider-boot.stub',
        "if (class_exists(\\Acme\\Menu::class)) {\n    \\Acme\\Menu::registrar('{{{ functionality }}}');\n}"
    );

    $proveedor = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Providers/InvoiceServiceProvider.php');

    expect($proveedor)->toContain(
        "        if (class_exists(\\Acme\\Menu::class)) {\n"
        . "            \\Acme\\Menu::registrar('invoices');\n"
        . "        }"
    );
});
