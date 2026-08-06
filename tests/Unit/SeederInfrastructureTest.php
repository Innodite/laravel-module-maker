<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Traits\ReportsSeederErrors;
use Innodite\LaravelModuleMaker\Traits\ResolvesSeederDestructiveMode;

/**
 * La infraestructura que comparten las tres piezas ejecutables (R23 · R24).
 *
 * Vive en el paquete y no en el archivo generado porque es idéntica en cada subfuncionalidad de cada
 * módulo de cada proyecto: escribirla en el archivo generado sería sembrar N copias del mismo código
 * para que envejezcan por separado. Y como la comparte todo lo que el paquete genera, tiene que
 * comportarse exactamente como dice la norma — que es lo que se comprueba aquí.
 */

/** Un seeder de mentira: solo tiene los pasos, para poder mirar qué hace `safe()` entre ellos. */
function seederDePrueba(): object
{
    return new class () {
        use ReportsSeederErrors;

        /** @var array<int, string> */
        public array $ejecutados = [];

        public function correrTresPasosConElDeEnMedioRoto(): void
        {
            $this->safe('primero', function (): void {
                $this->ejecutados[] = 'primero';
            });

            $this->safe('segundo', function (): void {
                $this->ejecutados[] = 'segundo';

                throw new RuntimeException('la tabla no existe');
            });

            $this->safe('tercero', function (): void {
                $this->ejecutados[] = 'tercero';
            });
        }

        public function cerrar(): void
        {
            $this->reportErrors();
        }

        public function huboErrores(): bool
        {
            return $this->hasSeederErrors();
        }
    };
}

it('un paso que falla no detiene a los que vienen detrás', function () {
    // Sin esto, un despliegue de quince pasos se arregla de uno en uno: falla el tercero, se
    // corrige, se relanza, falla el séptimo. `safe()` acumula para que una sola pasada deje delante
    // la lista completa de lo que hay que arreglar.
    $seeder = seederDePrueba();

    $seeder->correrTresPasosConElDeEnMedioRoto();

    expect($seeder->ejecutados)->toBe(['primero', 'segundo', 'tercero']);
    expect($seeder->huboErrores())->toBeTrue();
});

it('al cerrar, el seeder termina en rojo y nombra todos los fallos', function () {
    // El anti-patrón que la norma señala es el `try/catch` que traga la excepción y termina en
    // verde: un despliegue que falló a medias informando de éxito. Acumular sirve para verlo todo,
    // no para ocultarlo — así que `reportErrors()` lanza.
    $seeder = seederDePrueba();
    $seeder->correrTresPasosConElDeEnMedioRoto();

    expect(fn () => $seeder->cerrar())
        ->toThrow(RuntimeException::class, 'la tabla no existe');
});

it('sin fallos no lanza nada', function () {
    $seeder = seederDePrueba();

    $seeder->cerrar();

    expect($seeder->huboErrores())->toBeFalse();
});

it('el fallo dice en qué paso y en qué archivo y línea ocurrió', function () {
    // Un informe que solo diga «falló» obliga a reproducirlo para saber dónde. El paso lo nombra
    // quien lo escribió; el archivo y la línea los pone la excepción.
    $seeder = seederDePrueba();
    $seeder->correrTresPasosConElDeEnMedioRoto();

    try {
        $seeder->cerrar();
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('segundo')
            ->and($e->getMessage())->toContain('SeederInfrastructureTest.php');

        return;
    }

    $this->fail('Se esperaba que reportErrors() lanzara.');
});

it('se puede ejecutar sin consola, que es como lo ejecutan las pruebas', function () {
    // `$this->command` solo existe cuando el seeder corre por artisan. Llamarlo a ciegas convierte
    // en fatal cualquier ejecución desde código — justo la que la fase 4 necesita para probarlo.
    $seeder = seederDePrueba();

    $seeder->correrTresPasosConElDeEnMedioRoto();

    expect($seeder->ejecutados)->toHaveCount(3);
});

it('el modo destructivo está apagado mientras nadie lo pida', function () {
    // El mismo comando que en local reconstruye una tabla de prueba, en el servidor equivocado borra
    // datos reales. Por eso el borrado no es un modo del seeder sino una orden de quien lo lanza.
    $seeder = new class () {
        use ResolvesSeederDestructiveMode;

        public function esDestructivo(): bool
        {
            return $this->isDestructive();
        }
    };

    expect($seeder->esDestructivo())->toBeFalse();
});

it('se enciende con SEEDER_DESTRUCTIVE, y solo con eso', function () {
    $seeder = new class () {
        use ResolvesSeederDestructiveMode;

        public function esDestructivo(): bool
        {
            return $this->isDestructive();
        }
    };

    putenv('SEEDER_DESTRUCTIVE=true');
    $_ENV['SEEDER_DESTRUCTIVE']    = 'true';
    $_SERVER['SEEDER_DESTRUCTIVE'] = 'true';

    try {
        expect($seeder->esDestructivo())->toBeTrue();
    } finally {
        putenv('SEEDER_DESTRUCTIVE');
        unset($_ENV['SEEDER_DESTRUCTIVE'], $_SERVER['SEEDER_DESTRUCTIVE']);
    }
});
