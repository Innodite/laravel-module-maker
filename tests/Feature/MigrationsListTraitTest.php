<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;

/**
 * El trait que sustituye al manifiesto JSON (P2).
 *
 * El JSON era un segundo sitio describiendo lo que ya decía la carpeta: no viajaba con el módulo
 * cuando alguien lo copiaba a otro proyecto, y se desincronizaba sin avisar. El trait es código,
 * viaja dentro, y **se deriva de la carpeta** en cada generación.
 *
 * Que se derive no basta: hay que comprobarlo. Lista y carpeta son otro par que puede separarse —el
 * quinto de este paquete—, y esta vez el desfase no rompe un archivo, rompe **el despliegue**.
 */

it('el trait se genera con las otras piezas de seeder, no en la carpeta de migraciones', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    expect($modulo->has('Invoice/Database/Seeders/InvoiceInvoiceMigrationsList.php'))->toBeTrue(
        'Es una de las seis piezas de la subfuncionalidad (norma §6), así que vive con ellas. '
        . "Lo generado fue:\n  - " . implode("\n  - ", $modulo->tree())
    );
});

it('el nombre del trait sale de SeederNames, no de una segunda convención', function () {
    // Si el generador se inventara el nombre por su cuenta, tendríamos dos sitios decidiendo lo
    // mismo — que es exactamente lo que SeederNames vino a evitar en la fase anterior.
    $piezas = SeederNames::subFeaturePieces('', 'Invoice', 'Invoice');

    expect($piezas)->toContain('InvoiceInvoiceMigrationsList');

    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    expect($modulo->has('Invoice/Database/Seeders/InvoiceInvoiceMigrationsList.php'))->toBeTrue();
});

it('la lista nombra exactamente las migraciones que hay en la carpeta', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $trait = $modulo->contents('Invoice/Database/Seeders/InvoiceInvoiceMigrationsList.php');

    preg_match_all("/'(Modules\/[^']+\.php)'/", $trait, $encontrados);

    $listadas = array_map('basename', $encontrados[1]);
    $enDisco  = array_map('basename', $modulo->migrations());

    sort($listadas);
    sort($enDisco);

    expect($listadas)->toBe(
        $enDisco,
        "La lista del trait y la carpeta de migraciones dicen cosas distintas.\n"
        . 'En la lista: ' . implode(' · ', $listadas) . "\n"
        . 'En la carpeta: ' . implode(' · ', $enDisco) . "\n\n"
        . 'Un archivo en la carpeta que la lista no nombra no se despliega; una ruta en la lista que '
        . 'no existe rompe el despliegue entero al llegar a ella.'
    );

    expect($listadas)->not->toBeEmpty('Una lista vacía no despliega nada.');
});

it('las rutas de la lista existen de verdad, tal como están escritas', function () {
    // La ruta es relativa a la raíz del proyecto, que en la prueba es el directorio temporal. Un
    // import correcto a un archivo que nadie escribió pasa el parser y revienta al ejecutar: aquí
    // pasa igual con `--path`.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $trait = $modulo->contents('Invoice/Database/Seeders/InvoiceInvoiceMigrationsList.php');

    preg_match_all("/'(Modules\/[^']+\.php)'/", $trait, $encontrados);

    $rotas = array_values(array_filter(
        $encontrados[1],
        fn (string $relativa): bool => ! file_exists($this->tempPath($relativa)),
    ));

    expect($rotas)->toBe([], "Estas rutas de la lista no existen:\n  - " . implode("\n  - ", $rotas));
});

it('en multitenant el trait lleva el prefijo de su contexto', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::Multitenant, 'central');

    expect($modulo->has('Invoice/Database/Seeders/Central/CentralInvoiceInvoiceMigrationsList.php'))
        ->toBeTrue(
            'En multitenant hay una lista por contexto y sin prefijo colisionarían. '
            . "Lo generado fue:\n  - " . implode("\n  - ", $modulo->tree())
        );
});
