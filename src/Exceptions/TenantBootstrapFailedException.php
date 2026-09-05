<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando un tenant recién creado **no se puede levantar**, y por eso no se levanta.
 *
 * **Por qué excepción y no un aviso.** Por consola, quien lanza el despliegue lee la pantalla: un
 * «no hay nada declarado que desplegar» se ve y se corrige. En el alta automática de un cliente no
 * hay nadie leyendo — y devolver «bien» sin haber creado una sola tabla le entrega al cliente una
 * cuenta que parece lista y está vacía. Eso no se descubre al desplegar: se descubre cuando el
 * cliente entra, días después, y para entonces el alta ya se dio por buena.
 *
 * Todos los mensajes siguen el estándar del paquete —`FALLA: … · FIX: …`—, porque quien los va a
 * leer es el desarrollador del proyecto anfitrión, en un log, sin el comando delante.
 */
final class TenantBootstrapFailedException extends RuntimeException
{
    public static function unknownEnvironment(string $given): self
    {
        return new self(
            "FALLA: '{$given}' no es un entorno de despliegue.\n"
            . "  · FIX: pásale 'stage' o 'production', escrito entero.\n"
            . "  Sin valor por defecto a propósito: stage puede reconstruir desde cero y production "
            . "nunca borra nada, así que adivinar cuál quería quien llama es justo lo que no se hace."
        );
    }

    public static function notMultitenant(string $mode): self
    {
        return new self(
            "FALLA: el proyecto está en modo '{$mode}', donde no hay tenants que levantar.\n"
            . "  · FIX: si este proyecto tiene clientes con base propia, declara el modo multitenant "
            . "en config/make-module.php; si no, lo que buscas es el despliegue del proyecto."
        );
    }

    public static function tenancyNotUsable(string $paquete): self
    {
        return new self(
            "FALLA: el proyecto no declara un paquete de tenencia que el generador sepa inicializar "
            . "(hoy: {$paquete}).\n"
            . "  · FIX: declara `tenancy_package` en config/make-module.php, o entra tú en el "
            . "contexto del cliente antes de llamar a este arranque.\n"
            . "  Sin el contexto inicializado, el despliegue escribiría en la base central creyendo "
            . "que escribe en la del cliente, y sin dar un solo error."
        );
    }

    public static function seederMissing(string $fqcn): self
    {
        return new self(
            "FALLA: no existe {$fqcn}, que es el seeder de despliegue del contexto de tenant.\n"
            . "  · FIX: lánzalo con el instalador — php artisan innodite:module-setup\n"
            . "  Ese seeder es del proyecto, no del paquete: lo escribe el instalador y luego lo "
            . "amplías tú."
        );
    }

    public static function nothingDeclared(): self
    {
        return new self(
            "FALLA: el orden de despliegue está vacío, así que no habría nada que levantar.\n"
            . "  · FIX: declara `deploy` en config/make-module.php — cada línea es la carpeta de una "
            . "subfuncionalidad, y el orden de la lista es el orden en que se despliegan.\n"
            . "  Se detiene aquí a propósito: seguir devolvería un alta correcta con la base vacía."
        );
    }
}
