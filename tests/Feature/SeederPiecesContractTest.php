<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\GeneratedFileCheck;
use Innodite\LaravelModuleMaker\Support\SubFeaturePermissions;

/**
 * Las tres piezas ejecutables, con contenido de verdad (B3 · R23 · R24).
 *
 * El paquete emitía **un** seeder con un `run()` vacío y un `//` dentro: la pieza existía, el árbol
 * la mostraba, y no desplegaba nada. Las pruebas por existencia lo daban por bueno — por eso estas
 * miran el contenido y lo contrastan con lo que la norma exige de cada pieza.
 *
 * Y hay una diferencia que ninguna prueba de estilo alcanza y que aquí es la principal: **el seeder
 * de producción no puede borrar**. No basta con que responda «no» al modo destructivo; es que la
 * pregunta no debe estar escrita en ese archivo.
 */

/** Resuelve un stub con valores realistas, como haría el generador. */
function renderizarStub(string $stub, array $extra = []): string
{
    $valores = array_merge([
        'namespace'          => 'Modules\\Invoice\\Database\\Seeders\\Invoice',
        'subFeature'         => 'Invoice',
        'migrationsListTrait' => 'InvoiceInvoiceMigrationsList',
        'inlineAltersTrait'  => 'InvoiceInvoiceInlineAlters',
        'dataTrait'          => 'InvoiceInvoiceData',
        'permissionsSeeder'  => 'InvoiceInvoicePermissionsSeeder',
        'connection'         => 'null',
        'tables'             => "\n        'invoices',\n    ",
        'moduleLabel'        => 'Invoice - Invoice',
        'permissions'        => permisosComoArrayPhp(),
    ], $extra);

    $contenido = File::get(dirname(__DIR__, 2) . "/stubs/contextual/{$stub}");

    foreach ($valores as $clave => $valor) {
        $contenido = str_replace('{{{ ' . $clave . ' }}}', (string) $valor, $contenido);
    }

    return $contenido;
}

/**
 * Los permisos, escritos como los escribirá el generador — desde la fuente única y no a mano.
 *
 * Que la prueba los tome de `SubFeaturePermissions` es a propósito: si esa clase cambia de forma, el
 * stub que la consume tiene que seguir aceptando lo que entrega.
 */
function permisosComoArrayPhp(): string
{
    $lineas = [];

    foreach (SubFeaturePermissions::permissions('', 'invoice', 'Invoice', 'Invoice') as $permiso) {
        $lineas[] = "\n        [\n"
            . "            'name'        => '{$permiso['name']}',\n"
            . "            'description' => '" . str_replace("'", "\\'", $permiso['description']) . "',\n"
            . "            'module'      => '{$permiso['module']}',\n"
            . '        ],';
    }

    return implode('', $lineas) . "\n    ";
}

/** Escribe el archivo resuelto donde su namespace dice que vive, y lo pasa por el chequeo de salida. */
function pasaElChequeoDeSalida(string $contenido, string $clase, string $tempBase): void
{
    $carpeta = "{$tempBase}/Modules/Invoice/Database/Seeders/Invoice";

    File::ensureDirectoryExists($carpeta);

    $ruta = "{$carpeta}/{$clase}.php";

    File::put($ruta, $contenido);

    GeneratedFileCheck::assertWritable($ruta, $contenido);
}

it('las tres piezas se resuelven en PHP válido que el chequeo de salida acepta', function () {
    // El mismo criterio que se aplica a todo lo que el paquete escribe: nada sin resolver, PHP que
    // parsea, y el namespace espejando su carpeta.
    $piezas = [
        'stage-seeder.stub'       => 'InvoiceInvoiceStageSeeder',
        'production-seeder.stub'  => 'InvoiceInvoiceProductionSeeder',
        'permissions-seeder.stub' => 'InvoiceInvoicePermissionsSeeder',
    ];

    foreach ($piezas as $stub => $clase) {
        $contenido = renderizarStub($stub, ['seederName' => $clase]);

        pasaElChequeoDeSalida($contenido, $clase, $this->tempBase);
    }
})->throwsNoExceptions();

