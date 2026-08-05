<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

/**
 * ModuleMode — what kind of application the package is generating for.
 *
 * The mode is chosen once, at install time, and lives in configuration. No command
 * ever infers it by inspecting the project: guessing produces files that look right
 * and are wrong in the one place nobody checks.
 *
 * Everything downstream asks the mode instead of hardcoding a decision — whether the
 * context axis exists at all, whether class names carry a prefix, whether a tenant
 * declares its own connection, and which permission middleware guards a route.
 */
enum ModuleMode: string
{
    /** A single application. No tenancy, no context axis, no prefixes. */
    case SingleApp = 'single-app';

    /** Many tenants running the same feature set. The context axis exists; tenants are not named. */
    case MultitenantShared = 'multitenant-shared';

    /** Tenants with business logic of their own. Each named tenant gets its own files. */
    case MultitenantPerTenant = 'multitenant-per-tenant';

    /**
     * The configured mode, or the multi-tenant-per-tenant default the package was born with.
     */
    public static function current(): self
    {
        $configured = config('make-module.mode');

        return is_string($configured)
            ? (self::tryFrom($configured) ?? self::MultitenantPerTenant)
            : self::MultitenantPerTenant;
    }

    /**
     * Does the folder tree carry a Central/Tenant axis?
     *
     * Only multi-tenant projects have one. A single application puts its subfeature
     * straight under the layer: Models/Role/, not Models/Central/Role/.
     */
    public function hasContextAxis(): bool
    {
        return $this !== self::SingleApp;
    }

    /**
     * Do class names carry a context prefix (CentralRoleController)?
     *
     * The prefix exists to disambiguate contexts. With no context axis there is
     * nothing to disambiguate, so the prefix would be noise.
     */
    public function usesClassPrefix(): bool
    {
        return $this->hasContextAxis();
    }

    /**
     * Does a tenant get named in its class names and folders?
     *
     * Only when it has logic of its own. A tenant that does exactly what the others do
     * would otherwise become duplicated code wearing a label.
     */
    public function namesTenant(): bool
    {
        return $this === self::MultitenantPerTenant;
    }

    /**
     * Must a tenant context declare its own database connection?
     *
     * When every tenant shares the same feature set, no: the tenancy package switches
     * the connection when it initialises the context, and the route guarantees the
     * isolation. Demanding a connection key there produces a model bound to one tenant.
     */
    public function requiresTenantConnectionKey(): bool
    {
        return $this === self::MultitenantPerTenant;
    }

    /**
     * Context keys that contexts.json must declare for this mode.
     *
     * A single application has no tenants to declare, so demanding a 'tenant' key
     * forces it to invent one to pass a diagnostic that does not apply to it.
     *
     * @return array<int, string>
     */
    public function requiredContextKeys(): array
    {
        return match ($this) {
            self::SingleApp            => [],
            self::MultitenantShared    => ['central', 'shared', 'tenant_shared'],
            self::MultitenantPerTenant => ['central', 'shared', 'tenant_shared', 'tenant'],
        };
    }

    /**
     * Permission middleware alias guarding a route in the given context.
     *
     * @param  string|null  $contextKey  'central', 'tenant', 'tenant_shared'… — null in single-app
     */
    public function permissionMiddleware(?string $contextKey = null): string
    {
        if (! $this->hasContextAxis()) {
            return 'permission';
        }

        return $contextKey === 'central' ? 'central-permission' : 'tenant-permission';
    }

    /**
     * Prefix for permission names, snake_case, empty when none applies.
     *
     * Single app: invoices_index. Shared tenants: the context — tenant_roles_index.
     * A tenant with its own logic: its name — energy_spain_settlements_index.
     */
    public function permissionPrefix(?string $contextKey = null, ?string $tenantId = null): string
    {
        if (! $this->hasContextAxis()) {
            return '';
        }

        if ($this->namesTenant() && $tenantId !== null && $contextKey !== 'central') {
            return str_replace('-', '_', $tenantId) . '_';
        }

        return $contextKey === 'central' ? 'central_' : 'tenant_';
    }

    /** Human-readable label for console output. */
    public function label(): string
    {
        return match ($this) {
            self::SingleApp            => 'Aplicación única (sin tenancy)',
            self::MultitenantShared    => 'Multitenant — todos los tenants comparten funcionalidad',
            self::MultitenantPerTenant => 'Multitenant — cada tenant con lógica propia',
        };
    }
}
