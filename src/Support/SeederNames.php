<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

use InvalidArgumentException;

/**
 * SeederNames — cómo se llaman las piezas de seeder, decidido en un solo sitio.
 *
 * Por (sub)funcionalidad el patrón exige **seis piezas**: tres seeders ejecutables —Stage,
 * Production y Permissions— y tres traits —MigrationsList, InlineAlters y Data—. Y por módulo y
 * contexto, **tres maestros** `Application`, que no tienen esquema ni datos propios: solo hacen
 * fan-out en orden a las subfuncionalidades y propagan el modo destructivo hacia abajo. Son tres y
 * no seis por eso mismo.
 *
 * La cadena completa, de arriba abajo:
 *
 *   deploy-{contexto}
 *     └── {Prefijo}{Módulo}Application{Stage|Production|Permissions}Seeder     ← los 3 maestros
 *           └── {Prefijo}{Módulo}{SubFunc}{Stage|Production|Permissions}Seeder ← las 6 piezas
 *
 * Vive aparte de los generadores porque **el nombre lo van a necesitar varios**: el que escribe las
 * seis piezas, el maestro que las llama por su nombre de clase, el trait de migraciones y las
 * pruebas de despliegue. Cada uno recalculándolo por su cuenta es la receta exacta de B13, B15 y
 * B17 — dos mitades que dejan de coincidir y un archivo que no carga.
 *
 * Aquí solo viven los NOMBRES. El contenido de cada pieza —el `safe()` paso a paso, el
 * `reportErrors()` al cerrar, el upsert de producción, el truncate opt-in de stage— es de FEAT-003.
 */
final class SeederNames
{
    /** Las tres piezas ejecutables de una subfuncionalidad. */
    public const RUNNABLE = ['Stage', 'Production', 'Permissions'];

    /** Los tres traits que la acompañan: lista de migraciones, deltas con guardia y datos canónicos. */
    public const TRAITS = ['MigrationsList', 'InlineAlters', 'Data'];

    /** Carpeta de los maestros del módulo, dentro de Database/Seeders/{Contexto}/. */
    public const MASTER_FOLDER = 'Application';

    /**
     * Las 6 piezas de una subfuncionalidad, en el orden en que se leen.
     *
     * @param  string  $prefix      Prefijo del contexto ('Central', 'TenantShared'…) o '' en single-app
     * @return array<int, string>
     */
    public static function subFeaturePieces(string $prefix, string $module, string $subFeature): array
    {
        return array_map(
            fn (string $piece): string => self::piece($prefix, $module, $subFeature, $piece),
            [...self::RUNNABLE, ...self::TRAITS]
        );
    }

    /**
     * El nombre de **una** pieza — el sufijo `Seeder` lo pone quien sabe cuáles lo llevan.
     *
     * Existe porque cada generador escribe las suyas y ninguno debería recordar que los tres
     * ejecutables terminan en `Seeder` y los tres traits no: quien lo recuerde por su cuenta acabará
     * escribiendo un archivo `…Data.php` que declara `…DataSeeder`, y PSR-4 no lo encontrará.
     *
     * @param  string  $piece  Una de RUNNABLE o de TRAITS
     *
     * @throws InvalidArgumentException Si el nombre de pieza no es de los seis.
     */
    public static function piece(string $prefix, string $module, string $subFeature, string $piece): string
    {
        $base = $prefix . $module . $subFeature;

        if (in_array($piece, self::RUNNABLE, true)) {
            return "{$base}{$piece}Seeder";
        }

        if (in_array($piece, self::TRAITS, true)) {
            return "{$base}{$piece}";
        }

        throw new InvalidArgumentException(
            "'{$piece}' no es una de las seis piezas de una subfuncionalidad.\n"
            . 'Ejecutables: ' . implode(', ', self::RUNNABLE) . "\n"
            . 'Traits: ' . implode(', ', self::TRAITS)
        );
    }

