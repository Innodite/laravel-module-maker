<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Components\VueGenerator;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El modo decide la FORMA: dónde cae cada archivo y cómo se llama.
 *
 * Hasta ahora lo decidía `contexts.json`, y de forma incondicional: una aplicación sin un solo
 * tenant generaba `Models/Central/CentralRole.php` — una carpeta que no separa nada y un prefijo
 * que no desambigua nada, porque no hay un segundo contexto del que distinguirlo.
 *
 * Estas pruebas fijan la diferencia entre modos sobre archivos generados de verdad, porque es
 * exactamente el tipo de cosa que se puede leer bien en el código y salir mal en el disco.
 */

it('en single-app la subfuncionalidad va directa bajo la capa, sin contexto ni prefijo', function () {
    $this->withMode(ModuleMode::SingleApp);

    $modulePath = $this->tempPath('Modules/UserManagement');
    File::ensureDirectoryExists($modulePath);

    (new VueGenerator('UserManagement', $modulePath, false, 'Role', [
        'subFeature' => 'Role',
        'context' => 'central',      // aunque se pase un contexto, en single-app no existe el eje
    ]))->generate();

    expect(File::isDirectory("{$modulePath}/Role/resources/js/Pages"))->toBeTrue(
        'en single-app la ruta es Role/resources/js/Pages/. Si aparece Central/ al final, '
        . 'ModuleMode::hasContextAxis() no está mandando sobre getContextFolder().'
    );

    expect(File::exists("{$modulePath}/Role/resources/js/Pages/RoleIndex.vue"))->toBeTrue(
        'sin eje de contexto no hay prefijo. "CentralRoleIndex.vue" en un proyecto sin tenants '
        . 'es ruido pegado al nombre de cada archivo de cada módulo.'
    );

    expect(File::exists("{$modulePath}/Role/resources/js/Pages/Central/CentralRoleIndex.vue"))->toBeFalse(
        'Y no debe existir la versión con contexto: son dos formas distintas, no dos alternativas.'
    );
});

it('en multitenant el contexto entra en la carpeta y en el nombre', function () {
    $this->withMode(ModuleMode::Multitenant);

    $modulePath = $this->tempPath('Modules/UserManagement');
    File::ensureDirectoryExists($modulePath);

    (new VueGenerator('UserManagement', $modulePath, false, 'Role', [
        'subFeature' => 'Role',
        'context' => 'central',
    ]))->generate();

    expect(File::exists("{$modulePath}/Role/resources/js/Pages/Central/CentralRoleIndex.vue"))->toBeTrue(
        'En multitenant sí hay dos contextos que separar, así que el contexto entra como ÚLTIMO '
        . 'tramo de la capa y como prefijo del nombre: Role/resources/js/Pages/Central/CentralRoleIndex.vue.'
    );
});

it('la carpeta de páginas va en minúscula', function () {
    $this->withMode(ModuleMode::SingleApp);

    $modulePath = $this->tempPath('Modules/UserManagement');
    File::ensureDirectoryExists($modulePath);

    (new VueGenerator('UserManagement', $modulePath, false, 'Role', ['subFeature' => 'Role']))->generate();

    expect(File::isDirectory("{$modulePath}/Role/resources"))->toBeTrue(
        '`resources` en minúscula. En Linux no es cosmético — el bundler distingue '
        . 'mayúsculas al resolver la ruta de la página, así que Resources/ rompe la vista.'
    );
    expect(File::isDirectory("{$modulePath}/Role/Resources"))->toBeFalse();
});

it('el modo responde si el modelo declara conexión, y son dos respuestas', function () {
    expect(ModuleMode::SingleApp->declaresModelConnection())->toBeFalse(
        'Una sola base de datos: no hay nada que conmutar.'
    );
    expect(ModuleMode::Multitenant->declaresModelConnection('central'))->toBeTrue(
        'La app central siempre declara su conexión.'
    );
    expect(ModuleMode::Multitenant->declaresModelConnection('tenant'))->toBeFalse(
        'El modelo del inquilino NO declara conexión: la conmuta el paquete de tenencia al '
        . 'identificar la ruta, y el aislamiento lo garantiza esa ruta. Nombrarla aquí ata el '
        . 'modelo a UN cliente, que es lo contrario de lo que protege.'
    );
});

it('el modo rechaza un contexto que no le corresponde', function () {
    expect(ModuleMode::SingleApp->supportsContext('central'))->toBeFalse(
        'Una aplicación única no tiene contextos. Aceptarlo la obliga a inventarse uno.'
    );
    expect(ModuleMode::SingleApp->supportsContext(null))->toBeTrue();

    // Los dos contextos del catálogo de fábrica, y solo esos: un contexto propio lo declara el
    // proyecto en SU contexts.json, y entonces `ContextOption` lo admite porque está declarado.
    expect(ModuleMode::Multitenant->supportsContext('central'))->toBeTrue();
    expect(ModuleMode::Multitenant->supportsContext('tenant'))->toBeTrue();

    expect(ModuleMode::Multitenant->supportsContext('tenant_acme'))->toBeFalse(
        'Un inquilino NOMBRADO no cabe: generar para uno produce la copia por cliente de lógica '
        . 'idéntica que el eje existe para evitar.'
    );
    expect(ModuleMode::Multitenant->supportsContext('tenant_shared'))->toBeFalse(
        'Se retiró con el modo de lógica por cliente: lo que los inquilinos comparten es el eje '
        . '`tenant` entero.'
    );
});
