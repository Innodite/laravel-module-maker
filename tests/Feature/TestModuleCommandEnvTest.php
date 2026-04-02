<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Commands\TestModuleCommand;

it('renderiza variables de BD forzadas para contexto mysql con db_database', function () {
    $command = new TestModuleCommand();

    $method = new ReflectionMethod(TestModuleCommand::class, 'renderPhpunitEnvBlock');
    $method->setAccessible(true);

    $xmlEnv = $method->invoke($command, [
        'db_connection' => 'mysql',
        'db_database' => 'neocenter_test',
        'env' => [],
    ]);

    expect($xmlEnv)->toContain('name="DB_CONNECTION" value="mysql" force="true"');
    expect($xmlEnv)->toContain('name="DB_DATABASE" value="neocenter_test" force="true"');
    expect($xmlEnv)->toContain('name="DB_MYSQL_DATABASE" value="neocenter_test" force="true"');
});

it('permite sobreescribir variables por env custom con force true', function () {
    $command = new TestModuleCommand();

    $method = new ReflectionMethod(TestModuleCommand::class, 'renderPhpunitEnvBlock');
    $method->setAccessible(true);

    $xmlEnv = $method->invoke($command, [
        'db_connection' => 'mysql',
        'db_database' => 'neocenter_test',
        'env' => [
            'DB_DATABASE' => 'custom_db',
            'DB_MYSQL_DATABASE' => 'custom_mysql_db',
        ],
    ]);

    expect($xmlEnv)->toContain('name="DB_DATABASE" value="custom_db" force="true"');
    expect($xmlEnv)->toContain('name="DB_MYSQL_DATABASE" value="custom_mysql_db" force="true"');
});
