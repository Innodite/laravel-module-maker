<?php

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Illuminate\Support\Facades\File;

class SeederGenerator extends AbstractComponentGenerator
{
    protected const SEEDER_PATH_SUFFIX = "/Database/Seeders";
    protected const SEEDER_FILE_SUFFIX = "Seeder.php";
    protected const STUB_MAIN_SEEDER = 'main_seeder.stub';
    protected const STUB_TABLE_SEEDER = 'table_seeder.stub';
    protected const DEFAULT_FACTORY_COUNT = 50;

    protected string $seederName;
    protected string $modelName;

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $seederName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        
        $this->seederName = Str::studly($seederName);
        $this->modelName = $componentConfig['name'] ?? Str::studly(str_replace('Seeder', '', $this->seederName));
    }

    public function generate(): void
    {
        $seederDir = $this->getComponentBasePath() . self::SEEDER_PATH_SUFFIX;
        $this->ensureDirectoryExists($seederDir);

        // Genera el seeder principal del módulo si no existe
        $this->generateMainModuleSeeder($seederDir);

        // Genera el seeder de la tabla específica
        $this->generateTableSeeder($seederDir);
    }

    protected function generateMainModuleSeeder(string $seederDir): void
    {
        $mainSeederName = "{$this->moduleName}DatabaseSeeder";
        $seederFile = "{$seederDir}/{$mainSeederName}.php";

        // Si el seeder principal ya existe, lo actualiza para agregar el nuevo seeder
        if (File::exists($seederFile)) {
            $this->updateMainModuleSeeder($seederFile);
            return;
        }

        // Si no existe, lo crea
        $stub = $this->getStubContent(self::STUB_MAIN_SEEDER, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Database\\Seeders",
            'mainSeederName' => $mainSeederName,
            'uses' => "use Modules\\{$this->moduleName}\\Database\\Seeders\\{$this->seederName};",
            'calls' => "            {$this->seederName}::class,\n",
        ]);

        $this->putFile($seederFile, $stub, "Seeder principal {$mainSeederName} creado en '{$seederFile}'.");
    }

    protected function updateMainModuleSeeder(string $seederFile): void
    {
        $content = File::get($seederFile);
        $search = '        $this->call([';
        $replace = "{$search}\n            {$this->seederName}::class,";
        
        if (!str_contains($content, "{$this->seederName}::class,")) {
             $newContent = str_replace($search, $replace, $content);
             File::put($seederFile, $newContent);
             $this->info("Seeder principal actualizado para incluir {$this->seederName}.");
        }
    }

    protected function generateTableSeeder(string $seederDir): void
    {
        $seederFile = "{$seederDir}/{$this->seederName}.php";

        $stub = $this->getStubContent(self::STUB_TABLE_SEEDER, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Database\\Seeders",
            'seederName' => $this->seederName,
            'modelName' => $this->modelName,
            'factoryCount' => self::DEFAULT_FACTORY_COUNT,
        ]);

        $this->putFile($seederFile, $stub, "Seeder de tabla {$this->seederName} creado en '{$seederFile}'.");
    }
}