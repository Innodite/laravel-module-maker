<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\PackageVersion;

/**
 * `innodite:doctor` — un solo diagnóstico, en dos etapas, y la segunda no corre si la primera falla.
 *
 * Las pruebas **provocan** cada situación en vez de leer el código del comando: un diagnóstico que
 * describe bien lo que le pasa a un proyecto sano no sirve de nada, porque nadie lo lanza cuando todo
 * va bien. Lo que hay que comprobar es qué imprime cuando algo está roto — y qué NO imprime.
 */

/** Lanza el diagnóstico y devuelve [código de salida, salida completa]. */
function diagnostico(array $opciones = []): array
{
    $codigo = Artisan::call('innodite:doctor', array_merge($opciones, ['--no-interaction' => true]));

    return [$codigo, Artisan::output()];
}

/**
 * Escribe archivos en el proyecto de prueba y los retira al terminar, pase lo que pase.
 *
 * El contrato de la etapa 2 vive en rutas de Laravel que no se pueden mover por configuración
 * —`app/Models/User.php`, `app/Http/Middleware/…`—, así que la única forma de probar el verde es
 * crearlos. El `finally` no es cortesía: el skeleton de Testbench vive en `vendor/`, sobrevive a la
 * prueba y no aparece en el repositorio, así que un archivo olvidado aquí contaminaría todas las
 * corridas siguientes sin dejar rastro.
 *
 * @param array<string, string> $archivos ruta absoluta => contenido
 */
function conArchivosEnElProyecto(array $archivos, Closure $prueba): void
{
    foreach ($archivos as $ruta => $contenido) {
        File::ensureDirectoryExists(dirname($ruta));
        File::put($ruta, $contenido);
    }

    try {
        $prueba();
    } finally {
        foreach (array_keys($archivos) as $ruta) {
            File::delete($ruta);
        }
    }
}

it('es un solo diagnóstico: los dos que había ya no existen', function () {
    // D1. El problema no era el nombre de uno de los dos: era que los dos nombres decían lo contrario
    // de lo que hacían, y el desarrollador no quiere elegir cuál correr — quiere saber si su proyecto
    // está listo.
    $comandos = Artisan::all();

    expect(isset($comandos['innodite:doctor']))->toBeTrue('FALLA: innodite:doctor no está registrado.');

    expect(isset($comandos['innodite:module-check']))->toBeFalse(
        'FALLA: innodite:module-check sigue registrado — se fusionó en innodite:doctor.'
    );

    expect(isset($comandos['innodite:check-env']))->toBeFalse(
        'FALLA: innodite:check-env sigue registrado — se fusionó en innodite:doctor.'
    );
});

it('la cabecera dice la versión que corre, no una escrita a mano', function () {
    // Declaraba «v3.0.0» en tres sitios de un mismo archivo mientras el paquete iba por la 3.6. Es la
    // línea que se copia en el reporte de un fallo, así que una literal vieja manda a todo el mundo a
    // mirar la etiqueta equivocada.
    [, $salida] = diagnostico();

    expect(PackageVersion::current())->not->toBe('3.0.0');

    expect(str_contains($salida, 'v' . PackageVersion::current()))->toBeTrue(
        "FALLA: la cabecera no declara la versión instalada.\n{$salida}"
    );

    expect(str_contains($salida, 'v3.0.0'))->toBeFalse(
        "FALLA: la cabecera sigue anunciando v3.0.0.\n{$salida}"
    );
});

it('con el entorno del generador roto no comprueba el contrato, y dice cuál se saltó', function () {
    config()->set('make-module.contexts_path', $this->tempPath('module-maker-config/no-existe.json'));

    [$codigo, $salida] = diagnostico();

    expect($codigo)->toBe(1);

    expect(str_contains($salida, 'Etapa 1'))->toBeTrue("FALLA: no se ve la etapa 1.\n{$salida}");

    expect(str_contains($salida, 'Etapa 2'))->toBeFalse(
        "FALLA: la etapa 2 se ejecutó con el entorno roto. Sus fallos serían de la misma causa, y "
        . "leerlos cuesta más que arreglar lo primero.\n{$salida}"
    );

    expect(str_contains($salida, 'Sin comprobar'))->toBeTrue(
        "FALLA: cortó en silencio, y el silencio se lee como «el resto pasó».\n{$salida}"
    );

    expect(str_contains($salida, 'auth.permissions'))->toBeTrue(
        "FALLA: no nombra lo que dejó sin comprobar.\n{$salida}"
    );
});

it('con --continuar comprueba las dos etapas aunque la primera falle', function () {
    config()->set('make-module.contexts_path', $this->tempPath('module-maker-config/no-existe.json'));

    [$codigo, $salida] = diagnostico(['--continuar' => true]);

    expect($codigo)->toBe(1);

    expect(str_contains($salida, 'Etapa 2'))->toBeTrue(
        "FALLA: --continuar no llegó al contrato del proyecto.\n{$salida}"
    );
});

