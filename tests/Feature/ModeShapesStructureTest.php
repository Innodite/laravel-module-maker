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
 * que no desambigua nada, porque no hay un segundo contexto del que distinguirlo (C5 · R6).
 *
 * Estas pruebas fijan la diferencia entre modos sobre archivos generados de verdad, porque es
 * exactamente el tipo de cosa que se puede leer bien en el código y salir mal en el disco.
 */

it('en single-app la subfuncionalidad va directa bajo la capa, sin contexto ni prefijo', function () {
    $this->withMode(ModuleMode::SingleApp);

    $modulePath = $this->tempPath('Modules/UserManagement');
    File::ensureDirectoryExists($modulePath);

    (new VueGenerator('UserManagement', $modulePath, false, 'Role', [
        'entity'  => 'Role',
        'context' => 'central',      // aunque se pase un contexto, en single-app no existe el eje
    ]))->generate();

    expect(File::isDirectory("{$modulePath}/resources/js/Pages/Role"))->toBeTrue(
        'R5: en single-app la ruta es resources/js/Pages/Role/. Si aparece Central/ en medio, '
        . 'ModuleMode::hasContextAxis() no está mandando sobre getContextFolder().'
    );

    expect(File::exists("{$modulePath}/resources/js/Pages/Role/RoleIndex.vue"))->toBeTrue(
        'R6: sin eje de contexto no hay prefijo. "CentralRoleIndex.vue" en un proyecto sin tenants '
        . 'es ruido pegado al nombre de cada archivo de cada módulo.'
    );

    expect(File::exists("{$modulePath}/resources/js/Pages/Central/Role/CentralRoleIndex.vue"))->toBeFalse(
        'Y no debe existir la versión con contexto: son dos formas distintas, no dos alternativas.'
    );
});

it('en multitenant el contexto entra en la carpeta y en el nombre', function () {
    $this->withMode(ModuleMode::MultitenantPerTenant);

    $modulePath = $this->tempPath('Modules/UserManagement');
    File::ensureDirectoryExists($modulePath);

    (new VueGenerator('UserManagement', $modulePath, false, 'Role', [
        'entity'  => 'Role',
        'context' => 'central',
    ]))->generate();

    expect(File::exists("{$modulePath}/resources/js/Pages/Central/Role/CentralRoleIndex.vue"))->toBeTrue(
        'En multitenant sí hay dos contextos que separar, así que la carpeta y el prefijo llevan '
        . 'el contexto: resources/js/Pages/Central/Role/CentralRoleIndex.vue.'
    );
});

it('la carpeta de páginas va en minúscula', function () {
    $this->withMode(ModuleMode::SingleApp);

    $modulePath = $this->tempPath('Modules/UserManagement');
    File::ensureDirectoryExists($modulePath);

    (new VueGenerator('UserManagement', $modulePath, false, 'Role', ['entity' => 'Role']))->generate();

    expect(File::isDirectory("{$modulePath}/resources"))->toBeTrue(
        'B11 · R5: `resources` en minúscula. En Linux no es cosmético — el bundler distingue '
        . 'mayúsculas al resolver la ruta de la página, así que Resources/ rompe la vista.'
    );
    expect(File::isDirectory("{$modulePath}/Resources"))->toBeFalse();
});

it('el modo responde si el modelo declara conexión, y son tres respuestas distintas', function () {
    expect(ModuleMode::SingleApp->declaresModelConnection())->toBeFalse(
        'Una sola base de datos: no hay nada que conmutar.'
    );
    expect(ModuleMode::MultitenantShared->declaresModelConnection('central'))->toBeTrue(
        'La app central siempre declara su conexión (R7).'
    );
    expect(ModuleMode::MultitenantShared->declaresModelConnection('tenant_shared'))->toBeFalse(
        'Un tenant que hace lo mismo que los demás NO declara conexión: la conmuta stancl al '
        . 'inicializar el contexto y el aislamiento lo garantiza la ruta. Nombrarla aquí ataría el '
        . 'modelo a un solo tenant, que es lo contrario de lo que protege.'
    );
    expect(ModuleMode::MultitenantPerTenant->declaresModelConnection('tenant'))->toBeTrue(
        'Un tenant con lógica propia sí declara la suya.'
    );
});

it('el modo rechaza un contexto que no le corresponde', function () {
    expect(ModuleMode::SingleApp->supportsContext('central'))->toBeFalse(
        'Una aplicación única no tiene contextos. Aceptarlo la obliga a inventarse uno (C2).'
    );
    expect(ModuleMode::SingleApp->supportsContext(null))->toBeTrue();

    expect(ModuleMode::MultitenantShared->supportsContext('tenant'))->toBeFalse(
        'En el modo de tenants iguales no hay tenants nombrados: generar para uno produce justo lo '
        . 'que ese modo existe para evitar — una copia por tenant de lógica idéntica.'
    );
    expect(ModuleMode::MultitenantShared->supportsContext('tenant_shared'))->toBeTrue();
    expect(ModuleMode::MultitenantPerTenant->supportsContext('tenant'))->toBeTrue();
});
