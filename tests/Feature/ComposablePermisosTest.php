<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\SubFeaturePermissions;

/**
 * El composable que consulta los permisos y la clase que los emite, contrastados.
 *
 * Es la misma pareja de siempre: un lado escribe un nombre y el otro lo busca. `SubFeaturePermissions`
 * lo compone en PHP —`central_roles_update`, todo con guion bajo— y `usePermissions.js` lo consulta
 * en el navegador. Los dos calculaban por su cuenta, y el segundo usaba **punto**: buscaba
 * `central.roles_update`, un nombre que ningún seeder crea.
 *
 * El punto no salió de la nada — es el separador correcto **de los nombres de ruta**
 * (`central.roles.index`), que compone `useModuleContext().contextRoute()`. Copiado a los permisos,
 * produce una comprobación que no puede acertar nunca.
 *
 * Y era invisible: las vistas generadas pasan el nombre **completo** y aciertan por la otra rama.
 * Quien se rompía era el código escrito a mano, en silencio, con el botón oculto — que es
 * exactamente cómo se ve un permiso que no existe.
 */

/** El stub tal y como se publica al proyecto del usuario. */
function composablePermisos(): string
{
    return file_get_contents(dirname(__DIR__, 2) . '/stubs/resources/js/Composables/usePermissions.js');
}

/**
 * El mismo archivo **sin comentarios** — lo que de verdad se ejecuta.
 *
 * Hace falta porque el docblock explica el defecto retirado, y nombrarlo para advertir no es
 * cometerlo. Sin este despojado, la prueba que prohíbe el punto se disparaba con la línea que
 * justamente avisa de no volver a ponerlo: castigaba la documentación en vez del código.
 */
function codigoDelComposablePermisos(): string
{
    return preg_replace(
        ['#/\*.*?\*/#s', '#(^|\s)//[^\n]*#'],
        ' ',
        composablePermisos()
    );
}

it('el composable compone el permiso con el mismo separador que lo emite PHP', function () {
    // El nombre real, salido de quien lo emite. No se escribe a mano en la prueba: si mañana
    // cambia la convención, este contraste tiene que moverse con ella.
    $real = SubFeaturePermissions::permissionName('central', 'roles', 'update');

    expect($real)->toBe('central_roles_update');

    $js = codigoDelComposablePermisos();

    expect(str_contains($js, '`${prefix}_${permission}`'))->toBeTrue(
        'FALLA: el composable no compone el permiso con guion bajo. · FIX: PHP emite '
        . "'{$real}'; el JS tiene que buscar ese mismo nombre, o el `can()` con prefijo no "
        . 'acierta nunca y el botón se oculta sin decir por qué.'
    );

    expect(str_contains($js, '`${prefix}.${permission}`'))->toBeFalse(
        'FALLA: volvió el punto al composable de permisos. · FIX: el punto es el separador de los '
        . 'nombres de RUTA (central.roles.index), no de los permisos. Ver contextRoute().'
    );
});

it('los ejemplos del composable enseñan permisos que el paquete puede emitir', function () {
    // Un docblock es lo primero que copia quien escribe su propia vista. El de antes enseñaba
    // `can('roles.edit')`: ni el separador ni la acción existen — el paquete emite `update`, no
    // `edit`, porque `edit` era una de las dos pantallas que la v4 dejó de generar.
    preg_match_all("/can\w*\(\s*\[?\s*'([^']+)'/", composablePermisos(), $encontrados);

    $acciones = array_column(SubFeaturePermissions::routes('', 'roles'), 'permission');
    $vistas   = array_values(SubFeaturePermissions::viewElements('', 'roles'));
    $emitibles = array_merge($acciones, $vistas);

    expect($encontrados[1])->not->toBeEmpty('El docblock se quedó sin un solo ejemplo de uso.');

    foreach ($encontrados[1] as $ejemplo) {
        // El ejemplo puede llevar prefijo de contexto o no: se compara la parte sin prefijo.
        $sinPrefijo = preg_replace('/^(central|tenant(_\w+)?)_/', '', $ejemplo);

        expect(in_array($sinPrefijo, $emitibles, true))->toBeTrue(
            "FALLA: el ejemplo can('{$ejemplo}') usa un permiso que el paquete no emite. · FIX: los "
            . 'nombres válidos salen de SubFeaturePermissions — las seis acciones y sus cuatro '
            . 'permisos de vista. Un ejemplo inventado se copia y produce un botón oculto para '
            . "todo el mundo.\nEmitibles: " . implode(', ', $emitibles)
        );
    }
});
