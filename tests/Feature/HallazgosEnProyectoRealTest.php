<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\TestDatabase;

/**
 * Lo que salió al instalar la v4 en dos proyectos de verdad.
 *
 * Ninguno de estos casos lo veía la suite, y no por falta de pruebas: por falta de **proyecto**. Un
 * paquete probado solo contra su propio anfitrión mínimo no tiene stubs publicados de una versión
 * anterior, ni un comando propio que tape uno suyo, ni un `phpunit.xml` que apunte la suite a otra
 * conexión. Los tres primeros impedían usar la v4; se descubrieron el mismo día que se instaló.
 *
 * Cada prueba **provoca** la situación: la que solo mirase el código diría que está corregida
 * mientras el proyecto sigue roto.
 */

/** El módulo de prueba, con sus migraciones repartidas por contexto. */
function moduloConMigraciones(string $raiz, array $rutasRelativas): void
{
    foreach ($rutasRelativas as $relativa) {
        $ruta = "{$raiz}/{$relativa}";
        File::ensureDirectoryExists(dirname($ruta));
        File::put($ruta, "<?php\n\nreturn new class {};\n");
    }
}

it('la misma migración en dos contextos no es una colisión', function () {
    // El caso real: `users` existe en la base central y en la de cada tenant, y las dos migraciones se
    // llaman igual porque describen la misma tabla en bases distintas. El diagnóstico las contaba como
    // duplicadas —cuatro «colisiones» en un proyecto multitenant, todas legítimas— y, al ser un error
    // de la etapa 1, cortaba antes de llegar al contrato del proyecto.
    // La ruta que el diagnóstico mira es la CONFIGURADA, no `base_path('Modules')`. Escribir en la
    // otra fue lo que hizo pasar la primera versión de esta prueba por el motivo equivocado.
    $modulos = (string) config('make-module.module_path');
    $modulo  = "{$modulos}/Facturacion";

    moduloConMigraciones($modulo, [
        'Cobros/Database/Migrations/Central/2026_01_01_000000_create_users_final.php',
        'Cobros/Database/Migrations/Tenant/2026_01_02_000000_create_users_final.php',
    ]);

    try {
        Artisan::call('innodite:doctor', ['--continuar' => true, '--no-interaction' => true]);
        $salida = Artisan::output();

        expect(str_contains($salida, 'migración duplicada'))->toBeFalse(
            "FALLA: reprueba la misma tabla en dos contextos, que es lo que multitenant exige.\n{$salida}"
        );
    } finally {
        File::deleteDirectory($modulo);
    }
});

it('la misma migración DOS veces en un contexto sí lo es', function () {
    // La otra mitad: sin esto, la corrección de arriba se habría comido la comprobación entera.
    // La ruta que el diagnóstico mira es la CONFIGURADA, no `base_path('Modules')`. Escribir en la
    // otra fue lo que hizo pasar la primera versión de esta prueba por el motivo equivocado.
    $modulos = (string) config('make-module.module_path');
    $modulo  = "{$modulos}/Facturacion";

    moduloConMigraciones($modulo, [
        'Cobros/Database/Migrations/Central/2026_01_01_000000_create_users_final.php',
        'Otros/Database/Migrations/Central/2026_01_02_000000_create_users_final.php',
    ]);

    try {
        Artisan::call('innodite:doctor', ['--continuar' => true, '--no-interaction' => true]);
        $salida = Artisan::output();

        expect(str_contains($salida, 'migración duplicada'))->toBeTrue(
            "FALLA: dos migraciones de la misma tabla en el MISMO contexto sí se pisan.\n{$salida}"
        );
    } finally {
        File::deleteDirectory($modulo);
    }
});

it('los stubs publicados en formato v3 se avisan antes de generar, no a media generación', function () {
    // El bloqueante de la actualización: un proyecto que publicó stubs con la v3 los tiene en
    // `{{ clave }}`, la v4 delimita con `{{{ clave }}}` y el instalador no los sobreescribe —llevan
    // ediciones del proyecto—. Sin este aviso, la generación arrancaba, escribía carpetas y docs, y
    // moría en el primer stub dejando medio módulo en disco. Les pasó a los dos proyectos.
    $carpeta = rtrim((string) config('make-module.config_path'), '/\\') . '/stubs/contextual';

    File::ensureDirectoryExists($carpeta);
    File::put("{$carpeta}/model.stub", "<?php\n\nnamespace {{ namespace }};\n\nclass {{ modelName }} {}\n");

    try {
        $codigo = Artisan::call('innodite:doctor', ['--no-interaction' => true]);
        $salida = Artisan::output();

        expect($codigo)->toBe(1, "FALLA: los stubs de la v3 no hacen fallar el diagnóstico.\n{$salida}");

        expect(str_contains($salida, 'formato de la v3'))->toBeTrue(
            "FALLA: no dice que los stubs publicados están en el formato anterior.\n{$salida}"
        );

        expect(str_contains($salida, 'model.stub'))->toBeTrue(
            "FALLA: no nombra el stub que hay que tocar.\n{$salida}"
        );
    } finally {
        File::deleteDirectory(dirname($carpeta));
    }
});

