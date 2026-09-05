<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Services\Tenancy;

use Innodite\LaravelModuleMaker\Contracts\TenantContext;
use Innodite\LaravelModuleMaker\Support\TenancyPackage;

/**
 * El contexto de cliente de `stancl/tenancy` — la única tenencia que el generador sabe inicializar.
 *
 * **Es la única implementación, y a propósito.** Un resolvedor que eligiera entre varias sería
 * andamiaje para un segundo paquete de tenencia que hoy no existe: `TenancyPackage` ya declara los
 * casos, y el día que entre uno nuevo, esa clase es la que dirá cuál toca. Mientras tanto, quien
 * decide si esto **se puede usar** es `usable()`, no el sitio donde se construye.
 *
 * ⚠️ **`stancl/tenancy` no es dependencia de este paquete** —ni de producción ni de desarrollo—, así
 * que su orden global puede no existir. Por eso `usable()` pregunta las dos cosas: que el proyecto
 * lo haya **declarado** y que además esté **instalado**. Declararlo sin tenerlo es el escenario en
 * que el despliegue creería estar entrando en la base del cliente y estaría llenando la central.
 */
class StanclTenantContext implements TenantContext
{
    public function usable(): bool
    {
        return TenancyPackage::current()->initialisesContext();
    }

    public function isInside(object $tenant): bool
    {
        if (! function_exists('tenancy')) {
            return false;
        }

        $tenancy = tenancy();

        if (! ($tenancy->initialized ?? false)) {
            return false;
        }

        $abierto = $tenancy->tenant ?? null;

        return $abierto !== null
            && (string) $abierto->getTenantKey() === (string) $tenant->getTenantKey();
    }

    public function enter(object $tenant): void
    {
        tenancy()->initialize($tenant);
    }

    public function leave(): void
    {
        tenancy()->end();
    }
}
