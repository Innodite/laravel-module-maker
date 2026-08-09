<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Contracts;

/**
 * De dónde salen las reglas — la frontera entre lo que se distribuye y lo que no.
 *
 * Este paquete es público y reparte **mecánica**: estructura de carpetas, generación, migraciones,
 * despliegue, ejecución del contrato de pruebas. El **criterio** —qué reglas debe cumplir un módulo,
 * qué se considera un hallazgo, qué se recomienda corregir— no se distribuye: vive en el servidor de
 * Innodite y se consume por API con token. Es la regla 4 del proyecto, y no tiene excepciones
 * «temporales»: lo que se publica una vez, ya está publicado.
 *
 * Esta interfaz es el enchufe entre las dos cosas. Hoy la implementa {@see CriterioLocal}, que
 * responde vacío a todo — no porque falte escribirle las reglas, sino porque **escribirlas aquí sería
 * cruzar la línea**. Cuando exista `CriterioRemoto`, se cambia una clave de configuración y ningún
 * comando se entera: eso es lo que convierte la fase 2 del producto en cambiar una conexión, en vez
 * de en reescribir los diez comandos.
 *
 * **Qué NO es criterio.** Que `contexts.json` exista, que la carpeta se pueda escribir, que el
 * `User` tenga un trait: eso son hechos comprobables sobre un proyecto, y de esos sabe el paquete.
 * Criterio es lo que *debería* ser, y de eso sabe el servidor.
 */
interface ProveedorDeCriterio
{
    /**
     * ¿Hay criterio al que preguntar?
     *
     * El proveedor remoto puede no responder —sin red, sin token, servidor caído—, y lo que consume
     * esta interfaz tiene que poder seguir adelante sin él: un generador que deja de generar porque
     * un servicio de recomendaciones no contesta es peor que uno sin recomendaciones.
     */
    public function disponible(): bool;

    /**
     * Cómo se identifica este proveedor en pantalla — «local», «servidor de Innodite»…
     */
    public function nombre(): string;

    /**
     * Las reglas que aplican a un área del patrón (`capas`, `seeders`, `permisos`, `tests`…).
     *
     * @return array<int, array{id: string, titulo: string, severidad: string}>  Vacío si no hay
     */
    public function reglas(string $area): array;

    /**
     * Los hallazgos sobre algo concreto: un módulo generado, una subfuncionalidad, un despliegue.
     *
     * El `contexto` es lo que el comando sabe y el criterio no puede adivinar — nombre del módulo,
     * modo del proyecto, rutas escritas—; los hallazgos son lo que el criterio sabe y el comando no.
     *
     * @param  array<string, mixed>  $contexto
     * @return array<int, array{regla: string, mensaje: string, arreglo: string}>  Vacío si no hay
     */
    public function revisar(string $area, array $contexto): array;
}
