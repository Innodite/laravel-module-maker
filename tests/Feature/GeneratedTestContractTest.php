<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Tests\Support\GeneratedModule;

/**
 * El manifiesto del grupo de pruebas: lo no derivable, y solo eso.
 *
 * **Por qué el contrato es la primera pieza del grupo.** Sin él, cada una de las cinco piezas tendría
 * que declarar por su cuenta las tablas, las acciones de la vista y el andamiaje — y ese es
 * exactamente el defecto que infla las suites: la misma lista escrita en cuatro sitios, actualizada
 * en uno. Con él, añadir un campo es tocar **un archivo** y los temas 0, 1, 2 y 8 lo recorren solos.
 *
 * **Lo que estas pruebas vigilan no es que el archivo exista**, que es lo que comprobaba la v3 y lo
 * que dejó pasar cinco críticos. Vigilan tres cosas que solo se ven con el árbol delante:
 *
 *   1. Que el contrato **carga y responde**: las constantes están declaradas y valen algo.
 *   2. Que cada pieza del `SCAFFOLD` **apunta a un archivo que este mismo módulo escribió**. Un
 *      manifiesto que nombra una clase inexistente pasa el parser y revienta al primer `class_exists`.
 *   3. Que **no enumera** rutas ni permisos de ruta: esos se derivan, y una segunda lista es
 *      una lista que se queda atrás.
 */

/** Del FQCN de una pieza a la ruta del archivo que debería haberla escrito. */
function archivoDeLaClase(GeneratedModule $modulo, string $fqcn): string
{
    $prefijo = "Modules\\{$modulo->name}\\";
    $relativa = str_replace('\\', '/', substr(ltrim($fqcn, '\\'), strlen($prefijo)));

    return $relativa . '.php';
}

/** De la ruta relativa al proyecto (`Modules/X/…`) a la ruta dentro del árbol generado. */
function rutaEnElArbol(GeneratedModule $modulo, string $relativaAlProyecto): string
{
    return substr($relativaAlProyecto, strlen("Modules/{$modulo->name}/"));
}

it('el módulo generado trae el manifiesto de su subfuncionalidad', function (ModuleMode $modo, ?string $contexto, string $ruta) {
    $modulo = $this->generateModule('Invoice', $modo, $contexto);

    $modulo->assertTreeHas(
        [$ruta],
        'el grupo de pruebas de la subfuncionalidad empieza por su manifiesto, y vive en '
        . 'la carpeta de la subfuncionalidad.'
    );
})->with([
    'single-app'  => [ModuleMode::SingleApp, null, 'Invoice/Tests/Feature/InvoiceContract.php'],
    'multitenant' => [ModuleMode::Multitenant, 'central', 'Invoice/Tests/Feature/Central/CentralInvoiceContract.php'],
]);

it('el manifiesto carga y declara lo que la subfuncionalidad es', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    cargarPiezas($modulo, 'Invoice/Tests/Feature', ['InvoiceContract']);

    $contrato = 'Modules\Invoice\Invoice\Tests\Feature\InvoiceContract';

    expect($contrato::CONNECTION)->toBeNull(
        'FALLA: en single-app no hay conexión declarada, y el contrato dice otra cosa. · FIX: la '
        . 'conexión sale de connectionKey(), el mismo sitio del que la toma el modelo.'
    );

    expect(array_keys($contrato::TABLES))->toBe(
        ['invoices'],
        'FALLA: el contrato no declara la tabla de la subfuncionalidad. · FIX: sale de tableName(), '
        . 'la misma que crea la migración.'
    );

    expect($contrato::TABLES['invoices'])->toContain('id', 'created_at', 'deleted_at');

    expect($contrato::ROUTE_PREFIX)->toBe(
        'invoices.',
        'FALLA: el prefijo no es el del NOMBRE de las rutas, con su punto final. · FIX: es lo que '
        . 'filtra Route::getRoutes() en los temas 3-5; si no coincide, esas pruebas no ven ninguna '
        . 'ruta y pasan en verde sin comprobar nada. El punto lo hace exacto: `invoices.` no alcanza '
        . 'a las rutas de `invoice_lines`.'
    );

    expect($contrato::VIEW_ACTIONS)->toBe([
        'store'   => 'invoices_view_store',
        'show'    => 'invoices_view_show',
        'update'  => 'invoices_view_update',
        'destroy' => 'invoices_view_destroy',
        'restore' => 'invoices_view_restore',
    ], 'FALLA: las acciones de vista no son las que emite el seeder de permisos. · FIX: las dos '
     . 'salen de SubFeaturePermissions; si divergen, el tema 6 comprueba permisos que no existen.');
});

