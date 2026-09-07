<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El esquema que el paquete escribe, ejecutado de verdad.
 *
 * Hasta aquí, ninguna prueba había **corrido** una migración generada: se comprobaba que el archivo
 * existiera y, desde el chequeo de salida, que parseara. Con eso convivía un defecto que hacía
 * imposible usar el paquete: el stub traía `$table->id();` fijo **y** el generador inyectaba otro,
 * así que toda migración salía con la columna duplicada y moría al ejecutarse con
 * `duplicate column name: id`. Parseaba perfectamente.
 *
 * La lección es la de siempre —dos mitades que asumen cada una que la otra no lo hace—, y la única
 * prueba que la caza es la que ejecuta: por eso la primera de este archivo levanta la migración
 * sobre SQLite y mira la tabla resultante.
 */

/** Ejecuta el `up()` de un archivo de migración generado y devuelve la instancia. */
function correrMigracion(string $archivo): Migration
{
    $migracion = include $archivo;

    expect($migracion)->toBeInstanceOf(
        Migration::class,
        "El archivo {$archivo} debe devolver una migración anónima (`return new class extends Migration`)."
    );

    $migracion->up();

    return $migracion;
}

it('la migración generada se ejecuta y crea la tabla con la forma que promete', function () {
    // Mismo criterio que MigratePlanCommandTest: sin `pdo_sqlite` no hay dónde ejecutar. En CI está
    // en las ocho celdas, así que esta prueba —la única que ejecuta el esquema generado— corre
    // siempre allí, que es donde tiene que estar verde.
    if (! extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('pdo_sqlite no está disponible en este entorno.');
    }

    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $migraciones = $modulo->migrations();

    expect($migraciones)->toHaveCount(1, 'Una migración `_final` por tabla (R22b).');

    correrMigracion($migraciones[0]);

    expect(Schema::hasTable('invoices'))->toBeTrue(
        'La migración se ejecutó pero no creó la tabla. Si aquí falla con "duplicate column", el '
        . 'stub y el generador están escribiendo la misma columna: solo uno de los dos debe hacerlo.'
    );

    expect(Schema::hasColumn('invoices', 'id'))->toBeTrue();
    expect(Schema::hasColumn('invoices', 'deleted_at'))->toBeTrue(
        'el borrado lógico está en toda tabla generada, para que eliminar y restaurar '
        . 'estén siempre disponibles.'
    );
    expect(Schema::hasColumn('invoices', 'created_at'))->toBeTrue();
});

it('la clave primaria es ULID, no un autoincremental', function () {
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    $contenido = file_get_contents($modulo->migrations()[0]);

    expect(str_contains($contenido, "\$table->ulid('id')->primary();"))->toBeTrue(
        "la clave primaria es ULID. Un autoincremental es enumerable —con un id en la URL se "
        . "recorre la tabla entera probando números—, y en multitenant eso cruza inquilinos.\n"
        . "La migración dice:\n" . $contenido
    );

    expect(str_contains($contenido, '$table->id();'))->toBeFalse(
        'Y no queda ningún autoincremental: son dos formas distintas, no dos alternativas.'
    );
});

it('ninguna columna se declara dos veces', function () {
    // La regresión del defecto que hacía inejecutable toda migración generada. Se comprueba sobre
    // el texto además de ejecutando, porque el mensaje de error de SQLite nombra una columna y este
    // las nombra todas.
    $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

    preg_match_all('/\$table->(\w+)\(/', file_get_contents($modulo->migrations()[0]), $encontrados);

    $repetidas = array_keys(array_filter(
        array_count_values($encontrados[1]),
        static fn (int $veces): bool => $veces > 1,
    ));

    expect($repetidas)->toBe(
        [],
        'Estas llamadas aparecen más de una vez en la misma migración: '
        . implode(' · ', $repetidas)
        . ".\nSi son `id` o `timestamps`, el stub y el generador están escribiendo lo mismo: la "
        . 'columna sale duplicada y la migración no se puede ejecutar.'
    );
});

it('las claves foráneas apuntan a un ULID, no a un entero', function () {
    // Sin esto, la restricción no se puede crear: la PK es un ULID de 26 caracteres y la FK un
    // entero. Es el mismo par que la clave primaria, un escalón más abajo.
    $generador = new ReflectionClass(\Innodite\LaravelModuleMaker\Generators\Components\MigrationGenerator::class);
    $metodo    = $generador->getMethod('foreignIdColumn');
    $metodo->setAccessible(true);

    $instancia = $generador->newInstanceWithoutConstructor();

    $linea = $metodo->invoke($instancia, ['name' => 'customer_id', 'type' => 'foreignId']);

    expect(str_contains($linea, 'foreignUlid'))->toBeTrue(
        "la FK sigue el tipo de la PK a la que apunta. Se generó: {$linea}"
    );
    expect(str_contains($linea, 'foreignId('))->toBeFalse(
        'Un `foreignId` es un entero: contra una PK de tipo ULID la restricción ni se crea.'
    );
});

it('el módulo entero sigue coherente tras el cambio de esquema', function (ModuleMode $modo, ?string $contexto) {
    $this->generateModule('Invoice', $modo, $contexto)->assertCoherent();
})->with([
    'single-app'  => [ModuleMode::SingleApp, null],
    'multitenant' => [ModuleMode::Multitenant, 'central'],
]);
