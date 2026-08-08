<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * La vista generada, contrastada contra las rutas que va a llamar.
 *
 * **Por qué hace falta este archivo.** Los defectos de la pantalla no se ven leyéndola: el `.vue` es
 * correcto, compila, y sus botones están donde tienen que estar. Fallan al usarse, en el navegador
 * del usuario, y de formas que se confunden con otra cosa:
 *
 *   - `route()` recibiendo el nombre de un **permiso** en vez del de una ruta. Los botones «Nuevo» y
 *     «Editar» lanzaban `RouteNotFoundException` **al pulsarse**. Nació al reconectar los `can()` a
 *     la fuente única: se sustituyó la clave en los dos sitios cuando solo uno debía cambiar.
 *   - El listado pidiendo `index` —la ruta Inertia, que devuelve la **pantalla**— en vez de `list`.
 *     La petición respondía 200 con HTML y la tabla se quedaba vacía, que se lee igual que «todavía
 *     no hay datos».
 *   - Y el nombre de ruta derivado del **modelo** mientras las rutas se nombran por la
 *     **funcionalidad**: coinciden mientras el módulo tenga una sola subfuncionalidad, y dejan de
 *     coincidir en cuanto tiene dos.
 *
 * Los tres tienen la misma forma: dos lados correctos por separado que apuntan a sitios distintos.
 * Por eso lo que se comprueba aquí no es el contenido de la vista, es **la coherencia entre la vista
 * y el archivo de rutas** que el mismo módulo acaba de escribir.
 */

it('la vista pide exactamente los nombres de ruta que el módulo declara', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $vista = $modulo->contents('resources/js/Pages/Invoice/InvoiceIndex.vue');
    $rutas = $modulo->contents('Routes/web.php');

    // Cada `contextRoute('algo.accion')` de la vista tiene que existir como ruta con ese nombre.
    preg_match_all("/contextRoute\('([^']+)'\)/", $vista, $encontrados);

    expect($encontrados[1])->not->toBeEmpty('La vista no llama a ninguna ruta: algo se rompió antes.');

    foreach (array_unique($encontrados[1]) as $nombreCompleto) {
        [$base, $accion] = explode('.', $nombreCompleto, 2);

        expect(str_contains($rutas, "Route::prefix('{$base}')"))->toBeTrue(
            "FALLA: la vista pide '{$nombreCompleto}' y el módulo no declara ninguna ruta bajo "
            . "'{$base}'. · FIX: el prefijo del nombre sale de getFunctionality(), el mismo sitio "
            . "del que lo toma el generador de rutas.\nLas rutas dicen:\n" . $rutas
        );

        expect(str_contains($rutas, "->name('{$accion}')"))->toBeTrue(
            "FALLA: la vista pide la acción '{$accion}' y no existe entre las rutas generadas. "
            . '· FIX: las seis acciones salen de SubFeaturePermissions; si la vista pide otra, es '
            . 'que se escribió a mano.'
        );
    }
});

it('el listado pide el endpoint de datos, no la pantalla', function () {
    // `index` y `list` son dos rutas distintas a propósito: la primera devuelve la pantalla por
    // Inertia y la segunda los datos. Pedir la primera con axios responde 200 —con el HTML— y deja
    // la tabla vacía sin un solo error en consola.
    $vista = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('resources/js/Pages/Invoice/InvoiceIndex.vue');

    expect(str_contains($vista, "axios.get(route(contextRoute('invoices.list'))"))->toBeTrue(
        'FALLA: el listado no pide `list`. · FIX: `index` devuelve la pantalla; los datos están en '
        . "`list`, y cada una tiene su permiso propio.\nLa vista dice:\n" . $vista
    );
});

it('ningún route() de la vista recibe el nombre de un permiso', function () {
    // El defecto que dejaba los botones «Nuevo» y «Editar» reventando al pulsarse. Se comprueba
    // sobre las cuatro vistas porque el de detalle tenía el suyo propio.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    foreach (['Index', 'Create', 'Edit', 'Show'] as $pieza) {
        $vista = $modulo->contents("resources/js/Pages/Invoice/Invoice{$pieza}.vue");

        preg_match_all("/contextRoute\('([^']+)'\)/", $vista, $encontrados);

        foreach ($encontrados[1] as $nombre) {
            expect(str_contains($nombre, 'view_'))->toBeFalse(
                "FALLA: en Invoice{$pieza}.vue, route() recibe '{$nombre}', que es un permiso de "
                . 'vista y no un nombre de ruta. · FIX: los permisos van en can(), las rutas en '
                . 'route(). El generador los entrega por separado justo para que no se confundan.'
            );

            expect(str_contains($nombre, '.'))->toBeTrue(
                "FALLA: en Invoice{$pieza}.vue, route() recibe '{$nombre}', que no tiene forma de "
                . 'nombre de ruta. · FIX: son `{base}.{accion}`.'
            );
        }
    }
});

it('las tres acciones ocurren en modales sobre el listado, sin navegar', function () {
    // Con seis rutas y una sola pantalla, navegar a «crear» o «editar» es ir a una ruta que no
    // existe — y de ahí venía el defecto de arriba: no había nombre de ruta que pasarle a route(),
    // así que se le pasó el permiso. Las tres acciones se resuelven aquí mismo.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $index = $modulo->contents('resources/js/Pages/Invoice/InvoiceIndex.vue');

    expect(str_contains($index, 'router.visit'))->toBeFalse(
        'FALLA: la pantalla navega a otra ruta. · FIX: de las seis rutas generadas solo `index` '
        . 'devuelve una pantalla; crear, ver y editar son modales sobre esta misma tabla.'
    );

    foreach (['InvoiceCreate', 'InvoiceEdit', 'InvoiceShow'] as $modal) {
        expect(str_contains($index, $modal))->toBeTrue(
            "FALLA: el listado no monta {$modal}. · FIX: los tres se abren como modal desde aquí; "
            . 'si no se montan, sus archivos quedan escritos y sin forma de alcanzarse.'
        );
    }

    // Y los tres avisan al índice en vez de decidir por su cuenta: el estado de «qué registro estoy
    // mirando» vive en un solo sitio.
    foreach (['Create', 'Edit'] as $pieza) {
        expect(str_contains($modulo->contents("resources/js/Pages/Invoice/Invoice{$pieza}.vue"), "emit('guardado')"))
            ->toBeTrue("FALLA: Invoice{$pieza} no avisa de que guardó. · FIX: emite `guardado`, y el "
                . 'índice cierra el modal y recarga la tabla.');
    }
});
