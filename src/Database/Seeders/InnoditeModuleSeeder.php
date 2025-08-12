<?php

namespace Innodite\LaravelModuleMaker\Database\Seeders;

use Illuminate\Database\Seeder;

class InnoditeModuleSeeder extends Seeder
{
    /**
     * @var array
     */
    protected array $moduleSeeders = [];

    /**
     * Set the module seeders to be called.
     *
     * @param array $seeders
     * @return void
     */
    public function setModuleSeeders(array $seeders): void
    {
        $this->moduleSeeders = $seeders;
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        foreach ($this->moduleSeeders as $seederClass) {
            $this->call($seederClass);
        }
    }
}