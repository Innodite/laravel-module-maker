<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Generators\Components\SubFeatureSeederGenerator;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Innodite\LaravelModuleMaker\Support\SubFeaturePermissions;

/**
 * Las seis piezas, escritas por el generador y no por la prueba (R23).
 *
 * Hasta ahora el paquete emitía **una** —un seeder plano con el `run()` vacío— y las dos de esquema
 * que dejó la fase de migraciones. Aquí se comprueba lo que de verdad importa de un grupo de seis:
 * que estén las seis, que no sobre ninguna, y sobre todo que **se puedan instanciar**. Un
 * `use TraitQueNoExiste;` pasa el parser, pasa el chequeo de salida y revienta al instanciar: es
 * B21, y es la razón de que esta prueba cargue las clases en vez de leerlas.
 */

/** @return array<int, string> Las seis piezas de una subfuncionalidad, con su nombre de archivo. */
function piezasEsperadas(string $prefijo, string $modulo, string $subFeature): array
{
    return SeederNames::subFeaturePieces($prefijo, $modulo, $subFeature);
}

it('un módulo generado tiene las seis piezas, con los nombres de la convención', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $esperadas = array_map(
        static fn (string $pieza): string => "Database/Seeders/Invoice/{$pieza}.php",
        piezasEsperadas('', 'Invoice', 'Invoice')
    );

    $modulo->assertTreeHas($esperadas, 'Una subfuncionalidad con persistencia tiene seis piezas de seeder.');
});

it('ninguna de más: el seeder plano de antes ya no se escribe', function () {
    // Era el `{Modelo}Seeder.php` con un `//` dentro. Existía, el árbol lo mostraba, y no desplegaba
    // nada — y encima quedaba fuera del grupo de seis, así que nadie lo llamaba.
    //
    // Se mira **la carpeta de la subfuncionalidad**: los tres maestros del módulo son legítimos y
    // viven aparte, en `Application/`, porque no pertenecen a ninguna subfuncionalidad.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $seeders = array_values(array_filter(
        $modulo->tree(),
        static fn (string $ruta): bool => str_starts_with($ruta, 'Database/Seeders/Invoice/')
    ));

    $sobrantes = array_values(array_diff(
        $seeders,
        array_map(
            static fn (string $pieza): string => "Database/Seeders/Invoice/{$pieza}.php",
            piezasEsperadas('', 'Invoice', 'Invoice')
        )
    ));

    expect($sobrantes)->toBe([], "Sobra en Database/Seeders:\n  - " . implode("\n  - ", $sobrantes));
});

it('las seis se instancian de verdad, no solo parsean', function () {
    // La lección de B21: el parser acepta un `use` a un trait que nadie escribió, y el error aparece
    // en el despliegue. Cargarlas es la única forma de saber que el grupo está completo.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $piezas = piezasEsperadas('', 'Invoice', 'Invoice');

    cargarPiezas($modulo, 'Database/Seeders/Invoice', $piezas);

    foreach ($piezas as $pieza) {
        $fqcn = "Modules\\Invoice\\Database\\Seeders\\Invoice\\{$pieza}";

        expect(class_exists($fqcn) || trait_exists($fqcn))->toBeTrue(
            "PSR-4 no encuentra {$fqcn}: el archivo se llama {$pieza}.php pero la clase de dentro no."
        );

        if (class_exists($fqcn)) {
            expect(new $fqcn())->toBeInstanceOf($fqcn);
        }
    }
});

it('el seeder de permisos siembra exactamente los permisos que exigen las rutas', function () {
    // La novena pareja, contrastada sobre los archivos escritos y no sobre la clase que los calcula.
    // Cuando estos dos conjuntos se separaron, la pantalla generada cargaba sin un solo botón.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $seeder = $modulo->contents('Database/Seeders/Invoice/InvoiceInvoicePermissionsSeeder.php');

    foreach (SubFeaturePermissions::routes('', 'invoices') as $ruta) {
        expect($seeder)->toContain("'{$ruta['permission']}'");
    }

    foreach (SubFeaturePermissions::viewElements('', 'invoices') as $permiso) {
        expect($seeder)->toContain("'{$permiso}'");
    }
});

