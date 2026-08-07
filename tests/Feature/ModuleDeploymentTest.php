<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;
use Innodite\LaravelModuleMaker\Tests\Support\GeneratedModule;

/**
 * El despliegue de punta a punta: un módulo generado, levantado entero con un solo comando.
 *
 * **Qué añade este archivo a las 229 pruebas que ya hay.** Todas ellas afirman que **cada pieza se
 * escribe bien**: que el stub tiene lo que debe, que el trait invoca a quien debe, que el maestro
 * deriva la clase correcta. Ninguna afirma que **las piezas juntas levantan una aplicación**, que es
 * lo único que le importa a quien despliega. La diferencia no es de grado: los defectos de esta
 * familia —B13, B17, B25, B26, B27, B28— pasaron todas las pruebas por archivo que había, porque
 * cada mitad era correcta y lo que fallaba era el encaje.
 *
 * Por eso aquí no se lee nada: se **ejecuta el mismo comando que corre en el servidor**
 * (`innodite:deploy`) contra una base de datos de verdad, y se miran los **efectos** — la tabla
 * creada, el permiso sembrado, el rol con sus permisos dentro. Es R75 aplicada al propio paquete.
 *
 * **Dos decisiones del montaje, que no son detalles:**
 *
 *   1. **El módulo se genera DENTRO del proyecto de pruebas**, y no en el directorio temporal que usa
 *      el resto de la suite. No es una preferencia: el `MigrationsList` generado declara sus rutas
 *      **relativas a la raíz del proyecto** —`Modules/Deploy/Database/Migrations/…`— porque así es
 *      como `migrate --path` las recibe en un proyecto real. Un módulo fuera del proyecto haría que
 *      esas rutas no apunten a nada, y la prueba mediría el montaje en vez del paquete.
 *   2. **Las tablas de la convención de permisos las pone la prueba**, no el paquete. `permissions`,
 *      `roles`, `users`… las trae el proyecto (Spatie + Laravel) y el paquete se escribe **contra
 *      ellas** a propósito, para no atarse a una versión concreta. Aquí se crean con `description` y
 *      `module_id`, que son de la convención de la casa, justamente para comprobar que se llenan.
 */

// ── Montaje ──────────────────────────────────────────────────────────────────────────────────

/**
 * Las tablas que trae el proyecto anfitrión: la convención de permisos, más `users`.
 *
 * Con `description` y `module_id` en `permissions`, y con `modules`, porque son las columnas de la
 * convención de la casa que el seeder generado llena **si existen** — y esta prueba está para
 * comprobar que las llena.
 */
function tablasDeLaConvencion(): void
{
    Schema::create('modules', function ($tabla): void {
        $tabla->id();
        $tabla->string('name');
        $tabla->timestamps();
    });

    Schema::create('permissions', function ($tabla): void {
        $tabla->id();
        $tabla->string('name');
        $tabla->string('guard_name');
        $tabla->string('description')->nullable();
        $tabla->unsignedBigInteger('module_id')->nullable();
        $tabla->timestamps();
    });

    Schema::create('roles', function ($tabla): void {
        $tabla->id();
        $tabla->string('name');
        $tabla->string('guard_name');
        $tabla->timestamps();
    });

    Schema::create('role_has_permissions', function ($tabla): void {
        $tabla->unsignedBigInteger('permission_id');
        $tabla->unsignedBigInteger('role_id');
    });

    Schema::create('users', function ($tabla): void {
        $tabla->id();
        $tabla->string('name');
        $tabla->string('email')->unique();
        $tabla->string('password');
        $tabla->timestamp('email_verified_at')->nullable();
        $tabla->timestamps();
    });

    Schema::create('model_has_roles', function ($tabla): void {
        $tabla->unsignedBigInteger('role_id');
        $tabla->string('model_type');
        $tabla->unsignedBigInteger('model_id');
    });
}

