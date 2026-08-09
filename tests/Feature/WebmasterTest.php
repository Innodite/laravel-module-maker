<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * El webmaster: el rol que lo puede todo, sin una lista que mantener.
 *
 * **La prueba que de verdad importa está abajo, y no es «el seeder crea el rol».** Es que un permiso
 * que aparece *después* —el de un módulo generado mañana— acaba en el rol sin que nadie toque este
 * archivo. Una lista literal pasaría todas las demás pruebas de este archivo, y fallaría solo esa: por
 * eso existe. El síntoma de fallar sería un 403 a la única persona que se supone que puede entrar a
 * todo, y en la pantalla de lo último que se construyó.
 *
 * Aquí se ejecuta contra una base de datos de verdad —sqlite en memoria, con las tablas de la
 * convención de Spatie— porque lo que se mide es **el efecto**, no el texto del archivo.
 */

/** Las tablas mínimas de la convención de permisos, más `users`. */
function tablasDePermisos(): void
{
    Schema::create('permissions', function ($tabla): void {
        $tabla->id();
        $tabla->string('name');
        $tabla->string('guard_name');
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
        $tabla->timestamps();
    });

    Schema::create('model_has_roles', function ($tabla): void {
        $tabla->unsignedBigInteger('role_id');
        $tabla->string('model_type');
        $tabla->unsignedBigInteger('model_id');
    });
}

