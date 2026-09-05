<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Traits;

use Closure;
use RuntimeException;
use Throwable;

/**
 * ReportsSeederErrors — el paso que falla no se lleva por delante a los que vienen detrás.
 *
 * **Por qué existe.** Un despliegue son diez o quince pasos encadenados —migrar, alterar, validar,
 * sembrar, permisos—, y sin esto el primero que revienta corta la ejecución: se arregla, se vuelve a
 * lanzar, revienta el siguiente, y así una vez por cada fallo. `safe()` **acumula y continúa**, de
 * modo que una sola pasada deja delante la lista completa de lo que hay que arreglar.
 *
 * **Lo que NO es.** No es un `try/catch` que traga la excepción y termina en verde: ese es el
 * anti-patrón que la norma señala, y es peor que no capturar nada, porque un despliegue de producción
 * que falló a medias informa de éxito. Aquí cada fallo se imprime **con su `archivo:línea`** en el
 * momento, se lista otra vez al cerrar, y `reportErrors()` **lanza**: el proceso termina en rojo y
 * quien lo automatizó se entera.
 *
 * **Vive en el paquete y no en el módulo generado, a propósito.** `safe()` y `reportErrors()` son
 * idénticos en las tres piezas ejecutables de cada subfuncionalidad de cada módulo de cada proyecto:
 * escribirlos en el archivo generado es sembrar N copias del mismo código que envejecen por separado
 * —el patrón que produjo los defectos de las fases anteriores—. Lo que sí se escribe en el archivo
 * generado es la **secuencia de pasos**, que es lo que el desarrollador lee y amplía.
 */
trait ReportsSeederErrors
{
    /**
     * Los fallos acumulados durante la ejecución.
     *
     * @var array<int, array{step: string, message: string, file: string, line: int}>
     */
    protected array $seederErrors = [];

    /**
     * Ejecuta un paso; si revienta, lo anota y **sigue con el siguiente**.
     *
     * El nombre del paso es lo que se lee en el informe final, así que nombra la operación
     * (`'runMigrations'`, `'upsertCanonicalData'`) y no el resultado esperado.
     */
    protected function safe(string $step, Closure $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->seederErrors[] = [
                'step'    => $step,
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ];

            $this->say("   ❌ {$step} FALLÓ: {$e->getMessage()}", 'error');
            $this->say("      en {$e->getFile()}:{$e->getLine()}", 'error');
        }
    }

    /**
     * Cierra la ejecución: lista todo lo que falló y **deja el proceso en rojo**.
     *
     * Que lance al final, y no en el momento del fallo, es lo que hace útil a `safe()`: primero se
     * intenta todo y se informa de todo, y solo entonces se falla. Terminar en verde con errores
     * dentro convertiría a `safe()` en el anti-patrón que vino a evitar.
     *
     * Un seeder maestro que llame a sus hijos **dentro de su propio `safe()`** obtiene el
     * comportamiento completo: cada hijo se intenta, el que falla no detiene a los demás, y el
     * maestro reporta el conjunto al cerrar.
     *
     * @throws RuntimeException Si algún paso falló.
     */
    protected function reportErrors(): void
    {
        if ($this->seederErrors === []) {
            $this->say('   🎉 Sin errores.');

            return;
        }

        $total = count($this->seederErrors);

        $this->say("\n⚠️  Terminó con {$total} error(es):", 'error');

        $resumen = [];

        foreach ($this->seederErrors as $i => $error) {
            $n = $i + 1;

            $this->say("\n  [{$n}] Paso: {$error['step']}", 'error');
            $this->say("      Mensaje: {$error['message']}", 'error');
            $this->say("      Ubicación: {$error['file']}:{$error['line']}", 'error');

            $resumen[] = "[{$n}] {$error['step']}: {$error['message']} ({$error['file']}:{$error['line']})";
        }

        throw new RuntimeException(
            static::class . " terminó con {$total} error(es):\n  " . implode("\n  ", $resumen)
        );
    }

    /** ¿Hubo algún fallo hasta ahora? */
    protected function hasSeederErrors(): bool
    {
        return $this->seederErrors !== [];
    }

    /**
     * Escribe en la consola **si hay consola**.
     *
     * Un seeder invocado desde una prueba —o desde código— no tiene `$this->command`, y llamarlo
     * ahí sería un fatal por método sobre `null`: el seeder dejaría de poder probarse, que es justo
     * lo que la fase 4 necesita hacer con él.
     */
    protected function say(string $message, string $level = 'info'): void
    {
        if (! isset($this->command) || $this->command === null) {
            return;
        }

        match ($level) {
            'error' => $this->command->error($message),
            'warn'  => $this->command->warn($message),
            default => $this->command->info($message),
        };
    }
}
