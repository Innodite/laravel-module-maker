<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El modelo generado, contrastado contra la tabla que lo acompaña.
 *
 * Este archivo existe porque migración y modelo son **dos mitades del mismo par**: la tabla lleva
 * ULID y borrado lógico, y si el modelo no trae `HasUlids` las inserciones salen sin clave, ni
 * `SoftDeletes` el `deleted_at` queda de adorno y un `delete()` borra de verdad. Las dos mitades se
 * escriben en la misma pasada y aquí se comprueban juntas — que es la única forma de que no se
 * separen.
 */

it('el modelo trae ULID y borrado lógico, como la tabla que le corresponde', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $modelo    = $modulo->contents('Invoice/Models/Invoice.php');
    $migracion = file_get_contents($modulo->migrations()[0]);

    expect(str_contains($migracion, "\$table->ulid('id')->primary();"))->toBeTrue('La tabla usa ULID…');
    expect(str_contains($modelo, 'use HasUlids;'))->toBeTrue(
        '…así que el modelo necesita `HasUlids`: sin él, Eloquent no genera la clave y cada '
        . "inserción sale sin id.\nEl modelo dice:\n" . $modelo
    );

    expect(str_contains($migracion, '$table->softDeletes();'))->toBeTrue('La tabla trae deleted_at…');
    expect(str_contains($modelo, 'use SoftDeletes;'))->toBeTrue(
        '…así que el modelo necesita `SoftDeletes`. Sin el trait, la columna existe y '
        . 'nadie la usa: un delete borra de verdad y no hay forma de restaurar.'
    );
});

it('los imports van fuera de la clase, no dentro', function () {
    // Un `use Modules\X\Models\Y;` DENTRO del cuerpo de una clase no es un import: PHP lo lee como
    // uso de un trait, y el modelo muere con «Trait not found» en cuanto el módulo declara una
    // relación. Sintaxis válida, así que el chequeo de salida lo daba por bueno.
    $modelo = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Invoice/Models/Invoice.php');

    $cuerpo = substr($modelo, (int) strpos($modelo, 'class '));

    preg_match_all('/^\s*use\s+([A-Za-z_][A-Za-z0-9_\\\\]*);/m', $cuerpo, $encontrados);

    $conNamespace = array_values(array_filter(
        $encontrados[1],
        static fn (string $usado): bool => str_contains($usado, '\\'),
    ));

    expect($conNamespace)->toBe(
        [],
        "Dentro de la clase solo caben traits por su nombre corto. Estos llevan namespace, así que "
        . "son imports mal colocados:\n  - " . implode("\n  - ", $conNamespace)
    );
});

it('la conexión del modelo la decide el modo, y son tres respuestas distintas', function () {
    $single = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Invoice/Models/Invoice.php');

    expect(str_contains($single, '$connection'))->toBeFalse(
        'en single-app hay una sola base de datos. Declarar conexión ahí es una línea muerta '
        . 'en cada modelo de cada módulo.'
    );

    $central = $this->generateModule('Payment', ModuleMode::Multitenant, 'central')
        ->contents('Payment/Models/Central/CentralPayment.php');

    expect(str_contains($central, "protected \$connection = 'central';"))->toBeTrue(
        'la app central declara siempre la suya.'
    );
});

it('el modelo generado se puede cargar, con sus traits resueltos', function () {
    // La comprobación que ninguna de las anteriores hace: que el archivo **se pueda ejecutar**. Un
    // `use TraitQueNoExiste;` pasa el parser y revienta al instanciar.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);
    $ruta   = $modulo->path('Invoice/Models/Invoice.php');

    require_once $ruta;

    $clase = 'Modules\\Invoice\\Invoice\\Models\\Invoice';

    expect(class_exists($clase))->toBeTrue(
        'La clase debe existir tras incluir el archivo: si no, el namespace no espeja la carpeta.'
    );

    $modelo = new $clase();

    expect($modelo->getTable())->toBe('invoices');
    expect($modelo->getKeyType())->toBe('string', 'HasUlids conectado: la clave es texto, no entero.');
    expect($modelo->getIncrementing())->toBeFalse('Y no autoincrementa: la genera el modelo.');
    expect(method_exists($modelo, 'trashed'))->toBeTrue('SoftDeletes conectado.');
});
