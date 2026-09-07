<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Tests\Support;

/**
 * El modelo de tenant del proyecto, de mentira: solo responde `find()` y `all()`.
 *
 * `innodite:deploy` saca de ahí las claves de los clientes a desplegar (`tenancy.tenant_model`), y
 * es lo único que necesita de él. Tenerlo aquí permite comprobar que **el despliegue del inquilino
 * escribe en la base del inquilino** sin instalar el paquete de tenencia — que es asunto de stancl,
 * no de este generador.
 */
class TenantModeloDeMentira
{
    /** @var array<int, string> Las claves que este modelo dice tener. */
    public static array $claves = ['acme'];

    public static function find(string|int $clave): ?TenantDeMentira
    {
        return in_array((string) $clave, self::$claves, true)
            ? new TenantDeMentira((string) $clave)
            : null;
    }

    /** @return \Illuminate\Support\Collection<int, TenantDeMentira> */
    public static function all(): \Illuminate\Support\Collection
    {
        return collect(self::$claves)->map(static fn (string $c): TenantDeMentira => new TenantDeMentira($c));
    }
}
