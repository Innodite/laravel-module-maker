<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services;

use Symfony\Component\Process\Process;

/**
 * Lanza un archivo de pruebas y dice si pasó — nada más.
 *
 * Es una clase entera para una sola llamada a `Process` por un motivo concreto: es **la frontera con
 * el mundo exterior**, y tenerla aislada es lo que permite probar la cascada del contrato sin lanzar
 * un PHPUnit de verdad dentro de otro PHPUnit. La prueba sustituye esta clase en el contenedor por un
 * doble que devuelve resultados fijados, y así puede comprobar lo que de verdad importa —el orden de
 * las piezas y el corte al primer fallo— en milisegundos y sin procesos hijos.
 *
 * Lo que se probaría lanzando PHPUnit de verdad es que PHPUnit funciona.
 */
class PhpunitRunner
{
    /**
     * Ejecuta un archivo de pruebas y devuelve `[pasó, salida]`.
     *
     * @return array{ok: bool, salida: string}
     */
    public function ejecutar(string $archivo, ?string $filtro = null): array
    {
        $comando = [$this->binario(), $archivo];

        if ($filtro !== null && $filtro !== '') {
            $comando[] = '--filter';
            $comando[] = $filtro;
        }

        $proceso = new Process($comando, base_path());

        // Sin límite de tiempo: el tema 8 despliega de verdad —migraciones, seeders y permisos— y en
        // un módulo grande eso pasa del minuto. Un corte por tiempo se leería como un fallo de la
        // prueba, que es la peor forma de perder una hora buscando.
        $proceso->setTimeout(null);

        $proceso->run();

        return [
            'ok'     => $proceso->isSuccessful(),
            'salida' => $proceso->getOutput() . $proceso->getErrorOutput(),
        ];
    }

    /**
     * El binario de PHPUnit del proyecto, o el del PATH si el proyecto no lo tiene publicado.
     *
     * Se prefiere el del proyecto porque es el que lleva su configuración y sus extensiones: usar
     * otro es ejecutar las pruebas con una versión que nadie eligió.
     */
    protected function binario(): string
    {
        $delProyecto = base_path('vendor/bin/phpunit');

        return is_file($delProyecto) ? $delProyecto : 'phpunit';
    }
}
