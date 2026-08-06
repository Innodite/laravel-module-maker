<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Components\VueGenerator;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
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

it('encuentra los 32 stubs del paquete', function () {
    // La cuenta sube cuando el paquete aprende a generar una pieza nueva —las últimas son las tres
    // ejecutables de la subfuncionalidad, el trait de datos canónicos y los dos maestros de módulo,
    // todas de la fase 3— y esa subida se hace **a propósito, aquí**. Y baja cuando una pieza deja de generarse: en esta misma
    // fase salieron `route-api.stub` y `route-web.stub` —el camino de single-app, que escribía rutas
    // sin un solo permiso— porque ese modo pasó a usar el mismo bloque que los contextos.
    // Lo que esta prueba vigila es lo otro: que no reaparezcan las copias por
    // contexto que A4 borró, donde la copia le ganaba por prioridad al original corregido.
    expect(packageStubs())->toHaveCount(
        32,
        'El paquete lleva 32 stubs, una sola copia de cada uno. Si aparecen más sin haber añadido '
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

it('el marcador de rutas de tenant sigue en doble llave, porque debe sobrevivir', function () {
    // Este stub está huérfano (B14, se arregla en F-4), pero su marcador ya enseña la regla:
    // un marcador viaja hasta el archivo del proyecto, un placeholder muere al generar.
    $stub = File::get(dirname(__DIR__, 2) . '/stubs/contextual/route-tenant.stub');

    expect(str_contains($stub, '{{TENANT_ROUTES_END}}'))->toBeTrue(
        'Un marcador de inyección no es un placeholder: se escribe tal cual para que la siguiente '
        . 'ejecución encuentre dónde añadir rutas.'
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

    (new VueGenerator('UserManagement', $modulePath, false, 'Role', ['subFeature' => 'Role']))->generate();

    $index = File::get("{$modulePath}/resources/js/Pages/Role/RoleIndex.vue");

    expect(str_contains($index, "contextRoute('roles.index')"))->toBeTrue(
        'Aquí es donde B15 dolía de verdad: con el placeholder literal, la vista pedía la ruta '
        . "'{{ entityPlural }}.index' y la pantalla no cargaba nada."
    );
    expect(str_contains($index, 'RoleIndex'))->toBeTrue(
        'El nombre del componente se inyecta en el log de errores de la vista.'
    );
});