it('los dos ejecutables llaman a runMigrations y a runInlineAlters', function (string $stub, string $clase) {
    // Los dos métodos que la fase de migraciones dejó escritos **y sin invocador**: el trait existía,
    // el método estaba, y nadie lo llamaba nunca. El seeder es el vehículo del despliegue (R22), así
    // que es aquí donde el esquema se aplica.
    $contenido = renderizarStub($stub, ['seederName' => $clase]);

    expect($contenido)->toContain('$this->runMigrations()')
        ->and($contenido)->toContain('$this->runInlineAlters()');
})->with([
    ['stage-seeder.stub', 'InvoiceInvoiceStageSeeder'],
    ['production-seeder.stub', 'InvoiceInvoiceProductionSeeder'],
]);

it('las tres piezas acumulan los fallos y los reportan al cerrar', function (string $stub, string $clase) {
    $contenido = renderizarStub($stub, ['seederName' => $clase]);

    expect($contenido)->toContain('$this->safe(')
        ->and($contenido)->toContain('$this->reportErrors()');
})->with([
    ['stage-seeder.stub', 'InvoiceInvoiceStageSeeder'],
    ['production-seeder.stub', 'InvoiceInvoiceProductionSeeder'],
    ['permissions-seeder.stub', 'InvoiceInvoicePermissionsSeeder'],
]);

it('el de producción no tiene escrita una sola operación que borre', function () {
    // Lo que se comprueba no es que responda «no» al modo destructivo, sino que la pregunta no esté
    // en el archivo: exportar SEEDER_DESTRUCTIVE en el servidor no puede habilitar algo que no
    // existe. Es la diferencia entre una rama del `if` y otra estrategia.
    $codigo = soloCodigo(
        renderizarStub('production-seeder.stub', ['seederName' => 'InvoiceInvoiceProductionSeeder'])
    );

    $prohibidos = [
        'truncate(', 'ResolvesSeederDestructiveMode', 'isDestructive', 'SEEDER_DESTRUCTIVE',
        'FOREIGN_KEY_CHECKS', '->delete(', 'dropIfExists',
    ];

    foreach ($prohibidos as $prohibido) {
        expect(str_contains($codigo, $prohibido))->toBeFalse(
            "El código del seeder de producción no puede contener '{$prohibido}'."
        );
    }
});

it('el de producción fuerza los permisos a no destructivo', function () {
    $contenido = renderizarStub('production-seeder.stub', ['seederName' => 'InvoiceInvoiceProductionSeeder']);

    expect($contenido)->toContain("'destructive' => false");
});

it('el de stage solo vacía tablas si se lo pidieron', function () {
    // Sin la señal se comporta igual que el de producción. Que el truncado esté dentro de la rama
    // destructiva —y no antes, ni en paralelo— es toda la garantía.
    $contenido = renderizarStub('stage-seeder.stub', ['seederName' => 'InvoiceInvoiceStageSeeder']);

    $rama = substr(
        $contenido,
        (int) strpos($contenido, 'if ($this->isDestructive())'),
        (int) strpos($contenido, '$this->safe(\'seedPermissions\'') - (int) strpos($contenido, 'if ($this->isDestructive())')
    );

    expect($rama)->toContain("safe('truncateTables'");

    $antesDelIf = substr($contenido, 0, (int) strpos($contenido, 'if ($this->isDestructive())'));

    expect(str_contains($antesDelIf, "safe('truncateTables'"))->toBeFalse(
        'Un truncado fuera de la rama destructiva vacía la base de datos de cualquiera que ejecute '
        . 'el seeder de stage sin pedirlo.'
    );
});

it('el de stage propaga el modo a los permisos, sin forzarlo', function () {
    $contenido = renderizarStub('stage-seeder.stub', ['seederName' => 'InvoiceInvoiceStageSeeder']);

    expect($contenido)->toContain("'destructive' => \$this->isDestructive()");
});

