<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
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
    protected $signature = 'innodite:migrate-one
        {coordinate : Coordenada de la migración: Modulo:Contexto/archivo.php}
        {--context= : Fuerza el contexto de ejecución en vez de derivarlo de la coordenada}
        {--yes : Confirma automáticamente la ejecución}
        {--dry-run : Muestra lo que haría sin ejecutar}';

    protected $description = 'Ejecuta una migración específica, contra la base de datos de su contexto.';

    public function handle(): int
    {
        $resolver   = new MigrationPlanResolver();
        $targets    = new MigrationTargetService();
        $coordenada = trim((string) $this->argument('coordinate'));
        $dryRun     = (bool) $this->option('dry-run');

        $this->newLine();
        $this->line('  <fg=blue;options=bold>Innodite ModuleMaker — Migrate One</>');
        $this->newLine();

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
            '--path'     => $this->rutaRelativa($resuelta['path']),
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
        if ((bool) $this->option('yes')) {
            return true;
        }

        if (! $this->input instanceof InputInterface || ! $this->input->isInteractive()) {
            $this->components->error('Este comando requiere confirmación interactiva. Usa --yes si deseas omitirla.');

            return false;
        }

        return (bool) $this->confirm("Se ejecutará la migración sobre '{$conexion}'. ¿Continuar?", false);
    }

    /** `migrate --path` espera la ruta relativa a la raíz del proyecto. */
    private function rutaRelativa(string $absoluta): string
    {
        $raiz = rtrim(base_path(), '/\\') . '/';

        return str_starts_with($absoluta, $raiz) ? substr($absoluta, strlen($raiz)) : $absoluta;
    }
}
