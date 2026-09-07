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

    /**
     * Many tenants. Two contexts and only two: the central application and the tenant.
     *
     * ⛔ There used to be two multi-tenant modes — `multitenant-shared` and
     * `multitenant-per-tenant` — and the whole difference between them was that the second one
     * NAMED each tenant and gave it its own connection. Both halves of that difference turned out
     * to be wrong: naming a tenant multiplies identical logic by client, and declaring the
     * connection binds a model to one database when the tenancy package switches it per request.
     * With neither half left, the two modes generated the same thing under different labels, and a
     * choice with no consequence is exactly what rule 5 of this package exists to prevent.
     *
     * A project that genuinely needs a context of its own declares it in ITS catalogue. That is a
     * project's decision, not one of the generator's modes.
     */
    case Multitenant = 'multitenant';

    /**
     * Modes that used to exist, and what to write instead.
     *
     * They are named here on purpose: the alternative is a bare "unknown mode" while the
     * configuration says something that was legitimate last week. Silently translating them to
     * {@see self::Multitenant} would be worse — a project declaring `multitenant-per-tenant` chose
     * named tenants, and it deserves to be told that the answer is now a context of its own,
     * rather than to discover it in the generated tree.
     *
     * @var array<string, string>
     */
    public const RETIRADOS = [
        'multitenant-shared'     => 'multitenant',
        'multitenant-per-tenant' => 'multitenant',
    ];

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

        if (array_key_exists($configured, self::RETIRADOS)) {
            throw ModeNotConfiguredException::retired($configured, self::RETIRADOS[$configured]);
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
     * Does the generated model declare `protected $connection`?
     *
     * Three different answers, and the mode is the only thing that knows which applies:
     *
     *   single-app       no — there is one database and nothing to switch
     *   central context  yes, always — the central app has its own connection
     *   tenant context   NEVER — the tenancy package switches it when the middleware identifies
     *                    the tenant, and the route guarantees the isolation. Naming one here binds
     *                    the model to a single client and breaks the very thing it protects
     *
     * @param  string|null  $contextKey  'central', 'tenant'… — null in single-app
     */
    public function declaresModelConnection(?string $contextKey = null): bool
    {
        if (! $this->hasContextAxis()) {
            return false;
        }

        return $contextKey === 'central';
    }

    /**
     * Is this context key legitimate in this mode?
     *
     * A single application has no contexts at all. A multi-tenant project has the two of the
     * catalogue plus whatever it declares itself.
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
     * ⛔ These are the FACTORY keys, and this method cannot be the whole answer: a project may add
     * contexts of its own, and this enum has no way of knowing about them. Whether a given key can
     * be generated into is decided by {@see \Innodite\LaravelModuleMaker\Support\ContextOption},
     * which has the project's catalogue in front of it — a key that the catalogue declares is valid
     * even though this list does not name it.
     *
     * Saying otherwise here is not a wording detail: while this method was treated as the whole
     * answer, the package rejected every context a project declared, which is the one thing the
     * catalogue's own README promises it can do.
     *
     * @return array<int, string>
     */
    public function supportedContextKeys(): array
    {
        return match ($this) {
            self::SingleApp    => [],
            self::Multitenant  => ['central', 'tenant'],
        };
    }

    /**
     * Context keys that contexts.json must DECLARE for this mode.
     *
     * The same two the mode accepts, because there are only two. This used to be a different
     * question from {@see self::supportedContextKeys()} — one mode had to declare `tenant_shared`
     * and the other `tenant`, and telling them apart was most of the reason this method existed.
     * With one multi-tenant mode and one tenant context, both answers are the same list, and
     * keeping two ways to compute it is how the two halves of this package usually drift apart.
     *
     * A single application has no tenants to declare, so demanding a 'tenant' key would force it
     * to invent one to pass a diagnostic that does not apply to it.
     *
     * @return array<int, string>
     */
    public function requiredContextKeys(): array
    {
        return $this->supportedContextKeys();
    }

    /**
     * Permission middleware alias guarding a route in the given context.
     *
     * @param  string|null  $contextKey  'central', 'tenant'… — null in single-app
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
     * Single app: invoices_index. Multi-tenant: the context — central_roles_index,
     * tenant_roles_index.
     *
     * ⛔ Never the tenant's name. A permission called `energy_spain_settlements_index` has to be
     * seeded once per client and renamed the day the client is renamed; `tenant_settlements_index`
     * is seeded once, inside each tenant's own database, where it is already unambiguous.
     */
    public function permissionPrefix(?string $contextKey = null): string
    {
        if (! $this->hasContextAxis()) {
            return '';
        }

        return $contextKey === 'central' ? 'central_' : 'tenant_';
    }

    /** Human-readable label for console output. */
    public function label(): string
    {
        return match ($this) {
            self::SingleApp   => 'Aplicación única (sin tenancy)',
            self::Multitenant => 'Multiinquilino — aplicación central e inquilinos',
        };
    }
}
