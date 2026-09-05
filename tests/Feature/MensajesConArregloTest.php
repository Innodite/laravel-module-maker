<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * R30 en los comandos: un fallo dice qué pasó **y qué hacer**.
 *
 * El paquete ya lo aplicaba en las pruebas que genera —`FALLA: … · FIX: …`— y sus propios comandos
 * no. Casi todos explicaban la salida, en prosa, en las líneas de abajo; lo que les faltaba era la
 * forma. Suena cosmético y no lo es, por dos motivos:
 *
 *   · Un arreglo que no se encuentra es un arreglo que no se aplica: cuatro frases seguidas tapan
 *     la única línea que dice qué escribir.
 *   · Y por **R81**: un rojo se clasifica antes de investigarse, y clasificar es leer muchos fallos
 *     rápido. Un mensaje con el FIX marcado se ojea; uno sin él hay que leerlo entero, y entonces
 *     se salta.
 *
 * Estas pruebas no miran el código: **provocan el fallo de verdad** y leen lo que sale por consola.
 * Un estándar comprobado por inspección estática se cumple en el archivo y se incumple en pantalla.
 */

/** Lanza un comando que debe fallar y devuelve su salida. */
function salidaDelFallo(string $comando, array $parametros = []): string
{
    Artisan::call($comando, array_merge($parametros, ['--no-interaction' => true]));

    return Artisan::output();
}

it('el fallo de cada comando trae el arreglo, no solo el diagnóstico', function (
    string $titulo,
    string $comando,
    array $parametros
) {
    $salida = salidaDelFallo($comando, $parametros);

    expect(str_contains($salida, 'FALLA:'))->toBeTrue(
        "FALLA: {$titulo} no marca el fallo.\nLa salida dice:\n{$salida}"
    );

    expect(str_contains($salida, 'FIX:'))->toBeTrue(
        "FALLA: {$titulo} dice qué pasó y no qué hacer. · FIX: el mensaje lleva el arreglo — una "
        . "orden, un comando o un valor.\nLa salida dice:\n{$salida}"
    );
})->with([
    ['el entorno de despliegue inválido', 'innodite:deploy', ['environment' => 'preprod']],
    ['el despliegue sin contexto', 'innodite:deploy', ['environment' => 'production']],
    ['el módulo que no existe', 'innodite:add-entity', ['module' => 'Fantasma', 'entity' => 'Cosa']],
    ['el nombre de módulo reservado', 'innodite:make-module', ['name' => 'class']],
    ['el plan de migración, retirado', 'innodite:migrate-plan', []],
    ['el modo de instalación inexistente', 'innodite:module-setup', ['--mode' => 'multi-tenant']],
]);

it('el FIX dice qué hacer, no repite el fallo con otras palabras', function () {
    // El error que hace inútil al estándar: «el contexto no existe · FIX: usa un contexto que
    // exista». Aquí se comprueba que el arreglo nombra algo accionable — el catálogo real.
    config()->set('make-module.mode', ModuleMode::MultitenantPerTenant->value);

    $salida = salidaDelFallo('innodite:deploy', ['environment' => 'production']);

    expect(str_contains($salida, '--context=central'))->toBeTrue(
        "FALLA: el FIX no dice qué escribir.\n{$salida}"
    );
});
