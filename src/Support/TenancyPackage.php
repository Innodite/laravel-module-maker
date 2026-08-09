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
     * An unrecognised value throws, exactly like {@see PrimaryKeyMode::current()} — and that is
     * deliberate even though the set of tenancy packages will grow. Today it has two members and
     * only two, so `tenancy-for-laravel` in the configuration is not "a package we support
     * silently doing nothing": it is a declaration the package cannot honour. Treating it as
     * {@see self::None} would hand the developer route files with no wrapper while their
     * configuration says otherwise — the exact shape of every defect this package has spent six
     * phases removing: two halves that are each plausible and point somewhere else.
     *
     * Absent is a different case and does NOT throw: it means "I did not choose", and the answer
     * to that is {@see self::None} plus a note in the generated file. When another package gets
     * wired in, it becomes a case here and the message lists it on its own.
     */
    public static function current(): self
    {
        $declarado = self::declaredValue();

        if ($declarado === '') {
            return self::None;
        }

        return self::tryFrom($declarado)
            ?? throw new \InvalidArgumentException(
                "FALLA: el paquete de tenencia '{$declarado}' no está soportado. · FIX: usa "
                . self::valoresValidos() . ' en config/make-module.php. Con «none» las rutas se '
                . 'generan sin envoltura y el archivo dice dónde va la tuya.'
            );
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
        // Un valor no soportado ya no llega hasta aquí: lo rechaza `current()`. El único caso que
        // queda es el declarado a propósito — «ninguno de los que soportas», que es una respuesta
        // legítima mientras el paquete solo sepa envolver con stancl.
        $motivo = 'el proyecto declara que no usa ninguno de los paquetes de tenencia soportados';

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

    /** The supported values, as the error message lists them. */
    private static function valoresValidos(): string
    {
        return "'" . implode("' o '", array_column(self::cases(), 'value')) . "'";
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
            static fn (string $linea): string => $linea === '' ? '' : $relleno . $linea,
            explode("\n", $texto)
        ));
    }
}
