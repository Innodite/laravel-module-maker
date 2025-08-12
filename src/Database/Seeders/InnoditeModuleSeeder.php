<?php

namespace Innodite\LaravelModuleMaker\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InnoditeModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $modulesPath = base_path('Modules');

        if (File::exists($modulesPath)) {
            foreach (File::directories($modulesPath) as $modulePath) {
                $moduleName = basename($modulePath);
                $moduleName = Str::studly($moduleName);

                $seedersPath = "{$modulePath}/Database/Seeders";
                if (File::exists($seedersPath)) {
                    foreach (File::files($seedersPath) as $file) {
                        
                        if (Str::endsWith($fileName, 'Seeder.php')) {
                            $seederClassName = str_replace('.php', '', $fileName);
                            $seederClass = "Modules\\{$moduleName}\\Database\\Seeders\\{$seederClassName}";
                            
                            if (class_exists($seederClass)) {
                                $this->call($seederClass);
                            }
                        }
                    }
                }
            }
        }
    }
}