it('el de permisos nace con la firma no destructiva de la norma', function () {
    // R24: sin pedirlo, nada se borra — y lo que de verdad se pierde al borrar y recrear son las
    // asignaciones a roles hechas a mano.
    $contenido = renderizarStub('permissions-seeder.stub', ['seederName' => 'InvoiceInvoicePermissionsSeeder']);

    expect($contenido)->toContain('public function run(bool $destructive = false): void')
        ->and($contenido)->toContain('updateOrInsert');
});

it('el de permisos borra por nombre exacto, nunca por prefijo', function () {
    // `invoice_` alcanza también a `invoice_lines_index`, que es de otra subfuncionalidad y de otro
    // seeder: un borrado por prefijo se lleva por delante permisos que no son suyos.
    $contenido = renderizarStub('permissions-seeder.stub', ['seederName' => 'InvoiceInvoicePermissionsSeeder']);

    expect($contenido)->toContain('whereIn(\'name\', $nombres)');

    expect(str_contains($contenido, "'like'"))->toBeFalse(
        'Borrar permisos por prefijo alcanza a los de otras subfuncionalidades.'
    );
});

it('el de permisos lleva dentro los diez permisos de la fuente única, con su descripción', function () {
    // La lista no se calcula aquí: la entrega `SubFeaturePermissions`, la misma que decide el
    // `middleware()` de cada ruta y el `can()` de cada botón. Un segundo cálculo es la novena pareja.
    $contenido = renderizarStub('permissions-seeder.stub', ['seederName' => 'InvoiceInvoicePermissionsSeeder']);

    $esperados = SubFeaturePermissions::permissions('', 'invoice', 'Invoice', 'Invoice');

    expect($esperados)->toHaveCount(10);

    foreach ($esperados as $permiso) {
        expect($contenido)->toContain("'{$permiso['name']}'");
    }

    expect($contenido)->toContain('Registrar Invoice.');   // la descripción en español, dentro
});

it('el de permisos no revienta donde no hay tabla de permisos', function () {
    // El paquete es público: un proyecto puede no tener instalado el paquete de permisos todavía.
    // Que el seeder avise y siga es la diferencia entre un despliegue con una advertencia y uno roto.
    $contenido = renderizarStub('permissions-seeder.stub', ['seederName' => 'InvoiceInvoicePermissionsSeeder']);

    expect($contenido)->toContain("hasTable('permissions')");

    expect(str_contains($contenido, 'use Spatie\\'))->toBeFalse(
        'Se escribe contra las tablas, no contra las clases de un paquete que puede no estar.'
    );
});

it('el de permisos entrega la clave del módulo cuando la tabla no la rellena sola', function () {
    // Aquí se detenía el despliegue entero, y en el primer módulo que se desplegara: una columna de
    // texto no tiene valor por defecto, así que insertar sin `id` devuelve «Field 'id' doesn't have
    // a default value». El seeder de permisos es de los primeros en correr, de modo que el proyecto
    // no llegaba a crear una sola tabla.
    //
    // `modules` es del proyecto, no del paquete: se mira su esquema, no el modo declarado en la
    // configuración —que describe las tablas que el paquete genera, y esta no lo es—.
    $contenido = renderizarStub('permissions-seeder.stub', ['seederName' => 'InvoiceInvoicePermissionsSeeder']);

    expect($contenido)->toContain("getColumnType('modules', 'id')")
        ->and($contenido)->toContain("\$fila['id'] = (string) Str::ulid();")
        ->and($contenido)->toContain('use Illuminate\\Support\\Str;');

    // Y el valor leído se devuelve tal cual. `(int)` sobre un ULID da 0, y ese 0 acabaría escrito
    // como el módulo de cada permiso: todos agrupados bajo un módulo que no existe.
    expect(str_contains($contenido, '(int) $id'))->toBeFalse(
        'Un ULID casteado a entero es 0, y ese 0 acaba escrito en cada permiso.'
    );
});
