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
    'el controlador'          => 'Invoice/Http/Controllers/InvoiceController.php',
    'el servicio'             => 'Invoice/Services/InvoiceService.php',
    'el contrato del servicio' => 'Invoice/Services/Contracts/InvoiceServiceInterface.php',
    'el repositorio'          => 'Invoice/Repositories/InvoiceRepository.php',
    'el contrato del repositorio' => 'Invoice/Repositories/Contracts/InvoiceRepositoryInterface.php',
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
    $modulo = $this->generateModule('Invoice', ModuleMode::Multitenant, 'central');

    $capas = [
        'el controlador'   => 'Invoice/Http/Controllers/Central/CentralInvoiceController.php',
        'el servicio'      => 'Invoice/Services/Central/CentralInvoiceService.php',
        'el repositorio'   => 'Invoice/Repositories/Central/CentralInvoiceRepository.php',
    ];

    foreach ($capas as $quien => $ruta) {
        expect(str_contains($modulo->contents($ruta), 'int $id'))->toBeFalse(
            "En multitenant, {$quien} declara `int \$id` mientras la tabla lleva ULID. · FIX: la "
            . 'firma sale del stub, que es común a los tres modos — corrígela ahí y no en una copia.'
        );
    }
});

it('el controlador recibe sus FormRequests, no un Request genérico', function () {
    // C-1, el crítico que abrió la auditoría: el controlador declaraba `Request $request` y llamaba
    // a `$request->validated()`. Ese método no existe en `Illuminate\Http\Request` — solo en
    // FormRequest—, así que el primer alta real moría con un Error fatal. Y no se veía al generar:
    // el archivo es PHP válido, el chequeo de salida lo daba por bueno.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $controlador = $modulo->contents('Invoice/Http/Controllers/InvoiceController.php');

    foreach (['store' => 'InvoiceStoreRequest', 'update' => 'InvoiceUpdateRequest'] as $accion => $clase) {
        expect(str_contains($controlador, "public function {$accion}({$clase} \$request"))->toBeTrue(
            "`{$accion}()` no recibe {$clase}. · FIX: la validación vive en el FormRequest, y para "
            . "que se aplique tiene que estar en la firma; con un Request genérico, validated() no "
            . "existe y la primera llamada real muere.\nEl controlador dice:\n" . $controlador
        );

        expect(str_contains($controlador, "use Modules\\Invoice\\Invoice\\Http\\Requests\\{$clase};"))->toBeTrue(
            "El controlador usa {$clase} sin importarlo. · FIX: el import lo escribe el generador, "
            . 'que es quien sabe en qué namespace acaba de escribir la clase.'
        );
    }

    // El borde: `list()` sí recibe un Request genérico y es correcto — no valida, lee filtros.
    expect(str_contains($controlador, 'public function list(Request $request'))->toBeTrue(
        'El listado dejó de recibir Request. · FIX: es el único que debe recibirlo — no valida '
        . 'nada, solo lee los filtros de paginación. Convertirlo en FormRequest añade una clase '
        . 'vacía que nadie rellena.'
    );
});

it('los FormRequests que el controlador importa son los que el generador escribe', function () {
    // El defecto que motivó `RequestNames`: el nombre lo componían dos sitios distintos. Mientras
    // coincidan por casualidad no pasa nada; el día que uno cambie, el controlador importará una
    // clase que no existe y el error saldrá al arrancar la aplicación, no al generar.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    foreach (['InvoiceStoreRequest', 'InvoiceUpdateRequest'] as $clase) {
        expect($modulo->has("Invoice/Http/Requests/{$clase}.php"))->toBeTrue(
            "El controlador importa {$clase} y el generador no lo escribe. · FIX: los dos leen el "
            . 'nombre de RequestNames; si divergen, es que alguno volvió a componerlo por su cuenta.'
        );
    }
});

it('los dos FormRequests se generan también en multitenant, con el prefijo de su contexto', function () {
    // El modo que se quedaba atrás: aquí se escribía **un solo** Request genérico, mientras el
    // manifiesto del contrato declaraba siempre …StoreRequest. La prueba del andamiaje generada
    // pedía una clase que en este camino nadie escribía nunca.
    $modulo = $this->generateModule('Invoice', ModuleMode::Multitenant, 'central');

    foreach (['CentralInvoiceStoreRequest', 'CentralInvoiceUpdateRequest'] as $clase) {
        expect($modulo->has("Invoice/Http/Requests/Central/{$clase}.php"))->toBeTrue(
            "Falta {$clase}. · FIX: los dos FormRequests se generan en los tres modos. Un camino "
            . 'que escriba una pieza distinta es lo que deja al manifiesto apuntando a nada.'
        );
    }

    expect(str_contains(
        $modulo->contents('Invoice/Http/Controllers/Central/CentralInvoiceController.php'),
        'public function store(CentralInvoiceStoreRequest $request'
    ))->toBeTrue('En multitenant el controlador recibe el FormRequest con el prefijo de su contexto.');
});

it('solo el repositorio conoce el modelo', function () {
    // La razón por la que el identificador viaja como dato y no como modelo resuelto: si el
    // controlador recibiera `show(Invoice $invoice)`, Eloquent estaría dos capas por encima de donde
    // le toca. Route Model Binding es lo idiomático de Laravel y aquí está descartado a propósito;
    // esta prueba es lo que impide que alguien lo reintroduzca por comodidad.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $sinModelo = [
        'el controlador'  => 'Invoice/Http/Controllers/InvoiceController.php',
        'el servicio'     => 'Invoice/Services/InvoiceService.php',
    ];

    foreach ($sinModelo as $quien => $ruta) {
        expect(str_contains($modulo->contents($ruta), 'Models\\Invoice'))->toBeFalse(
            "{$quien} importa el modelo. · FIX: el modelo lo instancia solo el Repository. Si esto "
            . 'entró por un Route Model Binding, deshazlo: resuelve el registro dos capas por encima '
            . "de donde le toca, y el ScaffoldTest generado comprueba justamente esta frontera.\n"
            . $modulo->contents($ruta)
        );
    }

    expect(str_contains($modulo->contents('Invoice/Repositories/InvoiceRepository.php'), 'Models\\Invoice'))
        ->toBeTrue('El repositorio sí importa el modelo: es la única capa que puede.');
});