it('un stub de la v4 no se confunde con uno de la v3', function () {
    // El falso positivo que habría hecho inútil la comprobación: los stubs Vue llevan `{{ }}` de
    // interpolación con toda la legitimidad del mundo. Lo que los distingue es que los de la v4
    // traen además sus `{{{ }}}`.
    $carpeta = rtrim((string) config('make-module.config_path'), '/\\') . '/stubs/contextual';

    File::ensureDirectoryExists($carpeta);
    File::put("{$carpeta}/vue-index.stub", "<template>\n  <div>{{ item.name }}</div>\n</template>\n"
        . "<script setup>\nconst modulo = '{{{ moduleName }}}';\n</script>\n");

    try {
        Artisan::call('innodite:doctor', ['--continuar' => true, '--no-interaction' => true]);
        $salida = Artisan::output();

        expect(str_contains($salida, 'formato de la v3'))->toBeFalse(
            "FALLA: un stub de la v4 con interpolación Vue se toma por uno de la v3.\n{$salida}"
        );
    } finally {
        File::deleteDirectory(dirname($carpeta));
    }
});

it('avisa cuando un comando del proyecto tapa uno del paquete', function () {
    // Pasó de verdad: los dos proyectos traían su propio `innodite:crear-bd-test` de la época de la
    // v3, y Laravel resuelve un nombre a una clase. Se ejecutaba el del proyecto creyendo usar el del
    // paquete —otras opciones, otro comportamiento— y nada en pantalla lo decía. Se descubrió porque
    // el `--dry-run` que la firma del paquete promete no existía al ejecutarlo.
    Artisan::command('innodite:crear-bd-test', fn () => 0)->describe('El del proyecto');

    Artisan::call('innodite:doctor', ['--continuar' => true, '--no-interaction' => true]);
    $salida = Artisan::output();

    expect(str_contains($salida, 'tapados por otros del proyecto'))->toBeTrue(
        "FALLA: no avisa de que un comando del proyecto tapa uno del paquete.\n{$salida}"
    );

    expect(str_contains($salida, 'innodite:crear-bd-test'))->toBeTrue(
        "FALLA: no nombra el comando tapado.\n{$salida}"
    );
});

it('la guarda de la base mira la conexión de la SUITE, no la de la aplicación', function () {
    // El comando corre por artisan, fuera de PHPUnit, así que `database.default` responde por la
    // aplicación: la base REAL. Con eso se le denegaba la ejecución a un proyecto **correctamente
    // configurado** — kapitalizando declara `DB_CONNECTION=mysql_test` en su phpunit.xml, hace lo
    // correcto, y aun así no podía lanzar su contrato.
    Config::set('database.default', 'negocio');
    Config::set('database.connections.negocio', ['driver' => 'sqlite', 'database' => 'negocio']);
    Config::set('database.connections.negocio_test', ['driver' => 'sqlite', 'database' => 'negocio_test']);

    $phpunit = base_path('phpunit.xml');
    $previo  = File::exists($phpunit) ? File::get($phpunit) : null;

    File::put($phpunit, "<?xml version=\"1.0\"?>\n<phpunit>\n  <php>\n"
        . "    <env name=\"DB_CONNECTION\" value=\"negocio_test\"/>\n  </php>\n</phpunit>\n");

    try {
        expect(TestDatabase::laDeLaSuite())->toBe('negocio_test');

        expect(TestDatabase::nombreDe(TestDatabase::laDeLaSuite()))->toBe('negocio_test');
    } finally {
        $previo === null ? File::delete($phpunit) : File::put($phpunit, $previo);
    }
});

