<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

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
        $base = $prefix . $module . $subFeature;

        return [
            ...array_map(fn (string $piece): string => "{$base}{$piece}Seeder", self::RUNNABLE),
            ...array_map(fn (string $trait): string => "{$base}{$trait}", self::TRAITS),
        ];
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
