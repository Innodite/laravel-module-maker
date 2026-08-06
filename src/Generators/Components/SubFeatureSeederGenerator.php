<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\DeployOrderInjectionService;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Innodite\LaravelModuleMaker\Support\SubFeaturePermissions;

/**
 * Escribe las piezas de seeder de UNA subfuncionalidad.
 *
 * **Cuatro de las seis.** Las otras dos —`MigrationsList` e `InlineAlters`— las escribe el generador
 * de migraciones, y ahí es donde tienen que estar: la lista se **deriva de la carpeta** de
 * migraciones en cada generación, que es lo que impide que quede desfasada. Aquí salen las tres
 * ejecutables y el trait de datos canónicos.
 *
 * **Reparto deliberado (riesgo de God Class, declarado en el plan).** Este generador escribe piezas
 * de subfuncionalidad y nada más: los tres maestros `Application` del módulo son otro generador.
 * Juntarlos daría el archivo que nadie quiere tocar, y son dos trabajos distintos — estas piezas
 * conocen tablas, permisos y datos; los maestros solo saben en qué orden llamar a sus hijos.
 *
 * **Ninguna se sobrescribe si ya existe.** Dentro de un seeder vive código escrito a mano: el paso
 * propio que alguien añadió a `run()`, las filas canónicas del negocio, el delta de la semana
 * pasada. Regenerar el módulo no puede llevárselo por delante. Cuando la pieza ya está, se avisa y
 * se sigue — igual que hacen la migración y el trait de deltas.
 */
class SubFeatureSeederGenerator extends AbstractComponentGenerator
{
    /**
     * @param  array<string, mixed>  $componentConfig
     */
    public function __construct(string $moduleName, string $modulePath, bool $isClean, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
    }

    public function generate(): void
    {
        $subFeature = $this->getSubFeatureFolder();

        if ($subFeature === '') {
            // Sin subfuncionalidad no hay grupo de seis piezas al que pertenecer — mismo criterio
            // que aplica el generador de migraciones con los dos traits.
            return;
        }

        $seederDir = $this->buildPath('Database/Seeders');

        $this->ensureDirectoryExists($seederDir);

        $prefijo = $this->getClassPrefix();

        $comunes = [
            'namespace'           => $this->buildNamespace('Database\\Seeders'),
            'subFeature'          => $subFeature,
            'connection'          => $this->connectionLiteral(),
            'tables'              => $this->tablesLiteral(),
            'migrationsListTrait' => SeederNames::piece($prefijo, $this->moduleName, $subFeature, 'MigrationsList'),
            'inlineAltersTrait'   => SeederNames::piece($prefijo, $this->moduleName, $subFeature, 'InlineAlters'),
            'dataTrait'           => SeederNames::piece($prefijo, $this->moduleName, $subFeature, 'Data'),
            'permissionsSeeder'   => SeederNames::piece($prefijo, $this->moduleName, $subFeature, 'Permissions'),
        ];

        $this->writePiece($seederDir, 'stage-seeder.stub', SeederNames::piece($prefijo, $this->moduleName, $subFeature, 'Stage'), $comunes);
        $this->writePiece($seederDir, 'production-seeder.stub', SeederNames::piece($prefijo, $this->moduleName, $subFeature, 'Production'), $comunes);

        $this->writePiece(
            $seederDir,
            'permissions-seeder.stub',
            $comunes['permissionsSeeder'],
            $comunes + [
                'moduleLabel' => SubFeaturePermissions::moduleLabel($this->moduleName, $subFeature),
                'permissions' => $this->permissionsLiteral($subFeature),
            ]
        );

        $this->writeDataTrait($seederDir, $comunes['dataTrait'], $comunes['namespace'], $subFeature);

        $this->registerInDeployOrder($subFeature);
    }

    /**
     * Declara la subfuncionalidad en el orden de despliegue del proyecto (P4).
     *
     * **Generar declara.** El desfase entre lo generado y lo desplegado no rompe ningún archivo: deja
     * la aplicación a medio levantar, con una pantalla que no abre nadie porque su seeder de permisos
     * nunca se ejecutó. Y es el desfase más fácil de producir, porque generar y declarar son dos
     * actos separados por días.
     *
     * Se añade **al final**: el paquete sabe que la subfuncionalidad existe, pero no si va antes o
     * después de otra — eso depende de qué tabla apunta a cuál, y lo sabe el negocio.
     */
    protected function registerInDeployOrder(string $subFeature): void
    {
        $carpeta = $this->getContextFolder();
        $ruta    = $this->moduleName . '/' . ($carpeta ? "{$carpeta}/" : '') . $subFeature;

        $contexto = $this->mode()->hasContextAxis()
            ? (($this->componentConfig['context'] ?? '') ?: null)
            : null;

        (new DeployOrderInjectionService($this))->register($ruta, $contexto);
    }