/**
 * Genera el módulo **dentro del proyecto de pruebas**, que es donde sus rutas relativas apuntan, y
 * lo deja **vivo mientras dure el archivo**.
 *
 * Ver la nota 1 de la cabecera: el `MigrationsList` declara `Modules/…` relativo a la raíz, así que
 * un módulo generado fuera del proyecto tendría una lista que no resuelve.
 *
 * **Por qué se genera una vez y no una por prueba**, que es lo que hacía la primera versión de este
 * archivo y costó un falso verde:
 *
 * `cargarPiezas()` **salta las clases ya declaradas** —y hace bien, porque volver a declararlas es un
 * fatal de PHP—. Pero una clase de seeder lleva dentro la ruta de sus migraciones. Si cada prueba
 * regenera el módulo y borra el anterior, la segunda prueba se queda con **las clases de la primera**,
 * apuntando a una carpeta que ya no existe: el despliegue corre, siembra los permisos, crea el
 * webmaster… y no aplica una sola migración. La tabla no aparece y el error no dice por qué.
 *
 * Lo destapó una sonda: cambiando el orden de dos archivos, el mismo despliegue pasaba o fallaba. Una
 * prueba cuyo resultado depende de quién corrió antes no afirma nada sobre el paquete.
 *
 * Y el módulo persistente es además **lo más parecido a la realidad**: en un proyecto de verdad el
 * módulo está en disco de forma permanente y se despliega muchas veces sobre el mismo árbol.
 *
 * **La otra mitad del mismo problema, y por eso el módulo se llama `Deploy`:** la colisión no ocurre
 * solo entre las pruebas de este archivo. Media suite genera módulos llamados `Invoice`, `Billing` o
 * `Payment` en sus directorios temporales, y sus clases de seeder quedan declaradas en el proceso con
 * el mismo FQCN que tendrían las de aquí. Basta con que uno de esos archivos corra antes para que este
 * despliegue reutilice clases apuntando a un temporal ya borrado y termine en código 1. Un nombre que
 * no usa nadie más cierra esa puerta: **son las clases las que colisionan, no las carpetas.**
 */
function moduloEnElProyecto(string $nombre, ModuleMode $modo, ?string $contexto = null): GeneratedModule
{
    $GLOBALS['modulosDelDespliegue'] ??= [];

    $clave = "{$nombre}|{$modo->value}|{$contexto}";

    if (isset($GLOBALS['modulosDelDespliegue'][$clave])) {
        // La configuración sí se repite: Testbench la reinicia en cada prueba, y sin ella el
        // generador no sabría dónde vive el módulo que ya está en disco.
        config()->set('make-module.module_path', base_path('Modules'));

        return $GLOBALS['modulosDelDespliegue'][$clave];
    }

    $raiz = base_path('Modules');

    // Se limpia **antes de generar**, y no solo en el `afterAll`. Ese cubre la corrida que llega al
    // final; no cubre la que se interrumpe —un fallo duro, un Ctrl-C, una prueba que revienta antes—,
    // y ahí el módulo se queda dentro de `vendor/`: invisible al repositorio, y suficiente para que
    // `make-module` se niegue a generar en la corrida siguiente porque «ya existe». El síntoma es una
    // suite que falla entera sin que nadie haya tocado nada, y que vuelve a pasar sola al intento
    // siguiente. Pasó mientras se escribía esta tarea: **una corrida no puede depender de que la
    // anterior terminara bien.**
    File::deleteDirectory($raiz);

    config()->set('make-module.module_path', $raiz);

    return $GLOBALS['modulosDelDespliegue'][$clave] = GeneratedModule::generate($nombre, $raiz, $modo, $contexto);
}

/**
 * Deja el proyecto listo para desplegar: el instalador escrito, las piezas en memoria y el orden
 * declarado.
 *
 * Las piezas se cargan a mano porque el árbol generado no está en el autoloader del proyecto de
 * pruebas — es lo mismo que documenta `cargarPiezas()` en `Pest.php`, y no una concesión: en un
 * proyecto real las carga Composer.
 */
function prepararDespliegue(GeneratedModule $modulo, string $subFeature): void
{
    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    cargarSeederDelProyecto('WebmasterSeeder');
    cargarSeederDelProyecto('InnoditeDeploySeeder');

    cargarPiezas($modulo, "Database/Seeders/{$subFeature}", SeederNames::subFeaturePieces('', $modulo->name, $subFeature));
    cargarPiezas($modulo, 'Database/Seeders/Application', SeederNames::masterPieces('', $modulo->name));

    config()->set('make-module.deploy', ["{$modulo->name}/{$subFeature}"]);
}

/**
 * El módulo se borra al terminar **el archivo**, no cada prueba.
 *
 * Un módulo generado dentro del proyecto de pruebas no puede sobrevivir a la suite —quedaría dentro
 * de `vendor/`, invisible al repositorio y contaminando las corridas siguientes—, pero borrarlo entre
 * pruebas es lo que rompía las clases ya cargadas. Ver `moduloEnElProyecto()`.
 */
afterAll(function (): void {
    File::deleteDirectory(base_path('Modules'));

    unset($GLOBALS['modulosDelDespliegue']);
});

