<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Components\VueGenerator;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\RouteMarkers;
use Innodite\LaravelModuleMaker\Support\StubPlaceholder;

/**
 * Estas pruebas vigilan el motor de plantillas por los dos lados: que todos los stubs del
 * paquete hablen el delimitador nuevo, y que lo que sale de un generador real no lleve
 * placeholders dentro.
 *
 * La segunda mitad es la regresión de B15. Ahí el error no fue un stub mal escrito ni una
 * clave mal nombrada: el generador entregaba las claves ya envueltas y el trait las volvía a
 * envolver, así que **no se sustituía ninguna** y las cuatro vistas de cada módulo salían con
 * los placeholders literales —incluida la ruta que piden a Axios—. Ningún test lo vio porque
 * ninguno abría un archivo generado. Este lo abre.
 */

/** @return array<int, string> Rutas de los stubs del paquete. */
function packageStubs(): array
{
    return glob(dirname(__DIR__, 2) . '/stubs/contextual/*.stub') ?: [];
}

it('encuentra los 38 stubs del paquete', function () {
    // La cuenta sube cuando el paquete aprende a generar una pieza nueva —las últimas son las
    // cuatro del grupo de pruebas: el manifiesto, su base y las piezas de los nueve temas, de la
    // fase 4; antes fueron las tres
    // ejecutables de la subfuncionalidad, el trait de datos canónicos, los dos maestros de módulo y
    // el seeder de despliegue del proyecto, todas de la fase 3— y esa subida se hace **a propósito,
    // aquí**. Y baja cuando una pieza deja de generarse: en la fase 3
    // salieron `route-api.stub` y `route-web.stub` —el camino de single-app, que escribía rutas
    // sin un solo permiso— porque ese modo pasó a usar el mismo bloque que los contextos, y en la
    // fase 4 salieron `test.stub`, `test-unit.stub` y `test-support.stub`: los tres emitían
    // `assertTrue(true)` y ninguno estaba en los 9 temas del contrato. En la fase 5 salieron
    // `request-store.stub` y `request-update.stub`, que se fusionaron en un único `request.stub`
    // —la pieza es la misma, lo que cambia es la acción que valida—, y con ellos desapareció el
    // camino que escribía un Request genérico distinto según el contexto.
    // Lo que esta prueba vigila es lo otro: que no reaparezcan las copias por
    // contexto que A4 borró, donde la copia le ganaba por prioridad al original corregido.
    //
    // El título lleva la cuenta a propósito, y hay que moverlo con ella: en la fase 3 se quedó
    // atrás —decía «los 29» mientras exigía 27—, y un título que miente sobre lo que la prueba
    // exige se lee y se cree.
    expect(packageStubs())->toHaveCount(
        38,
        'El paquete lleva 38 stubs, una sola copia de cada uno. Si aparecen más sin haber añadido '
        . 'una pieza, alguien devolvió las copias por contexto; si aparecen menos, falta un stub.'
    );
});

