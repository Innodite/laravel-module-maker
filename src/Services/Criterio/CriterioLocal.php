<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services\Criterio;

use Innodite\LaravelModuleMaker\Contracts\ProveedorDeCriterio;

/**
 * El proveedor por defecto: **no hay criterio**, y lo dice.
 *
 * Responde vacío a todo, y eso no es una implementación a medias — es la implementación correcta. Las
 * reglas del patrón, los hallazgos y las recomendaciones **no se distribuyen** en un paquete público
 * (regla 4): viven en el servidor de Innodite y se consumen por API con token. Escribirlas aquí
 * «temporalmente, hasta que exista el remoto» sería publicarlas, y lo que se publica una vez ya está
 * publicado.
 *
 * Lo que sí hace es **existir**, para que quien pregunte tenga a quién preguntar. Un comando que
 * llama al criterio no necesita saber si hay servidor: pregunta, recibe vacío y sigue. El día que se
 * configure `CriterioRemoto`, esos mismos comandos empiezan a recibir reglas sin que ninguno cambie
 * una línea — que es lo que convierte la fase 2 del producto en cambiar una clave de configuración.
 *
 * ⛔ **Este archivo es el que hay que vigilar.** Es donde resulta cómodo «dejar apuntada» una regla, y
 * es exactamente donde no puede estar. Hay una prueba que lo comprueba.
 */
final class CriterioLocal implements ProveedorDeCriterio
{
    /**
     * Siempre disponible, y siempre vacío.
     *
     * Responde `true` porque la pregunta es «¿hay a quién preguntar?», no «¿hay reglas?». Un `false`
     * aquí haría que quien consume tratara como avería lo que es el estado normal de un proyecto que
     * todavía no ha conectado su criterio.
     */
    public function disponible(): bool
    {
        return true;
    }

    public function nombre(): string
    {
        return 'local — sin reglas propias';
    }

    /**
     * Vacío. Las reglas del área las tiene el servidor, no el paquete.
     *
     * @return array<int, array{id: string, titulo: string, severidad: string}>
     */
    public function reglas(string $area): array
    {
        return [];
    }

    /**
     * Vacío. Un hallazgo es una opinión sobre lo que debería ser, y de eso sabe el criterio.
     *
     * @param  array<string, mixed>  $contexto
     * @return array<int, array{regla: string, mensaje: string, arreglo: string}>
     */
    public function revisar(string $area, array $contexto): array
    {
        return [];
    }
}