    /**
     * La clase de una pieza a partir de **la carpeta** de su subfuncionalidad (P4).
     *
     * `'UserManagement/Central/Role'` + `'Stage'` →
     * `Modules\UserManagement\Database\Seeders\Central\Role\CentralUserManagementRoleStageSeeder`
     *
     * **Por qué la carpeta y no el nombre de clase.** El desarrollador declara el orden de despliegue
     * escribiendo rutas, no FQCNs de 90 caracteres: se escribe mucho menos y, sobre todo, **renombrar
     * una clase no rompe la lista**. Y el maestro que hace fan-out no nombra nunca a sus hijos —
     * los deriva de la carpeta y de **su propia pieza**, que es lo que hace imposible que el maestro
     * de producción invoque un seeder de stage.
     *
     * El prefijo de clase sale de los segmentos de contexto de la propia ruta —`Central` →
     * `Central`, `Tenant/Shared` → `TenantShared`—, que es la misma regla con la que se escribieron
     * los archivos: la carpeta y el prefijo espejan desde la fase 1.
     *
     * @param  string  $path   'Modulo/Contexto/SubFuncionalidad' — el contexto puede faltar (single-app)
     * @param  string  $piece  Una de RUNNABLE o de TRAITS
     *
     * @throws InvalidArgumentException Si la ruta no tiene al menos módulo y subfuncionalidad.
     */
    public static function classFromPath(string $path, string $piece): string
    {
        $segmentos = array_values(array_filter(
            explode('/', str_replace('\\', '/', trim($path))),
            static fn (string $segmento): bool => $segmento !== ''
        ));

        if (count($segmentos) < 2) {
            throw new InvalidArgumentException(
                "'{$path}' no nombra una subfuncionalidad.\n"
                . "Se espera 'Modulo/Contexto/SubFuncionalidad' —o 'Modulo/SubFuncionalidad' donde no "
                . 'hay eje de contexto—, que es la carpeta donde viven sus seis piezas.'
            );
        }

        $module     = array_shift($segmentos);
        $subFeature = array_pop($segmentos);
        $contexto   = $segmentos;   // lo que quede en medio; vacío en single-app

        $namespace = 'Modules\\' . $module . '\\Database\\Seeders'
            . ($contexto === [] ? '' : '\\' . implode('\\', $contexto))
            . '\\' . $subFeature;

        return $namespace . '\\' . self::piece(self::prefijoDeLaCarpeta($contexto), $module, $subFeature, $piece);
    }

    /**
     * El módulo al que pertenece una ruta de despliegue — el primer segmento.
     *
     * Lo usa el maestro para quedarse **solo con las subfuncionalidades suyas** dentro de una lista
     * que es del proyecto entero.
     */
    public static function moduleOfPath(string $path): string
    {
        $segmentos = explode('/', str_replace('\\', '/', trim($path, "/ \t\n\r\0\x0B")));

        return $segmentos[0] ?? '';
    }

    /**
     * El **maestro** que corresponde a una ruta de despliegue — un escalón por encima de `classFromPath()`.
     *
     * `'UserManagement/Central/Role'` + `'Stage'` →
     * `Modules\UserManagement\Database\Seeders\Central\Application\CentralUserManagementApplicationStageSeeder`
     *
     * Existe por la misma razón que su hermana, un nivel más arriba: el seeder de despliegue del
     * proyecto **tampoco nombra a sus hijos**. Deriva cada maestro de la carpeta declarada más su
     * propia pieza, así que un despliegue de producción no puede invocar el maestro de stage — el que
     * reconstruye desde cero. Y como la carpeta es la misma que declara el orden, no hay un segundo
     * listado de módulos que pueda quedarse atrás cuando aparezca el siguiente.
     *
     * La subfuncionalidad de la ruta se **descarta** a propósito: un maestro es del módulo y del
     * contexto, no de una subfuncionalidad. Dos rutas del mismo módulo y contexto resuelven al mismo
     * maestro, que es exactamente lo que permite deduplicarlas sin razonar sobre módulos.
     *
     * @param  string  $path   'Modulo/Contexto/SubFuncionalidad' — el contexto puede faltar (single-app)
     * @param  string  $piece  Una de RUNNABLE
     *
     * @throws InvalidArgumentException Si la ruta no tiene al menos módulo y subfuncionalidad.
     */
    public static function masterFromPath(string $path, string $piece): string
    {
        $segmentos = array_values(array_filter(
            explode('/', str_replace('\\', '/', trim($path))),
            static fn (string $segmento): bool => $segmento !== ''
        ));

        if (count($segmentos) < 2) {
            throw new InvalidArgumentException(
                "'{$path}' no nombra una subfuncionalidad.\n"
                . "Se espera 'Modulo/Contexto/SubFuncionalidad' —o 'Modulo/SubFuncionalidad' donde no "
                . 'hay eje de contexto—, que es de donde sale el módulo y el contexto de su maestro.'
            );
        }

        $module = array_shift($segmentos);
        array_pop($segmentos);      // la subfuncionalidad: el maestro es del módulo, no de ella
        $contexto = $segmentos;

        $namespace = 'Modules\\' . $module . '\\Database\\Seeders'
            . ($contexto === [] ? '' : '\\' . implode('\\', $contexto))
            . '\\' . self::MASTER_FOLDER;

        return $namespace . '\\' . self::masterFor(self::prefijoDeLaCarpeta($contexto), $module, $piece);
    }

