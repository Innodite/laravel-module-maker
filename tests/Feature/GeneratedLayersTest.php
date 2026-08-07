<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * Las capas generadas, contrastadas contra la tabla que las acompaña.
 *
 * **Por qué existe este archivo.** La clave primaria pasó a ULID en la fase 2 — la migración y el
 * modelo se escribieron juntos y se comprueban juntos en `GeneratedModelTest`. Pero el cambio se
 * quedó ahí: las **cinco piezas de capa** siguieron declarando `int $id` durante tres fases enteras,
 * y ninguna de las 289 pruebas lo veía, porque ninguna leía una firma.
 *
 * El síntoma tampoco ayudaba a encontrarlo. Con `strict_types` un ULID contra un `int` no se
 * convierte: lanza `TypeError`, que sale como un 500. Y un 500 hace **pasar** a la prueba generada
 * que comprueba que un identificador inexistente no devuelva 200 — verde por la razón equivocada,
 * mientras la que borra de verdad quedaba en rojo sin explicar por qué.
 *
 * Lo que este archivo fija no es el tipo `string`: es que **las cinco piezas cambien juntas**. El día
 * que la clave vuelva a cambiar de forma, esto falla en las cinco a la vez y dice cuál falta.
 */

/** Las cinco piezas por las que viaja el identificador, en el orden en que lo hace. */
const CAPAS_SINGLE_APP = [
    'el controlador'          => 'Http/Controllers/Invoice/InvoiceController.php',
    'el servicio'             => 'Services/Invoice/InvoiceService.php',
    'el contrato del servicio' => 'Services/Contracts/Invoice/InvoiceServiceInterface.php',
    'el repositorio'          => 'Repositories/Invoice/InvoiceRepository.php',
    'el contrato del repositorio' => 'Repositories/Contracts/Invoice/InvoiceRepositoryInterface.php',
];

it('ninguna capa declara el identificador como entero', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $migracion = file_get_contents($modulo->migrations()[0]);

    expect(str_contains($migracion, "\$table->ulid('id')->primary();"))->toBeTrue(
        'La tabla generada declara su clave primaria como ULID. Si esto cambia, esta prueba entera '
        . "está midiendo contra la referencia equivocada.\nLa migración dice:\n" . $migracion
    );

    foreach (CAPAS_SINGLE_APP as $quien => $ruta) {
        $codigo = $modulo->contents($ruta);

        expect(str_contains($codigo, 'int $id'))->toBeFalse(
            "La tabla lleva ULID y {$quien} declara `int \$id`. Un ULID son veintiséis caracteres, "
            . "no un número: con `strict_types` la llamada muere con TypeError antes de tocar la "
            . "base de datos, y sale como un 500 que es fácil confundir con otra cosa.\n"
            . "· FIX: `string \$id` en las cinco piezas de capa, no solo en esta — el identificador "
            . "las atraviesa todas.\n{$ruta} dice:\n" . $codigo
        );
    }
});

it('el identificador viaja como string por las cinco capas', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    foreach (CAPAS_SINGLE_APP as $quien => $ruta) {
        expect(str_contains($modulo->contents($ruta), 'string $id'))->toBeTrue(
            "{$quien} no recibe el identificador en ninguna firma. · FIX: las tres operaciones que "
            . 'trabajan sobre un registro concreto —consultar, actualizar y eliminar— lo reciben '
            . "como `string \$id`.\nRevisa {$ruta}."
        );
    }
});

it('el identificador tampoco es entero en multitenant', function () {
    // El modo decide el prefijo de clase y la carpeta de contexto, no la forma de la clave: la
    // migración escribe el mismo ULID en los tres modos. Si el cambio se hubiera aplicado solo al
    // camino que las pruebas recorren más, este modo se habría quedado atrás — que es exactamente
    // lo que le pasó a `tenant_shared` con el manifiesto del contrato.
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    $capas = [
        'el controlador'   => 'Http/Controllers/Central/Invoice/CentralInvoiceController.php',
        'el servicio'      => 'Services/Central/Invoice/CentralInvoiceService.php',
        'el repositorio'   => 'Repositories/Central/Invoice/CentralInvoiceRepository.php',
    ];

    foreach ($capas as $quien => $ruta) {
        expect(str_contains($modulo->contents($ruta), 'int $id'))->toBeFalse(
            "En multitenant, {$quien} declara `int \$id` mientras la tabla lleva ULID. · FIX: la "
            . 'firma sale del stub, que es común a los tres modos — corrígela ahí y no en una copia.'
        );
    }
});

it('solo el repositorio conoce el modelo', function () {
    // La razón por la que el identificador viaja como dato y no como modelo resuelto: si el
    // controlador recibiera `show(Invoice $invoice)`, Eloquent estaría dos capas por encima de donde
    // le toca. Route Model Binding es lo idiomático de Laravel y aquí está descartado a propósito;
    // esta prueba es lo que impide que alguien lo reintroduzca por comodidad.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $sinModelo = [
        'el controlador'  => 'Http/Controllers/Invoice/InvoiceController.php',
        'el servicio'     => 'Services/Invoice/InvoiceService.php',
    ];

    foreach ($sinModelo as $quien => $ruta) {
        expect(str_contains($modulo->contents($ruta), 'Models\\Invoice'))->toBeFalse(
            "{$quien} importa el modelo. · FIX: el modelo lo instancia solo el Repository. Si esto "
            . 'entró por un Route Model Binding, deshazlo: resuelve el registro dos capas por encima '
            . "de donde le toca, y el ScaffoldTest generado comprueba justamente esta frontera.\n"
            . $modulo->contents($ruta)
        );
    }

    expect(str_contains($modulo->contents('Repositories/Invoice/InvoiceRepository.php'), 'Models\\Invoice'))
        ->toBeTrue('El repositorio sí importa el modelo: es la única capa que puede.');
});
