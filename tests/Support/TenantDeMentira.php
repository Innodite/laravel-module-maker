<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Tests\Support;

/** Un tenant: de él, el despliegue solo necesita su clave. */
class TenantDeMentira
{
    public function __construct(private readonly string $clave = 'acme')
    {
    }

    public function getTenantKey(): string
    {
        return $this->clave;
    }
}
