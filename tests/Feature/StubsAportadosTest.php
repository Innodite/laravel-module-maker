<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Components\SubFeatureSeederGenerator;
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
        ->contents('Invoice/resources/js/Pages/InvoiceIndex.vue');

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
        ->contents('Invoice/resources/js/Pages/InvoiceIndex.vue');

    expect($vista)->toContain('LO QUE DICE EL PROYECTO')
        ->and($vista)->not->toContain('ACME');
});

it('sin nadie que aporte nada, lo generado es exactamente lo del paquete', function () {
    // El caso normal, y el que no puede cambiar: el descubrimiento no tiene que alterar en nada un
    // proyecto que no lleva ninguna biblioteca.
    expect(StubsDeVendor::carpetas())->toBe([]);

    $vista = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Invoice/resources/js/Pages/InvoiceIndex.vue');

    expect($vista)->toContain("window.route('")
        ->and($vista)->toContain('usePage');
});

it('con dos paquetes aportando el mismo stub gana el primero por orden alfabético', function () {
    // Alfabético es arbitrario, pero es igual en todas las máquinas. El orden de un directorio no
    // lo es, y un empate que se resuelve distinto según dónde corra no hay quien lo diagnostique.
    paqueteQueAporta('zeta/interfaz', 'vue-index.stub', '<template>ZETA</template>');
    paqueteQueAporta('acme/interfaz', 'vue-index.stub', '<template>ACME</template>');

    expect(array_keys(StubsDeVendor::carpetas()))->toBe(['acme/interfaz', 'zeta/interfaz'])
        ->and(StubsDeVendor::paquetesQueAportan('vue-index.stub'))->toBe(['acme/interfaz', 'zeta/interfaz']);

    $vista = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Invoice/resources/js/Pages/InvoiceIndex.vue');

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

// ─── La séptima pieza del seeder: los textos, que existen solo si alguien los aporta ──────────

/** El stub de textos tal y como lo aportaría una biblioteca de interfaz instalada. */
function stubDeTextos(): string
{
    return "<?php\n\nnamespace {{{ namespace }}};\n\n"
        . "use Acme\\Idiomas\\SiembraTextos;\n\n"
        . "/** Los textos de {{{ subFeature }}} ({{{ moduleName }}}), en cada idioma. */\n"
        . "trait {{{ traitName }}}\n{\n    use SiembraTextos;\n\n"
        . "    protected function texts(): array\n    {\n"
        . "        return ['es' => ['{{{ textsGroup }}}' => []], 'en' => ['{{{ textsGroup }}}' => []]];\n"
        . "    }\n}\n";
}

it('sin nadie que aporte la pieza de textos, los seeders salen con sus seis piezas y sin el paso', function () {
    // Es el caso normal, y tiene que seguir siéndolo: un proyecto pelado no tiene dónde guardar un
    // texto, y un `use` a un trait que no existe no deja un módulo sin textos — deja un seeder que
    // no carga.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    expect($modulo->has('Invoice/Database/Seeders/InvoiceInvoiceTexts.php'))->toBeFalse()
        ->and($modulo->has('resources/lang/.gitkeep'))->toBeFalse();

    foreach (['InvoiceInvoiceStageSeeder', 'InvoiceInvoiceProductionSeeder'] as $seeder) {
        $contenido = $modulo->contents("Invoice/Database/Seeders/{$seeder}.php");

        expect($contenido)->not->toContain('seedTexts')
            ->and($contenido)->not->toContain('Texts;')
            ->and($contenido)->not->toContain('{{{');

        // Y los dos huecos no dejan rastro: ni una línea en blanco de más entre los `use`, ni
        // después del paso de permisos.
        expect($contenido)->toContain("    use InvoiceInvoiceData;\n    use ReportsSeederErrors;")
            ->and($contenido)->toContain("        ));\n\n        \$this->reportErrors();");
    }
});

it('la pieza de textos aportada se escribe junto a las otras seis y los dos seeders la incorporan', function () {
    paqueteQueAporta('acme/interfaz', 'texts-trait.stub', stubDeTextos());

    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $pieza = $modulo->contents('Invoice/Database/Seeders/InvoiceInvoiceTexts.php');

    // Un stub aportado es un stub de primera clase: sus placeholders se resuelven como los del paquete.
    expect($pieza)->toContain('namespace Modules\\Invoice\\Invoice\\Database\\Seeders;')
        ->and($pieza)->toContain('trait InvoiceInvoiceTexts')
        ->and($pieza)->toContain("'invoice::invoice'")
        ->and($pieza)->toContain('Los textos de Invoice (Invoice)')
        ->and($pieza)->not->toContain('{{{');

    foreach (['InvoiceInvoiceStageSeeder', 'InvoiceInvoiceProductionSeeder'] as $seeder) {
        $contenido = $modulo->contents("Invoice/Database/Seeders/{$seeder}.php");

        // Entra entre los traits de la subfuncionalidad y los del paquete, sangrado como ellos…
        expect($contenido)->toContain("    use InvoiceInvoiceData;\n    use InvoiceInvoiceTexts;\n    use ReportsSeederErrors;");

        // …y se llama al final de `run()`, después de los permisos y antes del reporte: si la tabla
        // de textos la crea otro módulo, es el último paso el que más probabilidades tiene de
        // encontrarla.
        expect($contenido)->toContain("        ));\n\n        // Los textos de la subfuncionalidad")
            ->and($contenido)->toContain("        \$this->safe('seedTexts', fn () => \$this->seedTexts());\n\n        \$this->reportErrors();");
    }

    // La carpeta de traducciones del módulo nace con la pieza: el proveedor solo registra el
    // espacio de nombres de un módulo si la carpeta existe al arrancar, y sin él el texto sembrado
    // no llega a ninguna pantalla.
    expect($modulo->has('resources/lang/.gitkeep'))->toBeTrue();
});

it('en multiinquilino la pieza de textos lleva el prefijo del contexto y el grupo sigue siendo del módulo', function () {
    paqueteQueAporta('acme/interfaz', 'texts-trait.stub', stubDeTextos());

    $modulo = $this->generateModule('Invoice', ModuleMode::Multitenant, 'central');

    $pieza = $modulo->contents('Invoice/Database/Seeders/Central/CentralInvoiceInvoiceTexts.php');

    expect($pieza)->toContain('namespace Modules\\Invoice\\Invoice\\Database\\Seeders\\Central;')
        ->and($pieza)->toContain('trait CentralInvoiceInvoiceTexts')
        // El grupo no lleva contexto: los textos hablan del negocio, no de quién administra, y el
        // proveedor registra UNA carpeta de traducciones por módulo.
        ->and($pieza)->toContain("'invoice::invoice'");

    expect($modulo->contents('Invoice/Database/Seeders/Central/CentralInvoiceInvoiceStageSeeder.php'))
        ->toContain("    use CentralInvoiceInvoiceTexts;\n")
        ->toContain("\$this->safe('seedTexts', fn () => \$this->seedTexts());");
});

it('el grupo de traducción se compone como el proveedor registra la carpeta: en snake_case', function () {
    // `PersonCatalog` se registra como `person_catalog`; un grupo `personcatalog::…` apuntaría a un
    // espacio de nombres que nadie registró, y el texto sembrado se serviría como su propia clave.
    paqueteQueAporta('acme/interfaz', 'texts-trait.stub', stubDeTextos());

    $modulo = $this->generateModule('PersonCatalog', ModuleMode::SingleApp);

    expect($modulo->contents('PersonCatalog/Database/Seeders/PersonCatalogPersonCatalogTexts.php'))
        ->toContain("'person_catalog::person_catalog'");
});

it('la pieza de textos que ya existe no se pisa: dentro viven los textos escritos a mano', function () {
    paqueteQueAporta('acme/interfaz', 'texts-trait.stub', stubDeTextos());

    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);
    $pieza  = $modulo->path('Invoice/Database/Seeders/InvoiceInvoiceTexts.php');

    File::put($pieza, "<?php\n// LO QUE ALGUIEN ESCRIBIÓ A MANO\n");

    // Volver a generar las piezas —lo que hace `add-entity` sobre una subfuncionalidad que ya
    // está— se salta la de textos igual que se salta las otras seis.
    (new SubFeatureSeederGenerator('Invoice', $modulo->path(), true, [
        'name'          => 'Invoice',
        'subFeature'    => 'Invoice',
        'functionality' => 'invoices',
    ]))->generate();

    expect(file_get_contents($pieza))->toContain('LO QUE ALGUIEN ESCRIBIÓ A MANO');
});