it('el andamiaje declara los dos FormRequests, no uno', function () {
    // La prueba de más arriba comprueba que **cada** pieza declarada existe. Esta comprueba lo
    // contrario, que es lo que aquella no puede ver: que **no falte** ninguna. Quitar
    // `form_request_update` del manifiesto no rompe nada — simplemente deja al FormRequest de la
    // edición sin nadie que lo mire, y «sin escribir» pasa a verse igual que «terminado».
    //
    // Es el hueco por el que el manifiesto llegó a declarar una sola clase mientras el generador
    // escribía otra cosa en uno de sus modos.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    cargarPiezas($modulo, 'Invoice/Tests/Feature', ['InvoiceContract']);

    $contrato = 'Modules\Invoice\Invoice\Tests\Feature\InvoiceContract';

    expect($contrato::SCAFFOLD)->toHaveKeys(
        ['form_request_store', 'form_request_update'],
        'FALLA: el andamiaje no declara los dos FormRequests. · FIX: toda subfuncionalidad nace con '
        . 'el del alta y el de la edición; el manifiesto declara los dos para que el tema 0 mire los '
        . 'dos. Salen de RequestNames, igual que los que el controlador recibe en su firma.'
    );

    expect($contrato::SCAFFOLD['form_request_store'])->toBe(
        'Modules\Invoice\Invoice\Http\Requests\InvoiceStoreRequest',
        'FALLA: el manifiesto apunta a otra clase que la que el generador escribe.'
    );

    expect($contrato::SCAFFOLD['form_request_update'])->toBe(
        'Modules\Invoice\Invoice\Http\Requests\InvoiceUpdateRequest',
        'FALLA: el manifiesto apunta a otra clase que la que el generador escribe.'
    );
});

it('cada pieza del andamiaje apunta a algo que el módulo escribió', function (ModuleMode $modo, ?string $contexto, string $carpeta, string $contrato) {
    // El cruce que ningún chequeo por archivo puede hacer: le falta el otro lado del par. Un
    // manifiesto con un FQCN mal compuesto —el namespace de otra capa, el nombre sin prefijo— pasa
    // el parser, y el tema 0 falla el día del despliegue en vez de aquí.
    $modulo = $this->generateModule('Invoice', $modo, $contexto);

    cargarPiezas($modulo, $carpeta, [basename(str_replace('\\', '/', $contrato))]);

    $faltan = [];

    foreach ($contrato::SCAFFOLD as $pieza => $referencia) {
        if (str_contains($referencia, '/')) {
            $encontrados = glob($modulo->path(rutaEnElArbol($modulo, $referencia))) ?: [];

            if ($encontrados === []) {
                $faltan[$pieza] = $referencia;
            }

            continue;
        }

        if (! $modulo->has(archivoDeLaClase($modulo, $referencia))) {
            $faltan[$pieza] = $referencia;
        }
    }

    expect($faltan)->toBe(
        [],
        "FALLA: el manifiesto declara piezas que este módulo no escribió: "
        . implode(' · ', array_keys($faltan)) . ".\n"
        . "  · FIX: el contrato compone cada referencia con los mismos helpers que usa el generador "
        . "de esa pieza; si no coincide, uno de los dos cambió sin el otro.\n\n"
        . "Declarado:\n  - " . implode("\n  - ", array_map(
            static fn (string $k, string $v): string => "{$k}: {$v}",
            array_keys($faltan),
            $faltan,
        ))
        . "\n\nLo generado fue:\n  - " . implode("\n  - ", $modulo->tree())
    );
})->with([
    'single-app'  => [ModuleMode::SingleApp, null, 'Invoice/Tests/Feature', 'Modules\Invoice\Invoice\Tests\Feature\InvoiceContract'],
    'multitenant' => [ModuleMode::Multitenant, 'central', 'Invoice/Tests/Feature/Central', 'Modules\Invoice\Invoice\Tests\Feature\Central\CentralInvoiceContract'],
]);

it('no enumera lo que se deriva del código', function () {
    // El manifiesto declara el prefijo de rutas y el seeder de permisos, que son las dos
    // fuentes; escribir además la lista de rutas o la de permisos de ruta la convertiría en una
    // segunda verdad, y el día que alguien añada una acción solo se actualizaría una de las dos.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $contenido = $modulo->contents('Invoice/Tests/Feature/InvoiceContract.php');

    // Se afirma sobre el booleano y no con `not->toContain($permiso, $mensaje)`: ese segundo
    // argumento no es el mensaje —`toContain()` recibe valores—, así que la negación acabaría
    // comprobando que el archivo tampoco contiene el texto de ayuda. Es decir: pasaría siempre,
    // incluso con el permiso enumerado dentro. Una prueba verde por la razón equivocada.
    foreach (['invoices_index', 'invoices_list', 'invoices_store', 'invoices_show', 'invoices_update', 'invoices_destroy'] as $permisoDeRuta) {
        expect(str_contains($contenido, "'{$permisoDeRuta}'"))->toBeFalse(
            "FALLA: el manifiesto enumera el permiso de ruta '{$permisoDeRuta}'. · FIX: los permisos "
            . 'de ruta se derivan del PermissionsSeeder que ya declara PERMISSION_SEEDER; enumerarlos '
            . 'aquí crea una segunda lista.'
        );
    }
});

it('el módulo entero sigue coherente con el manifiesto dentro', function (ModuleMode $modo, ?string $contexto) {
    $this->generateModule('Invoice', $modo, $contexto)->assertCoherent();
})->with([
    'single-app'  => [ModuleMode::SingleApp, null],
    'multitenant' => [ModuleMode::Multitenant, 'central'],
]);
