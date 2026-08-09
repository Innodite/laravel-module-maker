<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El segundo registro de R22b: el delta con guardia.
 *
 * La norma pide una sola `_final` por tabla, con el esquema completo — así una instalación nueva
 * levanta la tabla como está hoy, sin replicar su historial de cambios. Pero un proyecto que ya
 * desplegó esa tabla no puede recibir un `create` otra vez: necesita el delta. Los dos sitios
 * describen el mismo cambio, y olvidar cualquiera de ellos deja a la mitad de las instalaciones sin
 * la columna.
 */

it('el trait se genera con las otras piezas, con el nombre de la convención', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    expect($modulo->has('Database/Seeders/Invoice/InvoiceInvoiceInlineAlters.php'))->toBeTrue(
        "Es una de las seis piezas de la subfuncionalidad.\nLo generado fue:\n  - "
        . implode("\n  - ", $modulo->tree())
    );
});

it('nace vacío, y eso es lo correcto', function () {
    // Un módulo recién generado no tiene cambios posteriores: su esquema entero está en la `_final`.
    // Emitir deltas inventados sería B3 otra vez — una pieza que aparenta contenido y no hace nada.
    $trait = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Database/Seeders/Invoice/InvoiceInvoiceInlineAlters.php');

    expect($trait)->toContain('public function runInlineAlters(): void');

    $cuerpo = substr($trait, (int) strpos($trait, 'public function runInlineAlters'));

    expect(str_contains($cuerpo, 'Schema::table('))->toBeFalse(
        'El método nace sin deltas: no hay cambios que aplicar en un módulo recién creado. Lo que '
        . 'sí lleva es el patrón documentado, para que el primero se escriba con su guardia.'
    );
});

it('lleva escrito el patrón de la guardia, con la tabla real del módulo', function () {
    // Sin la guardia, el método falla en el segundo despliegue con «duplicate column»: se ejecuta
    // en cada uno. Que el ejemplo nombre la tabla real es lo que hace que se copie bien.
    $trait = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Database/Seeders/Invoice/InvoiceInvoiceInlineAlters.php');

    expect(str_contains($trait, "Schema::hasTable('invoices')"))->toBeTrue(
        'El patrón documentado debe nombrar la tabla de esta subfuncionalidad, no un ejemplo genérico.'
    );
    expect(str_contains($trait, 'hasColumn'))->toBeTrue('Y la guardia por columna.');
});

it('no se reescribe si ya existe: dentro vive código del desarrollador', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);
    $ruta   = $modulo->path('Database/Seeders/Invoice/InvoiceInvoiceInlineAlters.php');

    file_put_contents($ruta, str_replace(
        'public function runInlineAlters(): void',
        "public function miDeltaPropio(): void {}\n\n    public function runInlineAlters(): void",
        file_get_contents($ruta)
    ));

    // Regenerar el módulo no debe llevarse por delante lo escrito a mano.
    $this->generateModule('Payment', ModuleMode::SingleApp);

    expect(str_contains(file_get_contents($ruta), 'miDeltaPropio'))->toBeTrue(
        'El trait de deltas guarda código escrito por el desarrollador: sobrescribirlo al regenerar '
        . 'le borraría el trabajo.'
    );
});

it('el módulo con sus dos traits sigue siendo coherente', function () {
    $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')->assertCoherent();
});
