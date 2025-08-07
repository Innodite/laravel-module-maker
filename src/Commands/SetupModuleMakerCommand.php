<?php

// Innodite\LaravelModuleMaker\Commands\SetupModuleMakerCommand.php
// Código corregido para el comando de configuración del paquete.

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
    protected $description = 'Configura el paquete, publicando stubs y un archivo de configuración de ejemplo.';

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

        // Publica los stubs y los archivos de configuración de ejemplo
        $this->publishStubsAndConfig($configPath);

        $this->info("\n🎉 ¡Configuración completa! Ahora puedes personalizar los stubs y el archivo de configuración en 'Modules/module-maker-config'.");
        $this->info("Para generar un módulo dinámico, edita 'post.json' y ejecuta: php artisan innodite:make-module --config=post.json");
    }

    /**
     * Publica los stubs y los archivos de configuración de ejemplo.
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

        // Copia los archivos de configuración de ejemplo
        $filesToCopy = ['post.json', 'blog.json','core.json','sales.json','shop.json'];
        foreach ($filesToCopy as $file) {
            $sourceFile = "{$packageStubsPath}/{$file}";
            $destinationFile = "{$configPath}/{$file}";

            if (File::exists($sourceFile)) {
                File::copy($sourceFile, $destinationFile);
                $this->info("✅ Archivo de configuración de ejemplo '{$file}' publicado en: '{$configPath}'.");
            } else {
                $this->error("El archivo de configuración de ejemplo '{$file}' no existe en: '{$sourceFile}'.");
            }
        }
    }
}