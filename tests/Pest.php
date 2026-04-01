<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest Configuration — Innodite Laravel Module Maker
|--------------------------------------------------------------------------
|
| Aplica el TestCase base de Orchestra Testbench a todos los tests
| en las carpetas Feature/ y Unit/.
|
*/

use Innodite\LaravelModuleMaker\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
