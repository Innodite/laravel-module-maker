<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\PrimaryKeyMode;

/**
 * La clave primaria la decide la configuración, y las cuatro respuestas van juntas.
 *
 * **Por qué existe este archivo.** El paquete generaba ULID siempre. Al hacerlo configurable, el
 * modo nuevo entra en el sitio exacto donde este paquete lleva cinco fases encontrando defectos: una
 * decisión que **cuatro piezas distintas** tienen que responder igual —la columna de la migración, el
 * tipo de sus foráneas, el trait del modelo y la expresión con que las pruebas fabrican un id que no
 * existe— y que hasta ahora vivía repartida en cuatro archivos que no se hablan.
 *
 * La cuarta es la que se olvida, y es la que rompe en silencio: un ULID literal contra una tabla
 * autoincremental **no afirma nada útil**, así que el contrato generado fallaría en el proyecto que
 * eligió el otro modo, con el fallo pareciendo un defecto de la funcionalidad.
 *
 * Y hay una razón para probar los dos modos y no solo el nuevo: `tenant_shared` demostró en la fase 5
 * que **un camino sin una sola prueba de generación** deja vivir defectos durante fases enteras.
 */

/** Deja la configuración en un modo concreto durante la prueba. */
function conClavePrimaria(string $modo, callable $prueba): void
{
    $anterior = config('make-module.primary_key');
    config(['make-module.primary_key' => $modo]);

    try {
        $prueba();
    } finally {
        config(['make-module.primary_key' => $anterior]);
    }
}

it('sin configuración declarada, la clave es ULID — lo que exige el patrón', function () {
    conClavePrimaria('', function () {
        expect(PrimaryKeyMode::current())->toBe(PrimaryKeyMode::Ulid);
    });
});

it('un valor desconocido NO cae al defecto en silencio: falla diciendo qué poner', function () {
    conClavePrimaria('uuid', function () {
        expect(fn () => PrimaryKeyMode::current())
            ->toThrow(InvalidArgumentException::class);
    });

    // El mensaje trae el arreglo, no solo el error.
    conClavePrimaria('uuid', function () {
        try {
            PrimaryKeyMode::current();
        } catch (InvalidArgumentException $e) {
            expect($e->getMessage())
                ->toContain('FALLA:')
                ->toContain('FIX:')
                ->toContain('ulid')
                ->toContain('increments');
        }
    });
})->note(
    'Ausente significa «no elegí» y recibe el defecto. Un valor equivocado significa «elegí y me '
    .'ignoraste», que produce migraciones contradiciendo la intención: es el fallo que esta clase '
    .'existe para prevenir.'
);

it('las cuatro respuestas de un modo son coherentes entre sí', function (
    string $modo,
    string $columna,
    string $foranea,
    bool $trait,
    string $idInexistente,
) {
    conClavePrimaria($modo, function () use ($columna, $foranea, $trait, $idInexistente) {
        $clave = PrimaryKeyMode::current();

        expect($clave->primaryKeyColumn())->toBe($columna);
        expect($clave->foreignKeyMethod())->toBe($foranea);
        expect($clave->needsUlidTrait())->toBe($trait);
        expect($clave->nonExistentIdExpression())->toBe($idInexistente);
        expect($clave->needsStrImport())->toBe($trait);
    });
})->with([
    'ulid' => ['ulid', "\$table->ulid('id')->primary();", 'foreignUlid', true, '(string) Str::ulid()'],
    'increments' => ['increments', '$table->id();', 'foreignId', false, '999999999'],
]);

it('el módulo generado sale coherente en los dos modos, de la migración a las pruebas', function (
    string $modo,
    string $columna,
    string $foranea,
    bool $conTrait,
) {
    conClavePrimaria($modo, function () use ($columna, $foranea, $conTrait) {
        $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp);

        // 1 · la migración declara la clave que la configuración pidió
        $migracion = file_get_contents($modulo->migrations()[0]);
        expect($migracion)->toContain($columna);

        // 2 · y sus foráneas son del tipo que puede referenciarla. Este es el par que, desacoplado,
        //     produce una restricción que simplemente no se crea.
        if (str_contains($migracion, 'foreign')) {
            expect($migracion)->toContain($foranea);
        }

        // 3 · el modelo lleva el trait solo cuando le toca. Con `HasUlids` sobre una tabla
        //     autoincremental, el modelo genera la clave en PHP y la base la ignora: el registro se
        //     guarda con un id y el modelo cree tener otro.
        $modelo = $modulo->contents('Models/Invoice/Invoice.php');
        expect(str_contains($modelo, 'use HasUlids;'))->toBe($conTrait);
        expect(str_contains($modelo, 'Concerns\HasUlids;'))->toBe($conTrait);

        // 4 · y las pruebas generadas fabrican un id inexistente que su tabla puede tener. La
        //     respuesta que se olvida — sin ella las otras tres siguen de acuerdo y el contrato
        //     falla igual.
        $http = $modulo->contents('Tests/Feature/Invoice/InvoiceHttpTest.php');
        expect(str_contains($http, 'Str::ulid()'))->toBe($conTrait);
        expect(str_contains($http, 'use Illuminate\Support\Str;'))->toBe($conTrait);

        if (! $conTrait) {
            expect($http)->toContain('999999999');
        }

        // Y lo escrito sigue siendo válido en los dos modos: sin placeholders sueltos y con cada
        // clase en el archivo que le toca.
        $modulo->assertCoherent();
    });
})->with([
    'ulid' => ['ulid', "\$table->ulid('id')->primary();", 'foreignUlid', true],
    'increments' => ['increments', '$table->id();', 'foreignId', false],
]);