it('la base declarada aparte en phpunit.xml gana a la de su conexión', function () {
    // La otra forma real, la de Interconectados: la conexión es la de siempre (`central`) y lo que
    // cambia es la base, fijada con DB_DATABASE. Sin mirar esa variable se leería la central real.
    Config::set('database.default', 'central');
    Config::set('database.connections.central', ['driver' => 'sqlite', 'database' => 'negocio']);

    $phpunit = base_path('phpunit.xml');
    $previo  = File::exists($phpunit) ? File::get($phpunit) : null;

    File::put($phpunit, "<?xml version=\"1.0\"?>\n<phpunit>\n  <php>\n"
        . "    <env name=\"DB_CONNECTION\" value=\"central\"/>\n"
        . "    <env name=\"DB_DATABASE\" value=\"negocio_test\"/>\n  </php>\n</phpunit>\n");

    try {
        $conexion = TestDatabase::laDeLaSuite();

        expect(TestDatabase::nombreDe($conexion))->toBe('negocio_test');

        expect(TestDatabase::esDePruebas(TestDatabase::nombreDe($conexion)))->toBeTrue(
            'FALLA: con DB_DATABASE apuntando a la _test, la guarda seguía viendo la base real.'
        );
    } finally {
        $previo === null ? File::delete($phpunit) : File::put($phpunit, $previo);
    }
});

it('sin phpunit.xml responde la conexión de la aplicación, como antes', function () {
    // El fail-safe: la corrección no puede volver ciego un proyecto que no declara nada.
    Config::set('database.default', 'negocio_test');
    Config::set('database.connections.negocio_test', ['driver' => 'sqlite', 'database' => 'negocio_test']);

    $phpunit = base_path('phpunit.xml');
    $previo  = File::exists($phpunit) ? File::get($phpunit) : null;
    File::delete($phpunit);

    try {
        expect(TestDatabase::laDeLaSuite())->toBe('negocio_test');
    } finally {
        $previo === null ? File::delete($phpunit) : File::put($phpunit, $previo);
    }
});

it('el ensayo no dice que escribió lo que no escribió', function () {
    // El instalador anunciaba «✅ Escrito en .env», «✅ Seeder creado» y «Configuración completa»
    // también con --dry-run, y solo al final aclaraba que no había tocado nada. Un resumen honesto
    // detrás de veinte líneas que afirman lo contrario no repara nada: quien lee las primeras ya se
    // lo creyó.
    Artisan::call('innodite:module-setup', [
        '--mode'           => 'single-app',
        '--dry-run'        => true,
        '--no-interaction' => true,
    ]);

    $salida = Artisan::output();

    expect(str_contains($salida, '✅ Escrito en .env'))->toBeFalse(
        "FALLA: el ensayo afirma haber escrito el .env.\n{$salida}"
    );

    expect(preg_match('/^\s*Configuración completa\./m', $salida))->toBe(0,
        "FALLA: el ensayo declara la configuración completa.\n{$salida}"
    );

    expect(str_contains($salida, '(ensayo)'))->toBeTrue(
        "FALLA: el ensayo no marca sus líneas como tales.\n{$salida}"
    );
});

it('el diagnóstico exige el catálogo del modo, y en multiinquilino son dos contextos', function () {
    // El diagnóstico pedía `shared` y `tenant_shared` a los dos modos multiinquilino que había: el
    // mensaje nombraba el modo y luego exigía lo mismo de cualquiera, así que la distinción existía
    // solo en pantalla. Con un solo modo y dos contextos, lo que se exige es exactamente lo que hay.
    expect(ModuleMode::Multitenant->requiredContextKeys())
        ->toBe(['central', 'tenant']);

    expect(ModuleMode::SingleApp->requiredContextKeys())->toBe(
        [],
        'Una aplicación única no tiene contextos que declarar: exigirle uno la obliga a inventárselo '
        . 'para pasar un diagnóstico que no le aplica.'
    );
});

it('lo que se admite en --context es más ancho que lo que hay que declarar', function () {
    // Son dos preguntas distintas, y responderlas con una sola lista es lo que cruzó los catálogos.
    // Generar en un contexto que el proyecto declaró no es un error porque el diagnóstico no lo
    // exigiera.
    expect(ModuleMode::Multitenant->supportsContext('tenant'))->toBeTrue();
    expect(ModuleMode::Multitenant->supportsContext('central'))->toBeTrue();
    expect(ModuleMode::Multitenant->supportsContext('tenant_shared'))->toBeFalse();

    expect(ModuleMode::SingleApp->supportsContext('central'))->toBeFalse(
        'FALLA: una aplicación única no tiene eje de contexto.'
    );
});
