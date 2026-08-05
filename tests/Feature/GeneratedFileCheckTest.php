<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Exceptions\GeneratedFileRejectedException;
use Innodite\LaravelModuleMaker\Support\GeneratedFileCheck;

/**
 * El chequeo de salida es la red que sostiene las fases siguientes: mientras se reescriben
 * migraciones, seeders, tests, capas y comandos, cada stub nuevo puede pedir una clave que su
 * generador no entrega. Sin esta red, eso produce un archivo con `{{{ clave }}}` dentro, un
 * mensaje de éxito, y un fallo meses después en el proyecto de otro. Con ella, produce un error
 * inmediato con el nombre del archivo y del placeholder.
 *
 * Que la red funciona no es una hipótesis: al conectarla, lo primero que rechazó fue el factory
 * de B13 — el crítico que estaba escribiendo PHP inválido en cada módulo generado.
 */

it('rechaza un archivo con un placeholder que nadie resolvió', function () {
    expect(fn () => GeneratedFileCheck::assertWritable(
        '/tmp/RoleFactory.php',
        "<?php\n\nuse {{{ modelNamespace }}};\n"
    ))->toThrow(GeneratedFileRejectedException::class, 'placeholders sin resolver');
});

it('el rechazo nombra el placeholder huérfano y dice qué hacer', function () {
    try {
        GeneratedFileCheck::assertWritable('/tmp/RoleFactory.php', "<?php\n// {{{ attributes }}}\n");
        $this->fail('Un placeholder sin resolver debe impedir la escritura.');
    } catch (GeneratedFileRejectedException $e) {
        expect(str_contains($e->getMessage(), '{{{ attributes }}}'))->toBeTrue(
            'El error debe nombrar el placeholder: sin el nombre, hay que buscarlo a mano entre 27 stubs.'
        );
        expect(str_contains($e->getMessage(), 'RoleFactory.php'))->toBeTrue(
            'Y el archivo, para saber qué generador lo emitió.'
        );
        expect(str_contains($e->getMessage(), 'no entrega'))->toBeTrue(
            'R30: el mensaje dice qué hacer — añadir la clave al generador o quitarla del stub.'
        );
    }
});

it('rechaza PHP que no parsea', function () {
    expect(fn () => GeneratedFileCheck::assertWritable(
        '/tmp/Broken.php',
        "<?php\n\nclass Broken {\n    public function x(): void {\n"
    ))->toThrow(GeneratedFileRejectedException::class, 'no es válido');
});

it('rechaza un placeholder en el formato de la v3, que ya no se resuelve', function () {
    expect(fn () => GeneratedFileCheck::assertWritable(
        '/tmp/RoleSeeder.php',
        "<?php\n\nclass {{ seederName }} extends Seeder {}\n"
    ))->toThrow(GeneratedFileRejectedException::class, 'formato de la v3');
});

it('acepta una vista Vue con sus interpolaciones', function () {
    $vue = <<<'VUE'
    <template>
      <h1>Roles</h1>
      <tr v-for="item in items" :key="item.id">
        <td>{{ item.name }}</td>
        <td>{{ item.created_at }}</td>
      </tr>
      <span>{{ meta.total }}</span>
      <p v-if="error">{{ error }}</p>
    </template>
    VUE;

    GeneratedFileCheck::assertWritable('/tmp/RoleIndex.vue', $vue);

    expect(true)->toBeTrue(
        'En un .vue la doble llave es interpolación de Vue, no un placeholder: rechazarla sería '
        . 'rechazar todas las vistas correctas.'
    );
});

it('acepta un archivo de rutas con su marcador de inyección', function () {
    $routes = <<<'PHP'
    <?php

    use Modules\UserManagement\Http\Controllers\Central\Role\CentralRoleController;

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [CentralRoleController::class, 'index'])->name('index');

        // {{CENTRAL_ROUTES_END}}
    });
    PHP;

    GeneratedFileCheck::assertWritable('/tmp/web.php', $routes);

    expect(true)->toBeTrue(
        'El marcador debe sobrevivir: es como la siguiente ejecución encuentra dónde inyectar. '
        . 'Lo que lo distingue de un placeholder es que no lleva espacios interiores.'
    );
});

it('no revisa la sintaxis de lo que no es PHP', function () {
    // Un .md o un .json generado no tiene por qué parsear como PHP. Solo se le exige no
    // llevar placeholders dentro.
    GeneratedFileCheck::assertWritable('/tmp/history.md', "# Historial\n\n- Módulo generado.\n");

    expect(true)->toBeTrue();
});

it('el factory generado compila y apunta al modelo con su prefijo de contexto', function () {
    Artisan::call('innodite:make-module', [
        'name'        => 'Permission',
        '--context'   => 'central',
        '--no-routes' => true,
    ]);

    $factory = $this->tempPath('Modules/Permission/Database/Factories/Central/Permission/PermissionFactory.php');

    expect(File::exists($factory))->toBeTrue(
        'El factory debe generarse. Si no está, el chequeo lo rechazó: lee el error del comando.'
    );

    $content = File::get($factory);

    // La regresión de B13: el stub pedía modelNamespace y attributes, el generador entregaba
    // modelUses y definitionAttributes. Los dos quedaban literales y el archivo no cargaba.
    expect(fn () => token_get_all($content, TOKEN_PARSE))->not->toThrow(ParseError::class);

    expect(str_contains($content, 'Modules\Permission\Models\Central\Permission\CentralPermission'))->toBeTrue(
        'El factory debe importar el modelo REAL, con el prefijo del contexto. Apuntar a '
        . '"Permission" cuando la clase generada es "CentralPermission" es una clase que no existe.'
    );
    expect(str_contains($content, 'protected $model = CentralPermission::class;'))->toBeTrue(
        'Y usarlo en $model con ese mismo nombre.'
    );

    // El cruce que faltaba: que la clase importada exista de verdad donde dice. Un import
    // correcto en sintaxis a un archivo que nadie escribió pasa el parser y falla al ejecutar.
    expect(File::exists($this->tempPath('Modules/Permission/Models/Central/Permission/CentralPermission.php')))
        ->toBeTrue(
            'El modelo debe estar en la ruta que el factory importa. Si el ModelGenerator lo escribe '
            . 'en otro sitio, el import es válido para el parser y roto en ejecución.'
        );
});