    /**
     * El prefijo de clase que le corresponde a una carpeta de contexto.
     *
     * **Lo dice `contexts.json`, no la carpeta.** Pegar los segmentos parece equivalente, y lo es en
     * los dos contextos que el paquete venía probando —`Central` → `Central`, `Tenant/Shared` →
     * `TenantShared`—; por eso el defecto sobrevivió. Con un tenant de lógica propia deja de
     * coincidir, porque su `class_prefix` no repite el segmento padre:
     *
     *     carpeta `Tenant/TenantOne`   pegando segmentos → TenantTenantOne
     *                                  class_prefix real → TenantOne      ← el que se generó
     *
     * El resultado era que `innodite:deploy --context=tenant` **no encontraba un solo maestro** en
     * modo per-tenant: los derivaba con un nombre que el generador nunca escribió, y avisaba de que
     * «el módulo no se generó con este paquete». El módulo estaba perfectamente generado.
     *
     * El respaldo por concatenación se conserva para la carpeta que `contexts.json` no declara —un
     * proyecto que todavía no lo publicó, o una carpeta escrita a mano—: ahí el comportamiento sigue
     * siendo el de siempre, que es lo que esperan los proyectos existentes.
     *
     * @param  array<int, string>  $contexto  Segmentos de la carpeta, ya separados
     */
    private static function prefijoDeLaCarpeta(array $contexto): string
    {
        if ($contexto === []) {
            return '';
        }

        $declarado = ContextResolver::findByFolder(implode('/', $contexto));

        $prefijo = is_array($declarado) ? (string) ($declarado['class_prefix'] ?? '') : '';

        return $prefijo !== '' ? $prefijo : implode('', $contexto);
    }

    /**
     * El seeder de despliegue **del proyecto** que cubre un despliegue.
     *
     * `null` → `InnoditeDeploySeeder` · `'central'` → `InnoditeCentralDeploySeeder`
     *
     * Es del proyecto y no de un módulo: lo escribe el instalador, vive en `database/seeders/` y es
     * quien lee el orden de despliegue **de punta a punta** —cada maestro lee solo lo suyo—. Su
     * nombre está aquí, y no repartido entre el instalador, el comando y las pruebas, por lo de
     * siempre: tres sitios calculando el mismo nombre son tres sitios donde el día que cambie solo
     * cambiarán dos.
     *
     * @param  string|null  $deployKey  'central', 'tenant'… o `null` donde no hay eje de contexto
     */
    public static function projectDeploySeeder(?string $deployKey = null): string
    {
        if ($deployKey === null || trim($deployKey) === '') {
            return 'InnoditeDeploySeeder';
        }

        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', trim($deployKey))));

        return "Innodite{$studly}DeploySeeder";
    }

    /**
     * Los 3 maestros del módulo en un contexto.
     *
     * @return array<int, string>
     */
    public static function masterPieces(string $prefix, string $module): array
    {
        return array_map(
            fn (string $piece): string => "{$prefix}{$module}Application{$piece}Seeder",
            self::RUNNABLE
        );
    }

    /**
     * El maestro que corresponde a una pieza ejecutable.
     *
     * El maestro de Stage llama a los Stage de sus subfuncionalidades, el de Permissions a los
     * Permissions: la cadena no se cruza. Cruzarla haría que un despliegue de producción invocara
     * el seeder que reconstruye desde cero.
     */
    public static function masterFor(string $prefix, string $module, string $piece): string
    {
        return "{$prefix}{$module}Application{$piece}Seeder";
    }
}