/**
 * Deja el proyecto listo y despliega. Devuelve el código de salida.
 *
 * Las cuatro pruebas de este archivo arrancan igual —modo, tablas de la convención, módulo,
 * preparación— y lo único que cambia es el entorno y lo que afirman después.
 */
function desplegar(string $entorno = 'stage'): int
{
    return Artisan::call('innodite:deploy', ['entorno' => $entorno, '--no-interaction' => true]);
}

/** El montaje común: modo, tablas del proyecto anfitrión, módulo generado y piezas cargadas. */
function proyectoConModuloDesplegable(): GeneratedModule
{
    test()->withMode(ModuleMode::SingleApp);

    tablasDeLaConvencion();

    $modulo = moduloEnElProyecto('Deploy', ModuleMode::SingleApp);

    prepararDespliegue($modulo, 'Deploy');

    return $modulo;
}

// ── El punta a punta ─────────────────────────────────────────────────────────────────────────

it('un módulo generado levanta entero con un solo comando', function () {
    requiereBaseDeDatos();

    $this->withMode(ModuleMode::SingleApp);

    tablasDeLaConvencion();

    $modulo = moduloEnElProyecto('Deploy', ModuleMode::SingleApp);

    prepararDespliegue($modulo, 'Deploy');

    $salida = Artisan::call('innodite:deploy', ['entorno' => 'stage', '--no-interaction' => true]);

    expect($salida)->toBe(
        0,
        "FALLA: `innodite:deploy stage` terminó en error sobre un módulo recién generado. · FIX: lee "
        . "la salida del comando, que lista cada paso fallido con su archivo:línea.\n\n"
        . Artisan::output()
    );

    // 1. El esquema — lo aplicó el seeder llamando a su MigrationsList, no una migración a mano.
    expect(Schema::hasTable('deploys'))->toBeTrue(
        "FALLA: el despliegue terminó bien y la tabla de la subfuncionalidad no existe. · FIX: "
        . "comprueba que el StageSeeder invoca runMigrations() y que las rutas del MigrationsList "
        . "apuntan a la carpeta real de migraciones.\n\n" . Artisan::output()
    );

    // 2. Los permisos — con las dos columnas de la convención llenas, que es lo que hace útil un
    //    permiso: sin `description` nadie sabe qué habilita, y sin módulo no se agrupa en la UI.
    $permisos = DB::table('permissions')->get();

    expect($permisos)->not->toBeEmpty(
        'FALLA: el despliegue no sembró un solo permiso. · FIX: el paso de permisos del seeder de '
        . 'despliegue corre siempre; comprueba que el maestro de permisos del módulo resuelve.'
    );

    foreach ($permisos as $permiso) {
        expect($permiso->description)->not->toBeEmpty(
            "FALLA: el permiso '{$permiso->name}' se sembró sin description. · FIX: la description "
            . 'es obligatoria (R18): dice qué permite, dónde está y qué bloquea.'
        );

        expect($permiso->module_id)->not->toBeNull(
            "FALLA: el permiso '{$permiso->name}' no cuelga de ningún módulo. · FIX: el "
            . 'PermissionsSeeder crea la fila en `modules` y la referencia (R19).'
        );
    }

    // 3. El webmaster — con TODOS los permisos que acaban de crearse, que es la promesa de R25:
    //    un módulo generado hoy queda cubierto sin que nadie toque una lista.
    $rol = DB::table('roles')->where('name', 'webmaster')->first();

    expect($rol)->not->toBeNull(
        'FALLA: el despliegue no creó el rol webmaster. · FIX: el WebmasterSeeder corre en el paso 3 '
        . 'del seeder de despliegue, después de los permisos.'
    );

    expect(DB::table('role_has_permissions')->where('role_id', $rol->id)->count())->toBe(
        $permisos->count(),
        'FALLA: el webmaster no tiene todos los permisos que existen. · FIX: recoge los permisos del '
        . 'guard en el momento de correr; si faltan, corrió antes que el seeder de permisos.'
    );

    // 4. Y el usuario, que es lo que permite entrar a probar desde el minuto cero.
    expect(DB::table('users')->where('email', config('make-module.webmaster.email'))->exists())->toBeTrue(
        'FALLA: no existe el usuario del webmaster. · FIX: comprueba el paso ensureUser del seeder — '
        . 'necesita las tablas `users` y `model_has_roles`.'
    );
});