it('cada permiso del seeder llega con su descripción en español', function () {
    // Es lo único que ve quien asigna permisos a un rol: sin ella, esa pantalla es una lista de
    // claves indescifrables.
    $seeder = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Database/Seeders/Invoice/InvoiceInvoicePermissionsSeeder.php');

    expect($seeder)->toContain('Acceder a la pantalla de')
        ->and($seeder)->toContain('Sin este permiso,')
        ->and($seeder)->toContain("'module'      => 'Invoice - Invoice'");
});

it('en multitenant las piezas llevan el prefijo del contexto y su carpeta', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    $esperadas = array_map(
        static fn (string $pieza): string => "Database/Seeders/Central/Invoice/{$pieza}.php",
        piezasEsperadas('Central', 'Invoice', 'Invoice')
    );

    $modulo->assertTreeHas($esperadas, 'El contexto entra en la carpeta y en el nombre (R5 · R6).');
});

it('el seeder declara la conexión del contexto, la misma que el modelo', function () {
    // Un modelo leyendo de una base y su seeder sembrando en otra es lo que pasa cuando cada uno
    // calcula la conexión por su cuenta.
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    $stage = $modulo->contents('Database/Seeders/Central/Invoice/CentralInvoiceInvoiceStageSeeder.php');
    $model = $modulo->contents('Models/Central/Invoice/CentralInvoice.php');

    expect($stage)->toContain("protected ?string \$connection = 'central';")
        ->and($model)->toContain("protected \$connection = 'central';");
});

it('en single-app no declara conexión: no hay nada que conmutar', function () {
    $stage = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Database/Seeders/Invoice/InvoiceInvoiceStageSeeder.php');

    expect($stage)->toContain('protected ?string $connection = null;');
});

it('los seeders nombran la tabla que crea la migración', function () {
    // Si divergen, el seeder valida una tabla que no existe mientras la real queda sin comprobar.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $stage = $modulo->contents('Database/Seeders/Invoice/InvoiceInvoiceStageSeeder.php');

    expect($stage)->toContain("'invoices',");

    $migraciones = $modulo->migrations();

    expect($migraciones)->not->toBeEmpty();
    expect(file_get_contents($migraciones[0]))->toContain("'invoices'");
});

it('el trait de datos nace vacío, con su estructura', function () {
    // El paquete no conoce los datos del negocio. Rellenarlo con filas de ejemplo sería repetir el
    // defecto que esta fase cierra: una pieza que aparenta contenido.
    $data = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Database/Seeders/Invoice/InvoiceInvoiceData.php');

    expect($data)->toContain('protected function upsertCanonicalData(): void')
        ->and($data)->toContain('protected function seedCanonicalData(): void');

    // Solo el CÓDIGO: los dos docblocks llevan escrito el patrón para cuando haya filas que sembrar,
    // y esa explicación es lo que hace que las primeras se añadan bien.
    $codigo = soloCodigo($data);

    expect(str_contains($codigo, 'DB::connection($this->connection)->table'))->toBeFalse(
        'Los métodos nacen sin filas: el patrón va documentado, no ejecutado.'
    );
});

it('volver a generar sobre un módulo existente no pisa lo que se escribió a mano', function () {
    // Dentro de un seeder viven el paso propio que alguien añadió y las filas canónicas del negocio.
    // El camino que puede reescribirlas es el de añadir componentes a un módulo que ya existe, que
    // es como llega `add-entity`: `make-module` sobre un módulo existente se niega antes de empezar.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);
    $ruta   = $modulo->path('Database/Seeders/Invoice/InvoiceInvoiceStageSeeder.php');

    file_put_contents($ruta, str_replace(
        'public function run(): void',
        "public function miPasoPropio(): void {}\n\n    public function run(): void",
        file_get_contents($ruta)
    ));

    (new SubFeatureSeederGenerator('Invoice', $modulo->path(), true, [
        'name'          => 'Invoice',
        'subFeature'    => 'Invoice',
        'functionality' => 'invoices',
    ]))->generate();

    expect(str_contains(file_get_contents($ruta), 'miPasoPropio'))->toBeTrue(
        'Volver a generar no puede borrar lo escrito a mano dentro de un seeder.'
    );
});

it('el módulo con sus seis piezas sigue siendo coherente', function () {
    $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')->assertCoherent();
});
