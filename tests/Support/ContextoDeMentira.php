<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Tests\Support;

use Innodite\LaravelModuleMaker\Contracts\TenantContext;

/**
 * El contexto de cliente, de mentira: apunta lo que le piden en vez de hacerlo.
 *
 * Es lo que permite comprobar la mecánica más delicada del despliegue —entrar en la base del
 * cliente y salir de ella— **sin instalar el paquete de tenencia**. Lo que se vigila con él no es
 * que stancl funcione, que es asunto de stancl: es que el generador lo llame en el orden correcto y
 * que salga siempre, también cuando el despliegue revienta a mitad.
 */
class ContextoDeMentira implements TenantContext
{
    /** @var array<int, string> Lo que se le pidió, en orden. */
    public array $pasos = [];

    public function __construct(
        private readonly bool $usable = true,
        private readonly ?object $abierto = null,
    ) {
    }

    public function usable(): bool
    {
        return $this->usable;
    }

    public function isInside(object $tenant): bool
    {
        return $this->abierto !== null
            && (string) $this->abierto->getTenantKey() === (string) $tenant->getTenantKey();
    }

    public function enter(object $tenant): void
    {
        $this->pasos[] = 'entra:' . $tenant->getTenantKey();
    }

    public function leave(): void
    {
        $this->pasos[] = 'sale';
    }
}
