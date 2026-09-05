<?php

declare(strict_types=1);

use Illuminate\Database\Seeder;
use Innodite\LaravelModuleMaker\Services\DeploymentRunner;
use Innodite\LaravelModuleMaker\Support\DeploymentResult;

/**
 * El despliegue, invocable desde código y no solo desde la consola.
 *
 * **Por qué existe esta prueba.** Hasta aquí el despliegue vivía dentro de `innodite:deploy`: para
 * levantar un tenant había que lanzar un comando. Un proyecto que da de alta clientes por
 * autoservicio necesita hacerlo **dentro de la petición**, sobre una base que acaba de nacer, y ahí
 * no hay consola a la que llamar — así que cada proyecto se escribía el suyo, y el primero que lo
 * hizo se saltó dos de las seis piezas del patrón sin enterarse.
 *
 * Lo que se fija aquí es la condición que lo hace posible: **el runner funciona sin comando** y
 * devuelve **hasta dónde llegó**, que es lo que un `Artisan::call` no da y sin lo cual el alta no
 * se puede deshacer.
 */
class SeederQueAnota extends Seeder
{
    /** @var array<int, string> */
    public static array $ejecutado = [];

    public function run(string $piece = 'Production'): void
    {
        self::$ejecutado[] = $piece;
    }
}

class SeederQueRevienta extends Seeder
{
    public function run(string $piece = 'Production'): void
    {
        throw new RuntimeException('la tabla facturas no existe');
    }
}

beforeEach(function () {
    SeederQueAnota::$ejecutado = [];
});

it('se despliega desde código, sin consola de por medio', function () {
    // Sin comando: es como lo invocaría el aprovisionamiento de un proyecto. El seeder calla —
    // `say()` no habla si no hay comando— pero se ejecuta igual, y con la pieza que se le pidió.
    $resultado = (new DeploymentRunner($this->app))->run(SeederQueAnota::class, 'Stage');

    expect($resultado)->toBeInstanceOf(DeploymentResult::class)
        ->and($resultado->successful())->toBeTrue()
        ->and(SeederQueAnota::$ejecutado)->toBe(['Stage']);
});

it('el resultado dice qué pasos se aplicaron, y no solo que fue bien', function () {
    // El dato que un código de salida no puede dar: un `0` no dice qué quedó hecho.
    $resultado = (new DeploymentRunner($this->app))->run(SeederQueAnota::class, 'Production', 'acme');

    expect($resultado->applied())->toBe(['acme'])
        ->and($resultado->errors())->toBe([]);
});

it('cuando el seeder revienta, el resultado dice cuál falló y con qué mensaje', function () {
    // Sin esto, quien llama sabe que algo falló y no qué deshacer. El mensaje llega tal cual lo
    // lanzó el seeder: reescribirlo aquí sería tapar el archivo y la línea que él ya dio.
    $resultado = (new DeploymentRunner($this->app))->run(SeederQueRevienta::class, 'Stage', 'acme');

    expect($resultado->successful())->toBeFalse()
        ->and($resultado->errors())->toHaveKey('acme')
        ->and($resultado->firstError())->toContain('la tabla facturas no existe')
        ->and($resultado->applied())->toBe([]);
});

it('el resultado es inmutable: un paso nuevo no toca el anterior', function () {
    // Se recorre mientras se acumula, así que el que se recibió al empezar no puede cambiar debajo.
    $primero = DeploymentResult::empty()->withApplied('uno');
    $segundo = $primero->withFailure('dos', 'reventó');

    expect($primero->successful())->toBeTrue()
        ->and($primero->applied())->toBe(['uno'])
        ->and($segundo->successful())->toBeFalse()
        ->and($segundo->applied())->toBe(['uno']);
});