it('cada fallo trae el arreglo, no solo el diagnóstico', function () {
    // R30, y por R81: clasificar un rojo es leer muchos fallos rápido, y un mensaje sin FIX marcado
    // hay que leerlo entero — así que se salta.
    config()->set('make-module.contexts_path', $this->tempPath('module-maker-config/no-existe.json'));

    [, $salida] = diagnostico(['--continuar' => true]);

    expect(str_contains($salida, 'FALLA:'))->toBeTrue("FALLA: no marca el fallo.\n{$salida}");
    expect(str_contains($salida, 'FIX:'))->toBeTrue("FALLA: dice qué pasó y no qué hacer.\n{$salida}");
});

it('sin modo elegido diagnostica el modo, en vez de morirse por él', function () {
    // El comando anterior llamaba a ModuleMode::current() en mitad de la lectura de contexts.json, y
    // eso lanza. El proyecto que más necesita el diagnóstico —el que no ha elegido modo— recibía una
    // excepción sin capturar en lugar de una respuesta.
    config()->set('make-module.mode', null);

    [$codigo, $salida] = diagnostico(['--continuar' => true]);

    expect($codigo)->toBe(1);

    expect(str_contains($salida, 'no has elegido el modo del proyecto'))->toBeTrue(
        "FALLA: no dice que falta el modo.\n{$salida}"
    );

    expect(str_contains($salida, 'innodite:module-setup'))->toBeTrue(
        "FALLA: no dice cómo elegirlo.\n{$salida}"
    );
});

it('exige el bridge donde hay eje de contexto y no lo exige donde no lo hay', function () {
    // La severidad la decide el modo. En multitenant el bridge es quien pone auth.context, y sin él la
    // vista generada no sabe a qué tenant sirve. En single-app no hay contexto que inyectar (D9 lo
    // prohíbe), así que exigirlo sería inventar un requisito que el modo ya descartó.
    [, $multitenant] = diagnostico(['--continuar' => true]);

    expect(str_contains($multitenant, 'InnoditeContextBridge no está registrado'))->toBeTrue(
        "FALLA: en multitenant el bridge ausente no es un fallo.\n{$multitenant}"
    );

    $this->withMode(ModuleMode::SingleApp);

    [, $singleApp] = diagnostico(['--continuar' => true]);

    expect(str_contains($singleApp, 'sin eje de contexto, no hace falta'))->toBeTrue(
        "FALLA: en single-app se sigue exigiendo el bridge.\n{$singleApp}"
    );
});

it('mira la carpeta donde el generador escribe, no la que Laravel trae por defecto', function () {
    // El comando anterior leía base_path('Modules') mientras el generador escribe en
    // make-module.module_path. En un proyecto normal son la misma ruta y no se nota; en uno que movió
    // sus módulos, el diagnóstico informaba sobre una carpeta vacía y pasaba en verde.
    $migraciones = $this->tempPath('Modules/Invoice/Database/Migrations');
    File::ensureDirectoryExists($migraciones);
    File::put("{$migraciones}/2026_01_01_000001_create_invoices_table.php", '<?php');
    File::put("{$migraciones}/2026_01_02_000002_create_invoices_table.php", '<?php');

    [$codigo, $salida] = diagnostico();

    expect($codigo)->toBe(1);

    expect(str_contains($salida, 'migración duplicada: create_invoices_table'))->toBeTrue(
        "FALLA: no vio la colisión que hay en la carpeta configurada.\n{$salida}"
    );
});

it('no reprueba el catálogo que publica el propio paquete', function () {
    // Hallazgo de esta tarea, preexistente: el diagnóstico exigía `route_file` a todos los
    // sub-contextos, y el contexts.json que instala innodite:module-setup deja `shared` sin ella
    // **a propósito** — esa ausencia es como un contexto dice que vive en los DOS archivos de rutas
    // (RouteGenerator). Es decir: el diagnóstico reprobaba la instalación por defecto del paquete, y
    // un diagnóstico que falla contra su propio ejemplo enseña a ignorarlo.
    [, $salida] = diagnostico();

    expect(str_contains($salida, "no tiene la clave 'route_file'"))->toBeFalse(
        "FALLA: vuelve a exigirse route_file. Sin declararla, el contexto vive en los dos archivos "
        . "de rutas — no le falta nada.\n{$salida}"
    );
});

it('pasa en verde cuando el generador puede escribir y el proyecto cumple su contrato', function () {
    $this->withMode(ModuleMode::SingleApp);

    conArchivosEnElProyecto([
        app_path('Models/User.php') => "<?php\n\nuse Spatie\\Permission\\Traits\\HasRoles;\n\n"
            . "class User\n{\n    use HasRoles;\n}\n",
        app_path('Http/Middleware/HandleInertiaRequests.php') => "<?php\n\n"
            . "class HandleInertiaRequests\n{\n    public function share()\n    {\n"
            . "        return ['auth' => ['permissions' => []]];\n    }\n}\n",
    ], function () {
        [$codigo, $salida] = diagnostico();

        expect($codigo)->toBe(0, "FALLA: el diagnóstico no pasa con todo en su sitio.\n{$salida}");

        expect(str_contains($salida, 'El proyecto está listo'))->toBeTrue(
            "FALLA: no lo declara listo.\n{$salida}"
        );
    });
});