it('ningún stub de PHP conserva un placeholder en el formato viejo', function () {
    $offenders = [];

    foreach (packageStubs() as $stub) {
        if (str_starts_with(basename($stub), 'vue-')) {
            continue;   // en un .vue la doble llave es de Vue; se comprueba aparte
        }

        preg_match_all(StubPlaceholder::legacyPattern(), File::get($stub), $matches);

        if ($matches[0] !== []) {
            $offenders[basename($stub)] = implode(' · ', array_unique($matches[0]));
        }
    }

    expect($offenders)->toBe(
        [],
        "Estos stubs siguen en {{ }} y ya no se resuelven:\n"
        . json_encode($offenders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        . "\nPásalos a {{{ clave }}}. Los marcadores de inyección ({{MARCADOR_END}}, sin espacios) NO se tocan."
    );
});

it('las interpolaciones de Vue sobreviven al cambio de delimitador', function () {
    $index = File::get(dirname(__DIR__, 2) . '/stubs/contextual/vue-index.stub');
    $show  = File::get(dirname(__DIR__, 2) . '/stubs/contextual/vue-show.stub');

    foreach (['{{ item.id }}', '{{ meta.total }}', '{{ p }}', '{{ error }}'] as $vueExpression) {
        expect(str_contains($index, $vueExpression))->toBeTrue(
            "La expresión {$vueExpression} es código Vue del usuario, no un placeholder. "
            . 'Convertirla a triple llave rompe la vista.'
        );
    }

    expect(str_contains($show, '{{ item.name }}'))->toBeTrue(
        'vue-show interpola el registro con doble llave: es Vue, no se toca.'
    );
});

it('las claves del paquete dentro de los stubs Vue están en triple llave', function () {
    foreach (glob(dirname(__DIR__, 2) . '/stubs/contextual/vue-*.stub') as $stub) {
        $content = File::get($stub);

        foreach (['subFeatureLabel', 'subFeaturePlural', 'vueComponentName'] as $key) {
            expect($content)->not->toMatch(
                '/(?<!\{)\{\{ ' . $key . ' \}\}(?!\})/',
                basename($stub) . ": la clave '{$key}' es del paquete y sigue en doble llave, "
                . 'así que ya no se resuelve. Pásala a {{{ ' . $key . ' }}}.'
            );
        }
    }
});

it('el marcador de rutas sigue en doble llave, porque debe sobrevivir al generar', function () {
    // Esta prueba leía `route-tenant.stub`, que estaba huérfano —ningún generador lo abría— y se
    // retiró en la fase 5 con el resto del flujo de rutas. La regla que enseñaba sigue viva, así
    // que se comprueba contra la pieza que hoy la decide: un **marcador** viaja hasta el archivo
    // del proyecto y sobrevive allí; un **placeholder** muere al generar.
    //
    // Por eso el marcador va en doble llave y sin espacios interiores: es lo que lo distingue del
    // `{{{ clave }}}` del paquete y de la interpolación `{{ variable }}` de Vue, y lo que hace que
    // el chequeo de salida no lo confunda con algo sin resolver.
    foreach ([
        ['central', 'web.php', '',           '// {{CENTRAL_ROUTES_END}}'],
        ['shared', 'tenant.php', '',         '// {{TENANT_SHARED_ROUTES_END}}'],
        ['tenant', 'tenant.php', 'clinic-one', '// {{TENANT_CLINIC_ONE_ROUTES_END}}'],
    ] as [$contexto, $archivo, $id, $esperado]) {
        expect(RouteMarkers::comment($contexto, $archivo, $id))->toBe(
            $esperado,
            "FALLA: el marcador de '{$contexto}' en {$archivo} cambió de forma. · FIX: estos "
            . 'marcadores ya están escritos en los routes/*.php de los proyectos instalados; '
            . 'cambiarlos deja huérfanos los archivos que los llevan, y la siguiente '
            . 'subfuncionalidad se colgará al final en vez de dentro de su grupo.'
        );
    }

    expect(StubPlaceholder::unresolvedPattern())->not->toBeEmpty();

    // Y la comprobación que cierra el círculo: el marcador NO se parece a un placeholder sin
    // resolver, o el chequeo de salida tumbaría todo archivo de rutas que el paquete escriba.
    preg_match_all(StubPlaceholder::unresolvedPattern(), RouteMarkers::comment('central', 'web.php'), $m);

    expect($m[0])->toBe(
        [],
        'FALLA: el chequeo de salida lee el marcador como un placeholder sin resolver. · FIX: el '
        . 'marcador va sin espacios interiores; con ellos, ningún archivo de rutas pasaría el '
        . 'chequeo y la generación fallaría siempre.'
    );
});

it('las cuatro vistas generadas no llevan un solo placeholder dentro', function () {
    $modulePath = $this->tempPath('Modules/UserManagement');
    File::ensureDirectoryExists($modulePath);

    // En single-app: sin eje de contexto, sin prefijo, y la subfuncionalidad como carpeta —
    // resources/js/Pages/Role/RoleIndex.vue, que es lo que describe el patrón.
    $this->withMode(ModuleMode::SingleApp);

    (new VueGenerator('UserManagement', $modulePath, false, 'Role', ['subFeature' => 'Role']))->generate();

    $views = glob("{$modulePath}/resources/js/Pages/Role/*.vue") ?: [];

    expect($views)->toHaveCount(
        4,
        'VueGenerator emite index, create, edit y show. Si no salen cuatro, el generador falló antes '
        . 'de escribir.'
    );

    foreach ($views as $view) {
        $content = File::get($view);

        preg_match_all(StubPlaceholder::unresolvedPattern(), $content, $matches);

        expect($matches[0])->toBe(
            [],
            basename($view) . ' salió con placeholders sin resolver: '
            . implode(' · ', array_unique($matches[0]))
            . "\nEs B15: comprueba que VueGenerator pase las claves DESNUDAS "
            . "(['entityLabel' => …], no ['{{{ entityLabel }}}' => …])."
        );
    }
});

it('la vista generada pide una ruta real, no el nombre del placeholder', function () {
    $modulePath = $this->tempPath('Modules/UserManagement');
    File::ensureDirectoryExists($modulePath);

    // En single-app: sin eje de contexto, sin prefijo, y la subfuncionalidad como carpeta —
    // resources/js/Pages/Role/RoleIndex.vue, que es lo que describe el patrón.
    $this->withMode(ModuleMode::SingleApp);

    // `functionality` es lo que el comando pasa siempre: es el prefijo del NOMBRE de las rutas, y
    // desde la fase 5 la vista lo lee del mismo sitio que el generador que las escribe.
    (new VueGenerator('UserManagement', $modulePath, false, 'Role', [
        'subFeature'    => 'Role',
        'functionality' => 'roles',
    ]))->generate();

    $index = File::get("{$modulePath}/resources/js/Pages/Role/RoleIndex.vue");

    expect(str_contains($index, "window.route('roles.list')"))->toBeTrue(
        'Aquí es donde B15 dolía de verdad: con el placeholder literal, la vista pedía la ruta '
        . "'{{ entityPlural }}.index' y la pantalla no cargaba nada.\n"
        . 'Y desde la fase 5 pide `list`, no `index`: son dos rutas distintas a propósito — `index` '
        . 'devuelve la pantalla por Inertia y `list` los datos. Confundirlas no da error, deja la '
        . 'tabla vacía para siempre.'
    );
    expect(str_contains($index, 'RoleIndex'))->toBeTrue(
        'El nombre del componente se inyecta en el log de errores de la vista.'
    );
});
