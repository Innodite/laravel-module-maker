<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Generators\Components\SubFeatureSeederGenerator;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Innodite\LaravelModuleMaker\Support\SubFeaturePermissions;

/**
 * Las seis piezas, escritas por el generador y no por la prueba.
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
        static fn (string $pieza): string => "Invoice/Database/Seeders/{$pieza}.php",
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
            static fn (string $pieza): string => "Invoice/Database/Seeders/{$pieza}.php",
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

    $seeder = $modulo->contents('Invoice/Database/Seeders/InvoiceInvoicePermissionsSeeder.php');

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
        ->contents('Invoice/Database/Seeders/InvoiceInvoicePermissionsSeeder.php');

    expect($seeder)->toContain('Acceder a la pantalla de')
        ->and($seeder)->toContain('Sin este permiso,')
        ->and($seeder)->toContain("'module'      => 'Invoice - Invoice'");
});

it('en multitenant las piezas llevan el prefijo del contexto y su carpeta', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::Multitenant, 'central');

    $esperadas = array_map(
        static fn (string $pieza): string => "Invoice/Database/Seeders/Central/{$pieza}.php",
        piezasEsperadas('Central', 'Invoice', 'Invoice')
    );

    $modulo->assertTreeHas($esperadas, 'El contexto entra en la carpeta y en el nombre.');
});

it('el seeder declara la conexión del contexto, la misma que el modelo', function () {
    // Un modelo leyendo de una base y su seeder sembrando en otra es lo que pasa cuando cada uno
    // calcula la conexión por su cuenta.
    $modulo = $this->generateModule('Invoice', ModuleMode::Multitenant, 'central');

    $stage = $modulo->contents('Invoice/Database/Seeders/Central/CentralInvoiceInvoiceStageSeeder.php');
    $model = $modulo->contents('Invoice/Models/Central/CentralInvoice.php');

    expect($stage)->toContain("protected ?string \$connection = 'central';")
        ->and($model)->toContain("protected \$connection = 'central';");
});

it('en single-app no declara conexión: no hay nada que conmutar', function () {
    $stage = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Invoice/Database/Seeders/InvoiceInvoiceStageSeeder.php');

    expect($stage)->toContain('protected ?string $connection = null;');
});

it('los seeders nombran la tabla que crea la migración', function () {
    // Si divergen, el seeder valida una tabla que no existe mientras la real queda sin comprobar.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $stage = $modulo->contents('Invoice/Database/Seeders/InvoiceInvoiceStageSeeder.php');

    expect($stage)->toContain("'invoices',");

    $migraciones = $modulo->migrations();

    expect($migraciones)->not->toBeEmpty();
    expect(file_get_contents($migraciones[0]))->toContain("'invoices'");
});

it('el trait de datos nace vacío, con su estructura', function () {
    // El paquete no conoce los datos del negocio. Rellenarlo con filas de ejemplo sería repetir el
    // defecto que esta fase cierra: una pieza que aparenta contenido.
    $data = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Invoice/Database/Seeders/InvoiceInvoiceData.php');

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
    $ruta   = $modulo->path('Invoice/Database/Seeders/InvoiceInvoiceStageSeeder.php');

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

// ─── La pieza de producción, por dentro ────────────────────────────────────────────────────────
//
// Que el archivo SE GENERE ya se comprueba arriba, dos veces: en el árbol de las seis piezas y al
// instanciarlas. Lo que faltaba era mirar QUÉ LLEVA DENTRO, y es la pieza donde eso importa más:
// corre contra la base de un cliente en producción, y su docblock promete cuatro cosas que hasta
// aquí nadie vigilaba. Perder cualquiera de ellas dejaba la suite en verde y el fallo aparecía en
// los datos de alguien.

it('la pieza de producción no trae una sola operación que borre', function () {
    // El docblock lo promete por escrito —«truncar, delete masivo o drop»— y por eso hay que mirar
    // SOLO el código: las tres palabras están en el comentario que dice que no se usan.
    $codigo = soloCodigo(
        $this->generateModule('Invoice', ModuleMode::SingleApp)
            ->contents('Invoice/Database/Seeders/InvoiceInvoiceProductionSeeder.php')
    );

    foreach (['truncate', '->delete(', 'dropIfExists', 'Schema::drop'] as $destructiva) {
        expect(str_contains($codigo, $destructiva))->toBeFalse(
            "Producción no borra nada, y aquí aparece `{$destructiva}`. Si hace falta reconstruir, "
            . 'eso es stage con su modo destructivo, no este archivo.'
        );
    }
});

it('la pieza de producción no conoce el modo destructivo: no hay nada que habilitar', function () {
    // La diferencia con stage no es responder «no» a la pregunta: es que la pregunta no está. Sin
    // el trait, exportar SEEDER_DESTRUCTIVE=true en el servidor no enciende nada en este archivo.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $produccion = soloCodigo($modulo->contents('Invoice/Database/Seeders/InvoiceInvoiceProductionSeeder.php'));
    $stage      = soloCodigo($modulo->contents('Invoice/Database/Seeders/InvoiceInvoiceStageSeeder.php'));

    expect(str_contains($produccion, 'ResolvesSeederDestructiveMode'))->toBeFalse(
        'Producción no usa el trait del modo destructivo: no hay modo que resolver.'
    );
    expect(str_contains($produccion, 'isDestructive()'))->toBeFalse(
        'Producción no pregunta por el modo destructivo en ningún sitio.'
    );

    // Y el contraste, que es lo que hace que la prueba signifique algo: stage SÍ lo trae.
    expect(str_contains($stage, 'ResolvesSeederDestructiveMode'))->toBeTrue(
        'Si stage tampoco lo trae, el que está mal es stage: el reset opt-in vive ahí.'
    );

    // Los permisos bajan con el borrado apagado, y apagado a mano: no heredado de una variable.
    expect($produccion)->toContain("'destructive' => false");
});

it('la pieza de producción mezcla los datos, no los siembra desde cero', function () {
    // Las dos mitades del trait Data: `upsert…` respeta lo que ya está, `seed…` asume base vacía.
    // Producción solo puede usar la primera.
    $codigo = soloCodigo(
        $this->generateModule('Invoice', ModuleMode::SingleApp)
            ->contents('Invoice/Database/Seeders/InvoiceInvoiceProductionSeeder.php')
    );

    expect(str_contains($codigo, 'upsertCanonicalData'))->toBeTrue(
        'Producción mezcla los datos canónicos con upsert: los que ya están se actualizan.'
    );
    expect(str_contains($codigo, 'seedCanonicalData'))->toBeFalse(
        'El sembrado desde cero es de stage. Aquí borraría el trabajo del cliente.'
    );
});

it('la pieza de producción comprueba las tablas después de migrar', function () {
    // Descubrir aquí que una migración no se aplicó es barato; descubrirlo en la primera consulta
    // de un usuario, no.
    $codigo = soloCodigo(
        $this->generateModule('Invoice', ModuleMode::SingleApp)
            ->contents('Invoice/Database/Seeders/InvoiceInvoiceProductionSeeder.php')
    );

    expect($codigo)->toContain('validateTables')
        ->and($codigo)->toContain('hasTable')
        ->and($codigo)->toContain("'invoices',");
});

it('la pieza de producción declara la conexión de su contexto, igual que stage', function () {
    // La simetría que faltaba: la conexión de stage estaba vigilada y la de producción no, siendo
    // la que corre contra la base del cliente. Un seeder sembrando en la base que no era es el
    // fallo que esta línea existe para impedir.
    $produccion = $this->generateModule('Invoice', ModuleMode::Multitenant, 'central')
        ->contents('Invoice/Database/Seeders/Central/CentralInvoiceInvoiceProductionSeeder.php');

    expect($produccion)->toContain("protected ?string \$connection = 'central';");
});

it('en single-app la pieza de producción tampoco declara conexión', function () {
    $produccion = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Invoice/Database/Seeders/InvoiceInvoiceProductionSeeder.php');

    expect($produccion)->toContain('protected ?string $connection = null;');
});

it('el módulo con sus seis piezas sigue siendo coherente', function () {
    $this->generateModule('Invoice', ModuleMode::Multitenant, 'central')->assertCoherent();
});
