<?php

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupModuleMakerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'innodite:module-setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configura el paquete: crea la estructura de módulos y publica stubs y contexts.json.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $modulesPath = base_path('Modules');
        $configPath = "{$modulesPath}/module-maker-config";

        // Verifica si la carpeta 'Modules' existe y lo notifica
        if (File::exists($modulesPath)) {
            $this->info("✅ La carpeta 'Modules' ya existe en: {$modulesPath}.");
        } else {
            // Si no existe, la crea
            File::makeDirectory($modulesPath, 0755, true);
            $this->info("✅ Carpeta 'Modules' creada en: {$modulesPath}.");
        }

        // Verifica si la carpeta de configuración existe y lo notifica
        if (File::exists($configPath)) {
            $this->info("✅ La carpeta 'module-maker-config' ya existe en: {$configPath}.");
        } else {
            // Si no existe, la crea
            File::makeDirectory($configPath, 0755, true);
            $this->info("✅ Carpeta 'module-maker-config' creada en: {$configPath}.");
        }

        $this->publishStubsAndConfig($configPath);

        // NUEVO: Modifica el DatabaseSeeder automáticamente
        $this->modifyDatabaseSeeder();

        $this->info("\n🎉 ¡Configuración completa! Ahora puedes personalizar los stubs y el archivo de configuración en 'Modules/module-maker-config'.");
        $this->info("Edita 'Modules/module-maker-config/contexts.json' con los contextos y tenants de tu proyecto.");
        $this->info("Luego ejecuta: php artisan innodite:make-module NombreModulo");
    }

    /**
     * Publica los stubs y contexts.json en el proyecto.
     *
     * @param string $configPath
     * @return void
     */
    protected function publishStubsAndConfig(string $configPath): void
    {
        // Se define la ruta raíz del paquete de forma robusta
        // Asumimos que el archivo de comando está en 'src/Commands' y la carpeta de stubs en la raíz del paquete.
        $packageStubsPath = dirname(__DIR__, 2) . '/stubs';
        
        // Copia los stubs de 'clean'
        $stubsSourcePathClean = "{$packageStubsPath}/clean";
        $stubsDestinationPathClean = "{$configPath}/stubs/clean";
        if (File::isDirectory($stubsSourcePathClean)) {
            File::copyDirectory($stubsSourcePathClean, $stubsDestinationPathClean);
            $this->info("✅ Stubs limpios publicados en: '{$stubsDestinationPathClean}'.");
        } else {
            $this->error("El directorio de stubs de origen 'clean' no existe: '{$stubsSourcePathClean}'.");
        }

        // Copia los stubs de 'dynamic'
        $stubsSourcePathDynamic = "{$packageStubsPath}/dynamic";
        $stubsDestinationPathDynamic = "{$configPath}/stubs/dynamic";
        if (File::isDirectory($stubsSourcePathDynamic)) {
            File::copyDirectory($stubsSourcePathDynamic, $stubsDestinationPathDynamic);
            $this->info("✅ Stubs dinámicos publicados en: '{$stubsDestinationPathDynamic}'.");
        } else {
            $this->error("El directorio de stubs de origen 'dynamic' no existe: '{$stubsSourcePathDynamic}'.");
        }

        // Publica contexts.json — configura los contextos del proyecto (Central, Shared, tenants)
        $contextsSource      = "{$packageStubsPath}/contexts.json";
        $contextsDestination = "{$configPath}/contexts.json";

        if (! File::exists($contextsDestination)) {
            File::copy($contextsSource, $contextsDestination);
            $this->info("✅ contexts.json publicado en: '{$contextsDestination}'.");
            $this->info("   👉 Edita este archivo para configurar los contextos de tu proyecto.");
        } else {
            $this->warn("   contexts.json ya existe. No se sobreescribió. Edítalo manualmente si necesitas cambios.");
        }
    }
    
    /**
     * Modifica el archivo DatabaseSeeder.php para incluir los seeders de los módulos.
     *
     * @return void
     */
    protected function modifyDatabaseSeeder(): void
    {
        $seederPath = database_path('seeders/DatabaseSeeder.php');

        if (!File::exists($seederPath)) {
            $this->error("No se encontró el archivo DatabaseSeeder.php. Por favor, asegúrate de que el proyecto está inicializado correctamente.");
            return;
        }

        $seederContent = File::get($seederPath);
        $callLine = "        \$this->call(InnoditeModuleSeeder::class);";
        $useStatement = "use Innodite\\LaravelModuleMaker\\Database\\Seeders\\InnoditeModuleSeeder;";
        
        // Revisa si ya existe el use statement o la llamada para no duplicar
        if (str_contains($seederContent, $useStatement) && str_contains($seederContent, $callLine)) {
            $this->warn("El archivo DatabaseSeeder.php ya está configurado para ejecutar los seeders de los módulos. No se realizaron cambios.");
            return;
        }

        // Inyecta el use statement si no existe
        if (!str_contains($seederContent, $useStatement)) {
            $seederContent = str_replace(
                "use Illuminate\\Database\\Seeder;",
                "use Illuminate\\Database\\Seeder;\n{$useStatement}",
                $seederContent
            );
        }
        
        // Inyecta la llamada si no existe
        if (!str_contains($seederContent, $callLine)) {
            $comment = "        // Código generado por LaravelModuleMaker para ejecutar los seeders de los módulos";
            $seederContent = str_replace(
                "public function run(): void\n    {\n",
                "public function run(): void\n    {\n" . $comment . "\n" . $callLine . "\n",
                $seederContent
            );
        }

        File::put($seederPath, $seederContent);
        $this->info("✅ Archivo DatabaseSeeder.php modificado para incluir los seeders de los módulos. ¡Revisa el archivo!");
    }
}