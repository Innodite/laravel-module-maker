<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

/**
 * The tenancy package the host project runs on.
 *
 * The package generates route files for multi-tenant projects, and those files need a
 * wrapper: the central application serves one domain per declared central domain, and a
 * tenant route has to identify its tenant before anything else runs. That wrapper is not
 * generic — it is written in the vocabulary of whichever tenancy package the project uses.
 *
 * So the project declares it, exactly as it declares the mode. This is {@see ModuleMode}'s
 * rule one level down: the package does not sniff the vendor folder to find out. Only
 * `stancl` is supported today; anything else means the wrapper is left to the developer,
 * with a note in the generated file saying where it goes and what it would look like.
 *
 * ⛔ The asymmetry is why {@see self::None} is the default and `stancl` is not. Writing a
 * stancl wrapper into a project that does not have stancl produces a route file referencing
 * classes that do not exist — the application stops booting. Leaving the wrapper out leaves
 * a visible note in a file that still parses. One failure mode is loud and recoverable; the
 * other takes down everything.
 */
enum TenancyPackage: string
{
    /** stancl/tenancy v3.10 — the only one wired in so far. */
    case Stancl = 'stancl';

    /** No supported package: the wrapper is the developer's, and the file says where it goes. */
    case None = 'none';

    /**
     * The configured tenancy package.
     *
     * Unlike {@see PrimaryKeyMode::current()}, an unrecognised value does NOT throw. The set of
     * primary keys is closed — there are two and there will be two — while the set of tenancy
     * packages is open: a project running one the package has not wired in yet is a legitimate
     * project, not a mistake. It gets the same treatment as declaring none, and
     * {@see self::unsupportedValue()} is what lets the caller say so out loud instead of
     * silently ignoring the declaration.
     */
    public static function current(): self
    {
        $declarado = self::declaredValue();

        if ($declarado === '') {
            return self::None;
        }

        return self::tryFrom($declarado) ?? self::None;
    }

    /**
     * The declared value when it names a package we do not support yet — null otherwise.
     *
     * Absent means "I did not choose"; unrecognised means "I chose and you do not know it".
     * The second one deserves a warning: without it the developer declares `tenancy-for-laravel`
     * and gets route files with no wrapper and no explanation of why.
     */
    public static function unsupportedValue(): ?string
    {
        $declarado = self::declaredValue();

        if ($declarado === '' || self::tryFrom($declarado) !== null) {
            return null;
        }

        return $declarado;
    }

    /** Does this package know how to wrap the generated route files? */
    public function wrapsRoutes(): bool
    {
        return $this === self::Stancl;
    }

    /**
     * Middleware that identifies the tenant, for every block written into `tenant.php`.
     *
     * Identification by domain. stancl also ships `BySubdomain` and `ByDomainOrSubdomain`;
     * a project serving its tenants on subdomains declares that too, rather than the package
     * settling it by silent convention — which is the one thing rule 5 forbids.
     *
     * The `::class` entries are written as PHP expressions, not quoted strings, which is why
     * {@see self::routeImports()} exists: the file that uses them has to import them.
     *
     * @return array<int, string>
     */
    public function tenantMiddleware(): array
    {
        return match ($this) {
            self::Stancl => [
                'web',
                'InitializeTenancyByDomain::class',
                'PreventAccessFromCentralDomains::class',
            ],
            self::None => [],
        };
    }

    /**
     * Fully qualified names the generated route file must import.
     *
     * @param  string  $routeFile  'web.php' or 'tenant.php' — only tenant routes need imports
     * @return array<int, string>
     */
    public function routeImports(string $routeFile): array
    {
        if ($this !== self::Stancl || $routeFile !== 'tenant.php') {
            return [];
        }

        return [
            'Stancl\\Tenancy\\Middleware\\InitializeTenancyByDomain',
            'Stancl\\Tenancy\\Middleware\\PreventAccessFromCentralDomains',
        ];
    }

    /**
     * Wraps the central application's routes, one group per declared central domain.
     *
     * @param  string  $interior  The route block plus its marker, already assembled
     */
    public function wrapCentralRoutes(string $interior): string
    {
        if (! $this->wrapsRoutes()) {
            return $interior;
        }

        $dentro = self::indent($interior, 8);

        return <<<PHP
        // Rutas de la aplicación central: una por cada dominio central declarado.
        // En local eso es `localhost`; en stage y producción, el dominio de la central.
        foreach (config('tenancy.central_domains') as \$dominio) {
            Route::domain(\$dominio)->middleware('web')->group(function () {

        {$dentro}
            });
        }
        PHP;
    }

    /**
     * The note left in place of a wrapper this package cannot write.
     *
     * D8: with no supported package the file is written **without** the wrapper and with a
     * comment saying where it goes and why. Not an invented generic wrapper that serves
     * nobody, and not silence — silence is how a tenant route ends up served on every domain.
     *
     * @param  string  $routeFile  'web.php' or 'tenant.php'
     */
    public function missingWrapperNote(string $routeFile): string
    {
        $declarado = self::unsupportedValue();
        $motivo = $declarado === null
            ? 'el proyecto no declara ningún paquete de tenencia'
            : "el paquete de tenencia declarado ('{$declarado}') todavía no está soportado";

        if ($routeFile === 'tenant.php') {
            return <<<PHP
            // ⛔ FALTA LA ENVOLTURA QUE IDENTIFICA AL TENANT — la escribes tú, porque {$motivo}
            //    (`tenancy.package` en config/make-module.php).
            //    FIX: envuelve este bloque con la identificación de tu paquete antes de servirlo. Con
            //    stancl/tenancy serían los middleware InitializeTenancyByDomain y
            //    PreventAccessFromCentralDomains. Sin ella, estas rutas se sirven en cualquier dominio
            //    y contra la base que estuviera conectada.
            PHP;
        }

        return <<<PHP
        // ⛔ FALTA LA ENVOLTURA DE LOS DOMINIOS CENTRALES — la escribes tú, porque {$motivo}
        //    (`tenancy.package` en config/make-module.php).
        //    FIX: sirve este bloque solo en los dominios de la aplicación central. Con stancl/tenancy
        //    sería un foreach sobre config('tenancy.central_domains') envolviendo cada grupo en
        //    Route::domain(). Sin ella, las rutas de la central responden también en el dominio de
        //    cada cliente.
        PHP;
    }

    /** Human-readable label for console output. */
    public function label(): string
    {
        return match ($this) {
            self::Stancl => 'stancl/tenancy (identificación por dominio)',
            self::None => 'Ninguno soportado — la envoltura de las rutas la escribo yo',
        };
    }

    /** The raw declared value, trimmed; empty string when absent or not a string. */
    private static function declaredValue(): string
    {
        $configurado = config('make-module.tenancy.package');

        return is_string($configurado) ? trim($configurado) : '';
    }

    /** Indents every non-empty line, so the generated file reads like handwritten code. */
    private static function indent(string $texto, int $espacios): string
    {
        $relleno = str_repeat(' ', $espacios);

        return implode("\n", array_map(
            static fn (string $linea): string => $linea === '' ? '' : $relleno.$linea,
            explode("\n", $texto)
        ));
    }
}
