<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Innodite\LaravelModuleMaker\Commands\Concerns\PrintsHeader;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Innodite\LaravelModuleMaker\Services\MigrationPlanResolver;
use Innodite\LaravelModuleMaker\Services\MigrationTargetService;
use Innodite\LaravelModuleMaker\Support\LegacyManifests;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

/**
 * Ejecuta **una** migración, nombrada por su coordenada.
 *
 *     php artisan innodite:migrate-one Invoice:Central/2026_08_01_120000_crea_facturas.php
 *
 * **La base de datos sale de la coordenada, no de un manifiesto.** Antes había que decir además
 * contra qué archivo JSON se registraba, y de ese nombre se derivaba la conexión: pasar otro
 * `--manifest` ejecutaba contra otra base sin advertirlo. La coordenada ya lleva encima su carpeta
 * de contexto —`Central`, `Tenant/Shared`, `Tenant/Acme`—, que es el dato de verdad; `--context`
 * queda solo para forzarlo cuando haga falta.
 *
 * **Y ya no registra nada en ninguna parte.** El orden de las migraciones lo declara el trait
 * `MigrationsList` de cada subfuncionalidad, que se escribe al generar.
 */
class MigrateOneCommand extends Command
{
    use PrintsHeader;
    use ReportsFailures;

    protected $signature = 'innodite:migrate-one
        {coordinate : Coordenada de la migración: Modulo:Contexto/archivo.php}
        {--context= : Fuerza el contexto de ejecución en vez de derivarlo de la coordenada}
        {--force : Aplica sin pedir confirmación}
        {--dry-run : Ensayo: enseña qué migración aplicaría y contra qué conexión, sin tocar la base}';

    protected $description = 'Ejecuta una migración específica, contra la base de datos de su contexto.';

    public function handle(): int
    {
        $resolver   = new MigrationPlanResolver();
        $targets    = new MigrationTargetService();
        $coordenada = trim((string) $this->argument('coordinate'));
        $dryRun     = (bool) $this->option('dry-run');

        $this->cabecera('Una migración');

        LegacyManifests::notice($this);

        try {
            $resuelta = $resolver->resolveMigrationCoordinate($coordenada);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $contexto = trim((string) $this->option('context')) ?: $targets->extractContextPath($coordenada);

        try {
            $conexion = $targets->resolveExecutionConnection($contexto, $dryRun);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $baseDatos = $targets->resolveDatabaseName($conexion);

        $this->components->info("Migración: {$resuelta['file']}");
        $this->line('  Módulo:        ' . $resuelta['module']);
        $this->line('  Contexto:      ' . $contexto);
        $this->line('  Conexión:      ' . $conexion);
        $this->line('  Base de datos: ' . ($baseDatos !== '' ? $baseDatos : '[sin definir]'));
        $this->newLine();

        if ($dryRun) {
            $this->components->info('Dry-run completado. No se aplicó nada.');

            return self::SUCCESS;
        }

        $error = $targets->validateDatabaseExists($conexion);

        if ($error !== null) {
            $this->components->error($error);

            return self::FAILURE;
        }

        if (! $this->confirmar($conexion)) {
            $this->components->warn('Ejecución cancelada por el usuario.');

            return self::SUCCESS;
        }

        $codigo = $this->call('migrate', [
            // La coordenada ya se resolvió a ruta absoluta contra `module_path`, así que se pasa tal
            // cual con `--realpath`. Antes se convertía a relativa a `base_path()` —la raíz de la
            // que `migrate` parte por defecto—, y las dos solo coinciden mientras nadie mueva la
            // carpeta de módulos. El síntoma de mezclarlas es el peor posible: `migrate` no falla
            // por «archivo no encontrado», simplemente no aplica nada y devuelve éxito.
            '--path'     => $resuelta['path'],
            '--realpath' => true,
            '--database' => $conexion,
            '--force'    => true,
        ]);

        if ($codigo !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->components->info('Migración aplicada.');

        return self::SUCCESS;
    }

    private function confirmar(string $conexion): bool
    {
        if ((bool) $this->option('force')) {
            return true;
        }

        if (! $this->input instanceof InputInterface || ! $this->input->isInteractive()) {
            $this->fallo(
                'no hay consola con la que confirmar esta migración.',
                'pásale --force si quieres aplicarla sin preguntar.',
                'Una migración aplicada contra la conexión equivocada no se deshace leyendo el log.'
            );

            return false;
        }

        return (bool) $this->confirm("Se ejecutará la migración sobre '{$conexion}'. ¿Continuar?", false);
    }
}