// ── Las tres garantías del despliegue ────────────────────────────────────────────────────────
//
// Un despliegue que levanta bien la primera vez no dice nada sobre las siguientes, y son las
// siguientes las que se ejecutan en un servidor con datos dentro. Las tres preguntas que importan a
// partir de la segunda corrida: ¿deja el mismo estado? ¿respeta lo que ya había? ¿y en producción?

it('desplegar dos veces deja el mismo estado que desplegar una', function () {
    requiereBaseDeDatos();

    proyectoConModuloDesplegable();

    expect(desplegar())->toBe(0, 'La primera corrida ya falló: ' . Artisan::output());

    $permisosTrasLaPrimera = DB::table('permissions')->count();
    $rolesTrasLaPrimera    = DB::table('roles')->count();
    $usuariosTrasLaPrimera = DB::table('users')->count();
    $asignacionesPrimera   = DB::table('role_has_permissions')->count();

    expect(desplegar())->toBe(
        0,
        'FALLA: la segunda corrida del despliegue terminó en error. · FIX: un despliegue se ejecuta '
        . "muchas veces sobre el mismo servidor; fallar en la segunda lo hace inservible.\n\n"
        . Artisan::output()
    );

    expect(DB::table('permissions')->count())->toBe(
        $permisosTrasLaPrimera,
        'FALLA: la segunda corrida cambió el número de permisos. · FIX: el PermissionsSeeder tiene '
        . 'que ser un upsert por nombre. Duplicarlos rompe la unicidad que el tema 3 exige, y '
        . 'borrarlos y recrearlos deja sin permisos a los roles que ya los tenían asignados.'
    );

    expect(DB::table('role_has_permissions')->count())->toBe(
        $asignacionesPrimera,
        'FALLA: la segunda corrida cambió las asignaciones del rol. · FIX: reasignar lo ya asignado '
        . 'duplica filas; recrear los permisos deja las asignaciones apuntando a identificadores que '
        . 'ya no existen. En los dos casos el webmaster acaba con menos permisos de los que ve.'
    );

    expect(DB::table('roles')->count())->toBe($rolesTrasLaPrimera, 'La segunda corrida duplicó roles.');
    expect(DB::table('users')->count())->toBe($usuariosTrasLaPrimera, 'La segunda corrida duplicó usuarios.');
});

it('un dato que ya estaba sobrevive al siguiente despliegue', function () {
    // La garantía que se comprueba una sola vez y se agradece siempre: R71 dice que los seeders son
    // NO destructivos salvo que se pida lo contrario con SEEDER_DESTRUCTIVE. Aquí no se pide, así
    // que un registro de negocio tiene que seguir ahí después de volver a desplegar.
    requiereBaseDeDatos();

    proyectoConModuloDesplegable();

    expect(desplegar())->toBe(0, 'La primera corrida ya falló: ' . Artisan::output());

    $id = (string) Str::ulid();

    DB::table('deploys')->insert([
        'id'         => $id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(desplegar())->toBe(0, 'La segunda corrida falló: ' . Artisan::output());

    expect(DB::table('deploys')->where('id', $id)->exists())->toBeTrue(
        'FALLA: el despliegue se llevó por delante un registro que ya estaba. · FIX: sin '
        . 'SEEDER_DESTRUCTIVE=true, el StageSeeder NO vacía su tabla (R71). Un truncate por defecto '
        . 'convierte cada despliegue en una pérdida de datos, y en producción no hay vuelta atrás.'
    );
});

it('en producción también levanta, con su propia pieza', function () {
    // `production` no es `stage` con otro nombre: ejecuta el ProductionSeeder, que nunca borra nada.
    // Si esta prueba falla, lo que está roto es justo el camino que corre en el servidor de verdad.
    requiereBaseDeDatos();

    proyectoConModuloDesplegable();

    expect(desplegar('production'))->toBe(
        0,
        "FALLA: `innodite:deploy production` terminó en error. · FIX: es el camino que se ejecuta en "
        . "el servidor real; comprueba que el ProductionSeeder de la subfuncionalidad existe y que "
        . "el maestro lo invoca con la pieza 'Production'.\n\n" . Artisan::output()
    );

    expect(Schema::hasTable('deploys'))->toBeTrue(
        'FALLA: producción no dejó el esquema puesto. · FIX: el ProductionSeeder también aplica sus '
        . 'migraciones; si no, un servidor nuevo desplegado en producción se queda sin tablas.'
    );

    expect(DB::table('permissions')->count())->toBeGreaterThan(
        0,
        'FALLA: producción no sembró los permisos. · FIX: el paso de permisos corre en los dos '
        . 'entornos — una pantalla sin su permiso creado es un 403 para todo el mundo.'
    );
});