    /**
     * Escribe una pieza ejecutable, si no está ya escrita.
     *
     * @param  array<string, mixed>  $placeholders
     */
    protected function writePiece(string $seederDir, string $stub, string $className, array $placeholders): void
    {
        $destino = "{$seederDir}/{$className}.php";

        if (File::exists($destino)) {
            $this->warn("Seeder '{$className}' ya existe. Se omite para no pisar lo que tenga dentro.");

            return;
        }

        $contenido = $this->getStubContent($stub, $this->isClean, $placeholders + ['seederName' => $className]);

        $this->putFile($destino, $contenido, "Seeder '{$className}' creado.");
    }

    /**
     * Escribe el trait de datos canónicos — vacío y con su estructura.
     *
     * Igual que `InlineAlters`: nace sin contenido porque el paquete no conoce los datos del
     * negocio, y lleva dentro el patrón escrito para que las primeras filas se añadan bien.
     */
    protected function writeDataTrait(string $seederDir, string $traitName, string $namespace, string $subFeature): void
    {
        $destino = "{$seederDir}/{$traitName}.php";

        if (File::exists($destino)) {
            return;   // dentro viven las filas canónicas del negocio
        }

        $contenido = $this->getStubContent('data-trait.stub', $this->isClean, [
            'namespace'  => $namespace,
            'traitName'  => $traitName,
            'subFeature' => $subFeature,
            'tableName'  => $this->tableName(),
        ]);

        $this->putFile($destino, $contenido, "Datos canónicos creados: {$traitName}.php");
    }

    // ─── Los valores que los stubs esperan escritos como PHP ──────────────────

    /**
     * La conexión, tal cual va en la propiedad: `null` o `'central'`.
     *
     * Sale de `connectionKey()`, el mismo sitio del que la toma el modelo. Un modelo leyendo de una
     * base y su seeder sembrando en otra es el resultado de calcularla dos veces.
     */
    protected function connectionLiteral(): string
    {
        $conexion = $this->connectionKey();

        return $conexion === null ? 'null' : "'{$conexion}'";
    }

    /**
     * Las tablas de la subfuncionalidad, como elementos de un array PHP.
     *
     * Hoy es una: la que crea la migración de esta subfuncionalidad. Cuando el desarrollador añada
     * más, las añade aquí **de dependiente a padre** — ese orden es el del vaciado.
     */
    protected function tablesLiteral(): string
    {
        return "\n        '" . $this->tableName() . "',\n    ";
    }

    /**
     * Los permisos, escritos como el array que el seeder devuelve.
     *
     * Salen de `SubFeaturePermissions` con **el mismo prefijo** que usa el generador de rutas para
     * el `->middleware()` de cada una: es la misma pregunta hecha al mismo sitio. Cuando se hacía
     * dos veces, las rutas exigían permisos que el seeder no creaba.
     *
     * Se separan los de ruta y los de vista con un rótulo porque son **dos protecciones
     * independientes** (R20), y quien abra el archivo tiene que poder ver cuál es cuál.
     */
    protected function permissionsLiteral(string $subFeature): string
    {
        $permisos = SubFeaturePermissions::permissions(
            $this->permissionPrefix(),
            $this->getFunctionality(),
            $this->moduleName,
            $subFeature
        );

        // La sangría es la del cuerpo de un método: 12 para los elementos, 8 para el cierre. Lo que
        // el paquete escribe se lee tanto como lo que se escribe a mano.
        $lineas = [];
        $tipo   = null;

        foreach ($permisos as $permiso) {
            if ($permiso['kind'] !== $tipo) {
                $tipo     = $permiso['kind'];
                $lineas[] = $tipo === 'ruta'
                    ? "\n            // ── Permisos de RUTA: protegen el servicio ──────────────────────"
                    : "\n\n            // ── Permisos de VISTA: protegen el elemento visual (R20) ───────";
            }

            $lineas[] = "\n            [\n"
                . "                'name'        => '{$permiso['name']}',\n"
                . "                'description' => '" . str_replace("'", "\\'", $permiso['description']) . "',\n"
                . "                'module'      => '" . str_replace("'", "\\'", $permiso['module']) . "',\n"
                . '            ],';
        }

        return implode('', $lineas) . "\n        ";
    }
}
