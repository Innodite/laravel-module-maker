<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * La guarda que impide desplegar contra una conexión que no existe (R03).
 *
 * `contexts.json` declara `connection_key`, pero quien tiene que tener esa conexión configurada es
 * `config/database.php` del proyecto. Cuando no la tiene, Laravel falla mucho más adentro y con un
 * mensaje que no dice qué contexto la pedía: por eso se comprueba antes, nombrando a los dos.
 */

/** Deja el trait y el archivo de una migración en el contexto indicado. */
function migracionDeclaradaEn(string $carpeta, string $modulo, string $archivo): void
{
    $migracion = "Modules/{$modulo}/Database/Migrations/{$carpeta}/{$archivo}";

    $rutaMigracion = test()->tempPath($migracion);
    File::ensureDirectoryExists(dirname($rutaMigracion));
    File::put($rutaMigracion, "<?php\n");

    $destinoTrait = test()->tempPath("Modules/{$modulo}/Database/Seeders/{$carpeta}/Thing");
    File::ensureDirectoryExists($destinoTrait);
    File::put(
        "{$destinoTrait}/{$modulo}ThingMigrationsList.php",
        "<?php\n\ntrait {$modulo}ThingMigrationsList\n{\n    protected function migrations(): array\n"
        . "    {\n        return [\n            '{$migracion}',\n        ];\n    }\n}\n"
    );
}

it('falla nombrando la conexión y el contexto cuando la del tenant no está configurada', function () {
    // tenant-one declara connection_key 'tenant_one' en contexts.json y el proyecto no la tiene.
    migracionDeclaradaEn('Tenant/TenantOne', 'User', '2026_01_01_000001_crea_usuarios.php');

    $this->artisan('innodite:migrate-plan', ['--context' => 'tenant-one'])
        ->expectsOutputToContain("'tenant_one' del contexto 'tenant-one'")
        ->assertFailed();
});

it('falla igual cuando la que falta es la de la aplicación central', function () {
    migracionDeclaradaEn('Central', 'User', '2026_01_01_000001_crea_usuarios.php');

    $this->artisan('innodite:migrate-plan', ['--context' => 'central'])
        ->expectsOutputToContain("'central' del contexto 'central'")
        ->assertFailed();
});

it('en dry-run no se exige la conexión: se está mirando, no ejecutando', function () {
    migracionDeclaradaEn('Central', 'User', '2026_01_01_000001_crea_usuarios.php');

    $this->artisan('innodite:migrate-plan', ['--context' => 'central', '--dry-run' => true])
        ->assertSuccessful();
});

it('un contexto que no existe se rechaza diciendo dónde se buscó', function () {
    // Y no «no hay migraciones para ese contexto», que es lo que haría creer que el trait falta
    // cuando lo que está mal escrito es el nombre del contexto.
    $this->artisan('innodite:migrate-plan', ['--context' => 'inventado', '--dry-run' => true])
        ->expectsOutputToContain("No se encontró el contexto 'inventado'")
        ->assertFailed();
});
