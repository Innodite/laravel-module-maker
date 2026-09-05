<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Innodite\LaravelModuleMaker\Contracts\ProveedorDeCriterio;
use Innodite\LaravelModuleMaker\Services\Criterio\CriterioLocal;

/**
 * `CriterioLocal` — el proveedor por defecto responde vacío, y eso es lo correcto.
 *
 * La regla 4 en su punto de rotura: este archivo es donde resulta cómodo «dejar apuntada» una regla
 * mientras no exista el remoto, y es exactamente donde no puede estar. Un paquete público que
 * distribuye criterio ya lo ha distribuido — no hay vuelta atrás en una versión posterior.
 *
 * Y la otra mitad: que el enchufe **tenga consumidor**. Una interfaz que nadie llama es código muerto
 * con buena intención, que es justo lo que la regla 7 de este proyecto viene evitando desde F1.
 */

it('⛔ el proveedor por defecto no trae ni una regla', function () {
    $criterio = new CriterioLocal();

    foreach (['capas', 'seeders', 'permisos', 'tests', 'migraciones'] as $area) {
        expect($criterio->reglas($area))->toBe(
            [],
            "FALLA: el paquete trae reglas de '{$area}' dentro. · FIX: quítalas — el criterio vive en "
            . 'el servidor de Innodite (regla 4), y lo que se publica una vez ya está publicado.'
        );
    }

    expect($criterio->revisar('proyecto', ['modo' => 'single-app']))->toBe(
        [],
        'FALLA: el paquete emite hallazgos propios. · FIX: un hallazgo es una opinión sobre lo que '
        . 'debería ser, y de eso sabe el criterio, no el generador.'
    );
});

it('está disponible aunque no tenga nada que decir', function () {
    // La pregunta es «¿hay a quién preguntar?», no «¿hay reglas?». Un false aquí haría que quien
    // consume tratara como avería el estado normal de un proyecto sin criterio conectado.
    expect((new CriterioLocal())->disponible())->toBeTrue();
    expect((new CriterioLocal())->nombre())->toContain('local');
});

it('la configuración decide qué proveedor llega, y por defecto es el local', function () {
    expect(app(ProveedorDeCriterio::class))->toBeInstanceOf(CriterioLocal::class);

    // Y con otro configurado, llega ese — sin tocar una línea de ningún comando. Eso es toda la
    // fase 2 del producto: una clave de configuración.
    config()->set('make-module.criterio.proveedor', CriterioDePrueba::class);
    app()->forgetInstance(ProveedorDeCriterio::class);

    expect(app(ProveedorDeCriterio::class))->toBeInstanceOf(CriterioDePrueba::class);
});

it('el diagnóstico le pregunta al criterio, y enseña sus hallazgos como propios', function () {
    // Lo que hace que el enchufe no sea código muerto: hoy la respuesta es vacía y solo se ve el
    // nombre del proveedor; el día que se conecte el remoto, sus hallazgos salen aquí sin tocar el
    // comando.
    Artisan::call('innodite:doctor', ['--continuar' => true, '--no-interaction' => true]);

    expect(str_contains(Artisan::output(), 'Lo que dice el criterio'))->toBeTrue(
        'FALLA: el diagnóstico no le pregunta a nadie por las reglas.'
    );

    config()->set('make-module.criterio.proveedor', CriterioDePrueba::class);
    app()->forgetInstance(ProveedorDeCriterio::class);

    Artisan::call('innodite:doctor', ['--continuar' => true, '--no-interaction' => true]);
    $salida = Artisan::output();

    expect(str_contains($salida, 'las capas están cruzadas'))->toBeTrue(
        "FALLA: el hallazgo del criterio no aparece en el diagnóstico.\n{$salida}"
    );

    expect(str_contains($salida, 'FIX:'))->toBeTrue(
        "FALLA: el hallazgo llega sin su arreglo — el estándar vale también para el criterio.\n{$salida}"
    );
});

/** Un criterio de mentira, para comprobar que el enchufe enchufa. */
final class CriterioDePrueba implements ProveedorDeCriterio
{
    public function disponible(): bool
    {
        return true;
    }

    public function nombre(): string
    {
        return 'de prueba';
    }

    public function reglas(string $area): array
    {
        return [['id' => 'X1', 'titulo' => 'Una regla de ejemplo', 'severidad' => 'alta']];
    }

    public function revisar(string $area, array $contexto): array
    {
        return [[
            'regla'   => 'X1',
            'mensaje' => 'las capas están cruzadas: el controlador toca el modelo.',
            'arreglo' => 'pásalo por el servicio y el repositorio.',
        ]];
    }
}
