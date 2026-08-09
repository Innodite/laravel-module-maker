<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Contracts\ProveedorDeCriterio;

/**
 * El enchufe del criterio — la forma de la frontera entre lo que se distribuye y lo que no.
 *
 * Lo que estas pruebas fijan es que la interfaz **exista y siga siendo una interfaz**: el día que
 * alguien le meta un método con cuerpo y una regla dentro, el paquete público habrá pasado a
 * distribuir criterio, que es la línea que la regla 4 no deja cruzar.
 */

it('el enchufe existe y es una interfaz, no una clase con reglas dentro', function () {
    expect(interface_exists(ProveedorDeCriterio::class))->toBeTrue(
        'FALLA: no está la interfaz. · FIX: es lo que permite que la fase 2 sea cambiar una '
        . 'conexión en vez de reescribir los diez comandos.'
    );
});

it('declara las cuatro preguntas que un comando le puede hacer al criterio', function () {
    $metodos = array_map(
        fn ($m) => $m->getName(),
        (new ReflectionClass(ProveedorDeCriterio::class))->getMethods()
    );

    sort($metodos);

    expect($metodos)->toBe(
        ['disponible', 'nombre', 'reglas', 'revisar'],
        'FALLA: el contrato cambió. · FIX: si hace falta otra pregunta, añádela aquí y a esta '
        . 'prueba — pero cada método nuevo es una forma más de que el criterio se cuele en el paquete.'
    );
});

it('⛔ el enchufe no trae ni una regla dentro', function () {
    // La regla 4, comprobada donde se rompería: una interfaz con constantes de reglas o un método
    // con cuerpo por defecto sería criterio distribuido en un paquete público. Lo que se publica una
    // vez, ya está publicado.
    $reflexion = new ReflectionClass(ProveedorDeCriterio::class);

    expect($reflexion->getConstants())->toBe(
        [],
        'FALLA: la interfaz declara constantes. · FIX: si son reglas, van en el servidor; si son '
        . 'nombres de área, van donde se usan.'
    );

    foreach ($reflexion->getMethods() as $metodo) {
        expect($metodo->isAbstract())->toBeTrue(
            "FALLA: {$metodo->getName()} tiene cuerpo. · FIX: en una interfaz, un cuerpo es una "
            . 'decisión horneada — y aquí las decisiones son el producto que no se distribuye.'
        );
    }
});
