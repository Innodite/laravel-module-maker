<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Innodite\LaravelModuleMaker\Services\MigrationPlanResolver;
use Innodite\LaravelModuleMaker\Services\MigrationTargetService;
use Innodite\LaravelModuleMaker\Support\LegacyManifests;
use Throwable;

/**
 * Aplica el esquema del proyecto: las migraciones de los traits, en el orden que ellos declaran.
 *
 * **El orden ya no lo declara un JSON.** Lo declara cada subfuncionalidad en su trait
 * `MigrationsList`, así que viaja con el módulo cuando alguien lo copia a otro proyecto — que es
 * justo lo que el manifiesto no hacía, y por eso se desincronizaba en silencio.
 *
 *     php artisan innodite:migrate-plan --context=central
 *     php artisan innodite:migrate-plan --context=central --dry-run
 *
 * **Solo el esquema.** Los datos y los permisos son del despliegue —`innodite:deploy`—, que además
 * llama a estas mismas migraciones desde cada seeder. Este comando existe para el caso en que se
 * quiera levantar la estructura sin sembrar nada: un servidor nuevo, o una revisión previa.
 */
class MigratePlanCommand extends Command
{
    use ReportsFailures;

    protected $signature = 'innodite:migrate-plan
        {--context= : Contexto contra el que ejecutar: central | shared | tenant_shared | id del tenant}
        {--dry-run : Muestra el plan sin ejecutar nada}';

    protected $description = 'Aplica las migraciones del proyecto en el orden que declaran sus traits MigrationsList.';

    public function handle(): int
    {
        $resolver = new MigrationPlanResolver();
        $targets  = new MigrationTargetService();
        $dryRun   = (bool) $this->option('dry-run');
        $contexto = trim((string) $this->option('context'));

        $this->newLine();
        $this->line('  <fg=blue;options=bold>Innodite ModuleMaker — Migrate Plan</>');
        $this->newLine();

        LegacyManifests::notice($this);

        if ($contexto === '') {
            $this->fallo(
                'falta --context: es lo que dice contra qué base de datos se ejecuta.',
                'pásalo — --context=central · --context=tenant_shared · --context=acme',
                'Sin él no hay forma de saber qué base recibe el plan, y aplicarlo en la que no era '
                . 'no se deshace solo.'
            );

            return self::FAILURE;
        }

        // El filtro de carpeta y la conexión salen del MISMO contexto resuelto: 'central' selecciona
        // las migraciones de Central/ y la conexión del contexto central. Derivarlos por separado es
        // lo que permitiría migrar las de un contexto contra la base de datos de otro.
        try {
            $carpeta = $targets->folderOf($contexto);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $migraciones = $resolver->migrationsFromTraits($carpeta);

        if ($migraciones === []) {
            $this->components->warn(
                "No hay ninguna migración declarada para '{$contexto}'.\n"
                . '  Cada subfuncionalidad declara las suyas en su trait MigrationsList; si acabas de '
                . 'generarla, comprueba que el contexto es el que le corresponde.'
            );

            return self::SUCCESS;
        }

        try {
            $conexion = $targets->resolveExecutionConnection($contexto, $dryRun);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Contexto: ' . $contexto);
        $this->line('  Conexión:      ' . $conexion);
        $this->line('  Base de datos: ' . ($targets->resolveDatabaseName($conexion) ?: '[sin definir]'));
        $this->line('  Migraciones:   ' . count($migraciones));
        $this->newLine();

        foreach ($migraciones as $i => $migracion) {
            $this->line('  ' . ($i + 1) . '. ' . $migracion);
        }

        $this->newLine();

        if ($dryRun) {
            $this->components->info('Dry-run completado. No se aplicó ninguna migración.');

            return self::SUCCESS;
        }

        $error = $targets->validateDatabaseExists($conexion);

        if ($error !== null) {
            $this->components->error($error);

            return self::FAILURE;
        }

        return $this->aplicar($migraciones, $conexion, $resolver);
    }

    /**
     * @param  array<int, string>  $migraciones
     */
    private function aplicar(array $migraciones, string $conexion, MigrationPlanResolver $resolver): int
    {
        foreach ($migraciones as $migracion) {
            // Absoluta y con `--realpath`, no relativa: el trait se encontró recorriendo
            // `module_path` y `migrate --path` resolvería contra `base_path()`, que son dos raíces
            // distintas en cuanto alguien mueve la carpeta de módulos. Ver `absolutePathOf()`.
            $codigo = $this->call('migrate', [
                '--path'     => $resolver->absolutePathOf($migracion),
                '--realpath' => true,
                '--database' => $conexion,
                '--force'    => true,
            ]);

            if ($codigo !== self::SUCCESS) {
                $this->fallo(
                    "falló la migración {$migracion}.",
                    'corrígela y vuelve a lanzar el plan: las anteriores ya están aplicadas y son '
                    . 'idempotentes.',
                    'El plan se detiene aquí para no dejar la base a medio migrar sin avisar.'
                );

                return self::FAILURE;
            }
        }

        $this->components->info('Migraciones aplicadas.');

        return self::SUCCESS;
    }
}
