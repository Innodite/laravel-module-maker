<?php

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Components\ModuleGenerator;


class MakeModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'innodite:make-module {name}
                             {--model= : Nombre del modelo a crear}
                             {--controller= : Nombre del controlador a crear}
                             {--request= : Nombre del request a crear}
                             {--service= : Nombre del servicio a crear}
                             {--repository= : Nombre del repositorio a crear}
                             {--migration= : Nombre de la migración a crear}
                             {--config= : Archivo de configuración JSON para la generación dinámica}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un módulo base, un componente individual o un módulo dinámico.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $moduleName = Str::studly($this->argument('name'));
        $modulePath = config('make-module.module_path')."/{$moduleName}";

        // Lógica para crear componentes individuales
        if ($this->option('model') || $this->option('controller') || $this->option('request') || $this->option('service') || $this->option('repository') || $this->option('migration')) {
            if (!File::exists($modulePath)) {
                $this->error("El módulo '{$moduleName}' no existe. No se puede crear un componente en él.");
                return Command::FAILURE;
            }
            $generator = new ModuleGenerator($moduleName, true, null, $this); // isClean = true para componentes individuales
            $generator->createIndividualComponents($this->options());
            return Command::SUCCESS;
        }

        // Si el módulo ya existe y no se piden componentes individuales, mostramos un error
        if (File::exists($modulePath)) {
            $this->error("El módulo '{$moduleName}' ya existe. Usa las opciones para añadir componentes.");
            return Command::FAILURE;
        }

        // Lógica para crear un módulo completo (limpio o dinámico)
        $configPath = $this->option('config');
        if ($configPath) {
            $generator = new ModuleGenerator($moduleName, false, null, $this); // isClean = false para dinámico
            $resolvedConfigPath = $generator->resolveConfigPath($configPath);

            if (!File::exists($resolvedConfigPath)) {
                $this->error("El archivo de configuración '{$configPath}' no existe.");
                return Command::FAILURE;
            }

            $config = json_decode(File::get($resolvedConfigPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Error al parsear el archivo JSON: " . json_last_error_msg());
                return Command::FAILURE;
            }
            
            // Actualizar el nombre del módulo si viene en la configuración
            $generator = new ModuleGenerator($config['module_name'] ?? $moduleName, false, $config, $this);
            $generator->createDynamicModule();
        } else {
            $generator = new ModuleGenerator($moduleName, true, null, $this); // isClean = true para limpio
            $generator->createCleanModule();
        }

        return Command::SUCCESS;
    }
}
