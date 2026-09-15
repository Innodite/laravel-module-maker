<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\PhpunitRunner;
use Innodite\LaravelModuleMaker\Support\LegacySharedTests;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El espejo de "grupo incompleto": un grupo que carga la forma de pruebas retirada desde v5.0.0.
 *
 * **Detección pura, y eso es lo que se prueba.** Las tres piezas de abajo comprueban que el fallo
 * aparece, que nombra qué encontró, y que — a diferencia de un fix automático — no toca un solo
 * archivo: `Shared/` sigue ahí después de correr el comando, porque un trait compartido no se puede
 * trasladar con seguridad sin leer sus aserciones y sus comentarios uno por uno.
 */

/** Un ejecutor que nunca llega a lanzarse: si algo se ejecuta con Shared/ presente, es el defecto. */
function ejecutorSiempreEnVerde(): PhpunitRunner
{
    return new class() extends PhpunitRunner
    {
        public function __construct()
        {
        }

        public function ejecutar(string $archivo, ?string $filtro = null): array
        {
            return ['ok' => true, 'salida' => ''];
        }
    };
}

/**
 * Genera un módulo multitenant real, en `base_path('Modules')` — el mismo sitio hardcodeado en
 * `TestCommand::carpetaDelGrupo()` — y devuelve la carpeta del contexto 'central' ya escrita.
 */
function moduloMultitenantConGrupo(string $nombre = 'Invoice'): array
{
    $raiz = base_path('Modules');

    File::deleteDirectory($raiz);

    config()->set('make-module.module_path', $raiz);
    config()->set('make-module.mode', ModuleMode::Multitenant->value);

    Artisan::call('innodite:make-module', [
        'name'             => $nombre,
        '--context'        => 'central',
        '--no-interaction' => true,
    ]);

    return [$nombre, "{$raiz}/{$nombre}/{$nombre}/Tests/Feature/Central"];
}

afterEach(function (): void {
    File::deleteDirectory(base_path('Modules'));
});

it('un grupo multitenant sin Shared/ ni trait incorporado pasa la comprobación', function () {
    [$modulo] = moduloMultitenantConGrupo();

    $this->app->instance(PhpunitRunner::class, ejecutorSiempreEnVerde());

    $this->artisan('innodite:test', [
        'module'     => $modulo,
        'subfeature' => $modulo,
        '--context'  => 'central',
    ])->assertSuccessful();
});

it('un Shared/ heredado hace fallar innodite:test, sin tocar nada', function () {
    [$modulo, $grupo] = moduloMultitenantConGrupo();

    $carpetaShared = dirname($grupo) . '/Shared';

    File::makeDirectory($carpetaShared, 0755, true);
    File::put(
        "{$carpetaShared}/CentralInvoiceHttpTests.php",
        "<?php\n\ntrait CentralInvoiceHttpTests\n{\n}\n"
    );

    $this->app->instance(PhpunitRunner::class, ejecutorSiempreEnVerde());

    $codigo = Artisan::call('innodite:test', [
        'module'     => $modulo,
        'subfeature' => $modulo,
        '--context'  => 'central',
    ]);
    $salida = Artisan::output();

    expect($codigo)->not->toBe(0);
    expect($salida)->toContain('Shared');
    expect($salida)->toContain('v5.0.0');
    expect($salida)->toContain('las-pruebas.md');

    // Detección pura: nada se movió ni se borró.
    expect(File::isDirectory($carpetaShared))->toBeTrue();
    expect(File::isDirectory($grupo))->toBeTrue();
});

it('una incorporación use <Trait> dentro de una pieza hace fallar aunque no exista Shared/', function () {
    [$modulo, $grupo] = moduloMultitenantConGrupo();

    $pieza = "{$grupo}/CentralInvoiceScaffoldTest.php";

    $firma = "final class CentralInvoiceScaffoldTest extends CentralInvoiceTestCase\n{\n";

    $contenido = File::get($pieza);

    // La prueba asume la firma exacta que escribe test-scaffold.stub; si el stub cambia de forma,
    // esta aserción tiene que actualizar la firma que busca.
    expect($contenido)->toContain($firma);

    File::put($pieza, str_replace($firma, $firma . "    use CentralInvoiceHttpTests;\n\n", $contenido));

    $this->app->instance(PhpunitRunner::class, ejecutorSiempreEnVerde());

    $codigo = Artisan::call('innodite:test', [
        'module'     => $modulo,
        'subfeature' => $modulo,
        '--context'  => 'central',
    ]);
    $salida = Artisan::output();

    expect($codigo)->not->toBe(0);
    expect($salida)->toContain('CentralInvoiceScaffoldTest.php');
    expect($salida)->toContain('CentralInvoiceHttpTests');

    // Ni siquiera aquí se toca el archivo que disparó el fallo.
    expect(File::get($pieza))->toContain('use CentralInvoiceHttpTests;');
});

it('en aplicación única (sin eje de contexto) no hay nada que comprobar', function () {
    $raiz = base_path('Modules');

    File::deleteDirectory($raiz);

    config()->set('make-module.module_path', $raiz);
    config()->set('make-module.mode', ModuleMode::SingleApp->value);

    Artisan::call('innodite:make-module', [
        'name'             => 'Ledger',
        '--no-interaction' => true,
    ]);

    $this->app->instance(PhpunitRunner::class, ejecutorSiempreEnVerde());

    $this->artisan('innodite:test', ['module' => 'Ledger', 'subfeature' => 'Ledger'])
        ->assertSuccessful();
});

/**
 * TASK-004 de esta misma entrega: confirmación de regresión, no corrección. `TestGenerator` ya no
 * produce `Shared/` desde v5.0.0 — esto lo comprueba de punta a punta, en los dos puntos de entrada
 * (`make-module` y `add-entity`) y en los dos contextos, para poder afirmarlo en el CHANGELOG.
 */
it('make-module y add-entity generan sin Shared/ ni incorporación use, en central y en tenant', function () {
    foreach (['central' => 'Central', 'tenant' => 'Tenant'] as $contexto => $prefijo) {
        $nombreModulo = "Invoice{$prefijo}";

        $modulo = $this->generateModule($nombreModulo, ModuleMode::Multitenant, $contexto);

        $codigoEntidad = Artisan::call('innodite:add-entity', [
            'module'           => $nombreModulo,
            'entity'           => 'Payment',
            '--context'        => $contexto,
            '--no-interaction' => true,
        ]);

        expect($codigoEntidad)->toBe(
            0,
            "FALLA: add-entity falló en el contexto '{$contexto}'.\n" . Artisan::output()
        );

        $grupos = [
            "make-module ({$contexto})" => "{$modulo->path}/{$nombreModulo}/Tests/Feature/{$prefijo}",
            "add-entity ({$contexto})"  => "{$modulo->path}/Payment/Tests/Feature/{$prefijo}",
        ];

        foreach ($grupos as $etiqueta => $grupo) {
            expect(File::isDirectory($grupo))->toBeTrue(
                "FALLA: {$etiqueta} no escribió su grupo de pruebas en {$grupo}."
            );
            expect(LegacySharedTests::tieneCarpetaCompartida($grupo))->toBeFalse(
                "FALLA: {$etiqueta} dejó una carpeta Shared/ junto a {$grupo}."
            );
            expect(LegacySharedTests::incorporacionesDeTrait($grupo))->toBe(
                [],
                "FALLA: {$etiqueta} incorporó un trait con use dentro de una pieza — {$grupo}."
            );
        }
    }
});
