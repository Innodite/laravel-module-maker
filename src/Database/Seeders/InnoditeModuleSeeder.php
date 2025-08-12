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

                $seederClass = "Modules\\{$moduleName}\\Database\\Seeders\\{$moduleName}DatabaseSeeder";
                if (class_exists($seederClass)) {
                    $this->call($seederClass);
                }
            }
        }
    }
}
