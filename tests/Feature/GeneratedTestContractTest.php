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
 *   3. Que **no enumera** rutas ni permisos de ruta (R76): esos se derivan, y una segunda lista es
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
        'R32 · R76: el grupo de pruebas de la subfuncionalidad empieza por su manifiesto, y vive en '
        . 'la carpeta de la subfuncionalidad (R36).'
    );
})->with([
    'single-app'  => [ModuleMode::SingleApp, null, 'Tests/Feature/Invoice/InvoiceContract.php'],
    'multitenant' => [ModuleMode::MultitenantPerTenant, 'central', 'Tests/Feature/Central/Invoice/CentralInvoiceContract.php'],
]);

it('el manifiesto carga y declara lo que la subfuncionalidad es', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    cargarPiezas($modulo, 'Tests/Feature/Invoice', ['InvoiceContract']);

    $contrato = 'Modules\Invoice\Tests\Feature\Invoice\InvoiceContract';

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
        'invoices',
        'FALLA: el prefijo de rutas no es el de la funcionalidad. · FIX: es lo que filtra '
        . 'Route::getRoutes() en los temas 3-5; si no coincide, esas pruebas no ven ninguna ruta y '
        . 'pasan en verde sin comprobar nada.'
    );

    expect($contrato::VIEW_ACTIONS)->toBe([
        'store'   => 'invoices_view_store',
        'show'    => 'invoices_view_show',
        'update'  => 'invoices_view_update',
        'destroy' => 'invoices_view_destroy',
    ], 'FALLA: las acciones de vista no son las que emite el seeder de permisos. · FIX: las dos '
     . 'salen de SubFeaturePermissions; si divergen, el tema 6 comprueba permisos que no existen.');
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
    'single-app'  => [ModuleMode::SingleApp, null, 'Tests/Feature/Invoice', 'Modules\Invoice\Tests\Feature\Invoice\InvoiceContract'],
    'multitenant' => [ModuleMode::MultitenantPerTenant, 'central', 'Tests/Feature/Central/Invoice', 'Modules\Invoice\Tests\Feature\Central\Invoice\CentralInvoiceContract'],
]);

it('no enumera lo que se deriva del código', function () {
    // R76. El manifiesto declara el prefijo de rutas y el seeder de permisos, que son las dos
    // fuentes; escribir además la lista de rutas o la de permisos de ruta la convertiría en una
    // segunda verdad, y el día que alguien añada una acción solo se actualizaría una de las dos.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $contenido = $modulo->contents('Tests/Feature/Invoice/InvoiceContract.php');

    foreach (['invoices_index', 'invoices_list', 'invoices_store', 'invoices_show', 'invoices_update', 'invoices_destroy'] as $permisoDeRuta) {
        expect($contenido)->not->toContain(
            "'{$permisoDeRuta}'",
            "FALLA: el manifiesto enumera el permiso de ruta '{$permisoDeRuta}'. · FIX: los permisos "
            . 'de ruta se derivan del PermissionsSeeder que ya declara PERMISSION_SEEDER; enumerarlos '
            . 'aquí crea una segunda lista (R76).'
        );
    }
});

it('el módulo entero sigue coherente con el manifiesto dentro', function (ModuleMode $modo, ?string $contexto) {
    $this->generateModule('Invoice', $modo, $contexto)->assertCoherent();
})->with([
    'single-app'  => [ModuleMode::SingleApp, null],
    'multitenant' => [ModuleMode::MultitenantPerTenant, 'central'],
]);
