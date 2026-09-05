<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use Innodite\LaravelModuleMaker\Exceptions\ModeNotConfiguredException;

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
     * The configured mode.
     *
     * Deliberately has no fallback. Every other missing setting can be guessed at;
     * this one cannot, because the three modes are equally legitimate and the choice
     * shapes every generated file. A generator that quietly picks one produces a
     * module that looks right and is wrong where nobody looks — multiplied by every
     * module of every project. Refusing to run costs one message; guessing costs a
     * refactor.
     *
     * @throws ModeNotConfiguredException When the mode is absent or unknown
     */
    /**
     * ¿Hay modo elegido en este proyecto?
     *
     * La misma pregunta que `current()`, respondida sin excepción, para quien solo necesita saber si
     * el paquete está configurado — el aviso de primera instalación, por ejemplo. Vive aquí y no en
     * quien pregunta porque el modo es lo que decide si el paquete puede hacer algo: sin él, ningún
     * comando genera nada.
     */
    public static function isConfigured(): bool
    {
        try {
            self::current();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function current(): self
    {
        $configured = config('make-module.mode');

        if (! is_string($configured) || $configured === '') {
            throw ModeNotConfiguredException::missing();
        }

        return self::tryFrom($configured)
            ?? throw ModeNotConfiguredException::invalid(
                $configured,
                array_column(self::cases(), 'value')
            );
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
     * Does the generated model declare `protected $connection`?
     *
     * Three different answers, and the mode is the only thing that knows which applies:
     *
     *   single-app          no — there is one database and nothing to switch
     *   central context     yes, always — the central app has its own connection
     *   shared tenants      no — stancl switches it when it initialises the context, and the
     *                       route guarantees the isolation. Naming one here would bind the
     *                       model to a single tenant and break the very thing it protects
     *   tenant of its own   yes, its own
     *
     * @param  string|null  $contextKey  'central', 'tenant', 'tenant_shared'… — null in single-app
     */
    public function declaresModelConnection(?string $contextKey = null): bool
    {
        if (! $this->hasContextAxis()) {
            return false;
        }

        if ($contextKey === 'central') {
            return true;
        }

        return $this->requiresTenantConnectionKey();
    }

    /**
     * Is this context key legitimate in this mode?
     *
     * A single application has no contexts at all; a shared-tenant project has no *named*
     * tenants. Generating for `--context=tenant` under `multitenant-shared` would produce
     * exactly what that mode exists to avoid: one named copy per tenant of identical logic.
     */
    public function supportsContext(?string $contextKey): bool
    {
        if ($contextKey === null || $contextKey === '') {
            return ! $this->hasContextAxis();
        }

        return in_array($contextKey, $this->supportedContextKeys(), true);
    }

    /**
     * Context keys this mode ACCEPTS in `--context`.
     *
     * Wider than what the catalogue has to declare: a project may add contexts of its own, and
     * generating into one that exists is not an error just because the diagnostic did not demand it.
     *
     * @return array<int, string>
     */
    public function supportedContextKeys(): array
    {
        return match ($this) {
            self::SingleApp            => [],
            self::MultitenantShared    => ['central', 'shared', 'tenant', 'tenant_shared'],
            self::MultitenantPerTenant => ['central', 'shared', 'tenant_shared', 'tenant'],
        };
    }

    /**
     * Context keys that contexts.json must DECLARE for this mode.
     *
     * Not the same question as the one above, and answering both with one list is what made the
     * diagnostic demand `shared` and `tenant_shared` from *both* multitenant modes — the message
     * named the mode and then asked the same of either, so the distinction existed only on screen.
     *
     * What each mode actually needs:
     *
     *   · **shared tenants** — every tenant runs the SAME logic, each in its own database. There are
     *     no named tenants, so the axis is the generic `tenant`, and that is the whole catalogue
     *     besides `central`. Demanding `tenant_shared` here asks a project to declare a context for
     *     logic split per tenant, which is precisely what this mode does not have.
     *   · **logic per tenant** — each tenant has its own. `tenant_shared` is what they have in
     *     common, and the named tenants come from the project's own catalogue: the package cannot
     *     know how many there are or what they are called.
     *
     * A single application has no tenants to declare, so demanding a 'tenant' key would force it to
     * invent one to pass a diagnostic that does not apply to it.
     *
     * @return array<int, string>
     */
    public function requiredContextKeys(): array
    {
        return match ($this) {
            self::SingleApp            => [],
            self::MultitenantShared    => ['central', 'tenant'],
            self::MultitenantPerTenant => ['central', 'tenant_shared'],
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