/** Deja escritos unos permisos, como los habría dejado el seeder de una subfuncionalidad. */
function sembrarPermisos(array $nombres): void
{
    foreach ($nombres as $nombre) {
        DB::table('permissions')->insert([
            'name'       => $nombre,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/** El webmaster generado, listo para ejecutar, con su consola capturada. */
function webmasterGenerado(): array
{
    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    cargarSeederDelProyecto('WebmasterSeeder');

    $buffer  = new BufferedOutput();
    $comando = new class () extends Command {
        protected $signature = 'prueba:consola';
    };

    $comando->setLaravel(app());
    $comando->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

    $seeder = new Database\Seeders\WebmasterSeeder();
    $seeder->setContainer(app())->setCommand($comando);

    return [$seeder, $buffer];
}

/** Los permisos que tiene el rol, por nombre. */
function permisosDelRol(string $rol = 'webmaster'): array
{
    $rolId = DB::table('roles')->where('name', $rol)->value('id');

    $ids = DB::table('role_has_permissions')->where('role_id', $rolId)->pluck('permission_id');

    return DB::table('permissions')->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
}

beforeEach(function (): void {
    if (extension_loaded('pdo_sqlite')) {
        tablasDePermisos();
    }
});

// ── Lo que escribe el instalador ────────────────────────────────────────────────────────────

it('el instalador lo escribe, y es uno solo aunque el proyecto sea multitenant', function () {
    // No sabe nada de contextos: recoge los permisos que haya en la base donde se le invoque. En
    // multitenant el despliegue central le da los centrales y el de cada tenant los suyos, con el
    // mismo archivo.
    Artisan::call('innodite:module-setup', ['--mode' => 'multitenant-shared', '--tenancy' => 'stancl', '--no-interaction' => true]);

    expect(File::exists(database_path('seeders/WebmasterSeeder.php')))->toBeTrue();

    $codigo = soloCodigo(File::get(database_path('seeders/WebmasterSeeder.php')));

    expect(str_contains($codigo, 'central'))->toBeFalse(
        'El webmaster no distingue contextos: los permisos que recoge son los de la base donde corre.'
    );
});

it('el despliegue lo llama después de los permisos, no antes', function () {
    // Recoge lo que los seeders de permisos acaban de crear: llamarlo antes lo dejaría con los
    // permisos de ayer, que es la forma silenciosa de este fallo.
    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    $codigo = soloCodigo(File::get(database_path('seeders/InnoditeDeploySeeder.php')));

    expect($codigo)->toContain('WebmasterSeeder::class');

    expect(strpos($codigo, "runMasters('Permissions')"))
        ->toBeLessThan(strpos($codigo, 'WebmasterSeeder::class'));
});

it('lee de la configuración y no del entorno — B28', function () {
    // `env()` fuera de config/ devuelve el valor POR DEFECTO en cuanto el proyecto cachea la
    // configuración, que es lo normal en producción: el `.env` deja de cargarse. El webmaster se
    // crearía con otro correo y otra contraseña, sin un solo error. La clave se declara en
    // `config/make-module.php`, donde `env()` sí corresponde, y ahí queda horneada en la caché.
    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    $codigo = soloCodigo(File::get(database_path('seeders/WebmasterSeeder.php')));

    expect(str_contains($codigo, 'env('))->toBeFalse(
        'Un env() aquí se evalúa a su valor por defecto en cualquier proyecto con config:cache.'
    );

    expect($codigo)->toContain('make-module.webmaster');
});

it('la configuración del paquete declara al webmaster', function () {
    $publicada = require dirname(__DIR__, 2) . '/config/make-module.php';

    expect($publicada)->toHaveKey('webmaster')
        ->and(array_keys($publicada['webmaster']))->toBe(['role', 'name', 'email', 'password']);
});

it('no sobreescribe el webmaster que el proyecto ya tenía', function () {
    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    $archivo = database_path('seeders/WebmasterSeeder.php');
    File::put($archivo, File::get($archivo) . "\n// los roles de la casa\n");

    Artisan::call('innodite:module-setup', ['--mode' => 'single-app', '--no-interaction' => true]);

    expect(File::get($archivo))->toContain('los roles de la casa');
});

// ── Lo que hace al ejecutarse, contra una base de datos ──────────────────────────────────────

it('crea el rol y le da todos los permisos que existen', function () {
    requiereBaseDeDatos();

    sembrarPermisos(['invoices_index', 'invoices_store', 'invoices_view_create']);

    [$seeder] = webmasterGenerado();
    $seeder->run();

    expect(permisosDelRol())->toBe(['invoices_index', 'invoices_store', 'invoices_view_create']);
});

it('un módulo generado DESPUÉS queda cubierto sin tocar nada', function () {
    requiereBaseDeDatos();

    // **Esta es la prueba de R25.** Una lista literal pasaría todas las demás de este archivo y
    // fallaría aquí: el permiso que nace mañana no estaría escrito en ella. Y el síntoma sería un
    // 403 a quien se supone que puede entrar a todo, en la pantalla de lo último que se construyó.
    sembrarPermisos(['invoices_index', 'invoices_store']);

    [$seeder] = webmasterGenerado();
    $seeder->run();

    expect(permisosDelRol())->toHaveCount(2);

    // Se genera y se despliega un módulo nuevo: sus permisos aparecen en la tabla.
    sembrarPermisos(['payments_index', 'payments_store', 'payments_destroy']);

    $seeder->run();

    expect(permisosDelRol())->toBe([
        'invoices_index',
        'invoices_store',
        'payments_destroy',
        'payments_index',
        'payments_store',
    ]);
});

it('correrlo dos veces no duplica ninguna asignación', function () {
    requiereBaseDeDatos();

    sembrarPermisos(['invoices_index', 'invoices_store']);

    [$seeder] = webmasterGenerado();
    $seeder->run();
    $seeder->run();

    expect(DB::table('role_has_permissions')->count())->toBe(2);
});

it('crea el usuario y le asigna el rol', function () {
    requiereBaseDeDatos();

    sembrarPermisos(['invoices_index']);

    [$seeder] = webmasterGenerado();
    $seeder->run();

    $usuario = DB::table('users')->where('email', 'webmaster@innodite.local')->first();

    expect($usuario)->not->toBeNull();

    expect(DB::table('model_has_roles')->where('model_id', $usuario->id)->count())->toBe(1);
});

it('la contraseña sale de la configuración cuando está declarada', function () {
    requiereBaseDeDatos();

    config()->set('make-module.webmaster.password', 'la-del-proyecto');

    sembrarPermisos(['invoices_index']);

    [$seeder] = webmasterGenerado();
    $seeder->run();

    $usuario = DB::table('users')->where('email', 'webmaster@innodite.local')->first();

    expect(Hash::check('la-del-proyecto', $usuario->password))->toBeTrue();
});

it('sin contraseña declarada genera una y la enseña una sola vez', function () {
    requiereBaseDeDatos();

    // Una contraseña por defecto escrita en el archivo generado quedaría publicada en el repositorio
    // del proyecto y sería la misma en todas las instalaciones.
    sembrarPermisos(['invoices_index']);

    [$seeder, $buffer] = webmasterGenerado();
    $seeder->run();

    $salida = $buffer->fetch();

    expect($salida)->toContain('contraseña:');

    // Y la que enseña es la que quedó escrita, no un texto decorativo.
    preg_match('/contraseña: (\S+)/u', $salida, $encontrada);

    $usuario = DB::table('users')->where('email', 'webmaster@innodite.local')->first();

    expect(Hash::check($encontrada[1] ?? '', $usuario->password))->toBeTrue();
});

it('a un usuario que ya existe no se le toca la contraseña', function () {
    requiereBaseDeDatos();

    // Un despliegue que la reescribe deja al administrador fuera de su propia aplicación, y eso
    // ocurriría en cada despliegue de producción.
    DB::table('users')->insert([
        'name'       => 'Webmaster',
        'email'      => 'webmaster@innodite.local',
        'password'   => Hash::make('la-que-ya-tenia'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    sembrarPermisos(['invoices_index']);

    [$seeder] = webmasterGenerado();
    $seeder->run();

    $usuario = DB::table('users')->where('email', 'webmaster@innodite.local')->first();

    expect(Hash::check('la-que-ya-tenia', $usuario->password))->toBeTrue();
});

it('el nombre del rol y el correo los decide el proyecto', function () {
    requiereBaseDeDatos();

    config()->set('make-module.webmaster.role', 'super-admin');
    config()->set('make-module.webmaster.email', 'admin@innodite.pe');

    sembrarPermisos(['invoices_index']);

    [$seeder] = webmasterGenerado();
    $seeder->run();

    expect(permisosDelRol('super-admin'))->toBe(['invoices_index']);
    expect(DB::table('users')->where('email', 'admin@innodite.pe')->exists())->toBeTrue();
});

it('sin las tablas de permisos avisa, en vez de reventar el despliegue', function () {
    requiereBaseDeDatos();

    // El proyecto puede estar desplegándose antes de instalar el paquete de permisos, y eso no es un
    // error del despliegue: es un paso que todavía no toca.
    Schema::drop('permissions');

    [$seeder, $buffer] = webmasterGenerado();
    $seeder->run();

    expect($buffer->fetch())->toContain('`permissions`');
});

it('sin ningún permiso todavía, lo dice en vez de dejar un rol vacío sin explicación', function () {
    requiereBaseDeDatos();

    [$seeder, $buffer] = webmasterGenerado();
    $seeder->run();

    expect($buffer->fetch())->toContain('los seeders de permisos corrieron antes');
});
