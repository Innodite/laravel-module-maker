<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Contracts;

/**
 * Entrar y salir del contexto de un cliente — lo único que el paquete necesita de la tenencia.
 *
 * **Existe para que el generador deje de llamar a una función global que no declara.** El
 * despliegue por tenants usaba `tenancy()` directamente, la orden de `stancl/tenancy`: un paquete
 * que **no está** en el `composer.json` de este, ni en `require` ni en `require-dev`. Eso tenía dos
 * consecuencias, y la segunda es la cara:
 *
 *   · Ninguna prueba podía ejecutar esas líneas —la función no existe aquí—, así que la mecánica
 *     más delicada del producto era la única sin red. Un `end()` en el sitio equivocado se habría
 *     descubierto en el alta de un cliente real.
 *   · Y `Support\TenancyPackage` quedaba a medias: declaraba **qué** paquete de tenencia usa el
 *     proyecto y luego no lo usaba para nada — solo devolvía etiquetas.
 *
 * Con el contrato, las pruebas comprueban lo que de verdad importa —que se entra, que se sale
 * **siempre**, y que no se cierra el contexto que ya estaba abierto— sin instalar nada.
 */
interface TenantContext
{
    /**
     * ¿Puede este proyecto entrar en el contexto de un cliente?
     *
     * `false` cuando el proyecto no declara un paquete de tenencia que el generador sepa
     * inicializar, o cuando lo declara y no está instalado. Quien llame decide qué hacer con eso:
     * el despliegue se niega, porque sembrar sin contexto llena la base central en silencio.
     */
    public function usable(): bool;

    /**
     * ¿Estamos ya dentro del contexto de **este** tenant?
     *
     * De este y no de otro: si el abierto fuera el de otro cliente, darlo por bueno sembraría la
     * base equivocada — que es justo lo que hay que impedir.
     */
    public function isInside(object $tenant): bool;

    /** Abre el contexto del cliente: a partir de aquí, la conexión por defecto es la suya. */
    public function enter(object $tenant): void;

    /** Lo cierra. Se llama pase lo que pase, también cuando el despliegue revienta. */
    public function leave(): void;
}
