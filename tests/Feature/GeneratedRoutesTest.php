<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Generators\Components\RouteGenerator;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\RouteMarkers;
use Innodite\LaravelModuleMaker\Support\TenancyPackage;

/**
 * Las rutas generadas: qué forma tienen, y **quién decide** esa forma.
 *
 * La respuesta correcta es el modo, y durante mucho tiempo fue la ausencia de una clave: sin
 * `context` en la configuración, las rutas salían por el camino simple. Eso tenía dos caras.
 *
 * Hacia un lado dejaba la aplicación única como un caso degradado —ahí el contexto está vacío
 * siempre, así que un proyecto sin tenants entraba por el «fallback» de un modo que es de primera
 * clase—. Hacia el otro, y peor, un proyecto multitenant cuyo componente no declarase contexto
 * recibía un archivo **plausible y equivocado**: sin envoltorio de dominios, en el archivo que no
 * era, y exigiendo el permiso de tenant, porque eso es lo que responde el modo cuando no se le dice
 * el contexto. Ninguna señal de que algo hubiera ido mal.
 *
 * Lo que estas pruebas fijan es que la forma la decide el modo, y que cuando falta el dato que el
 * modo necesita **no se escribe nada** en vez de escribirse algo verosímil.
 */

it('en single-app las rutas van sin contexto y con el permiso desnudo', function () {
    $rutas = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Routes/web.php');

    expect($rutas)->toContain("Route::prefix('invoices')");

    // Ninguna de las envolturas que solo tienen sentido habiendo tenants.
    expect($rutas)->not->toContain('central_domains');
    expect($rutas)->not->toContain('tenant-permission');
    expect($rutas)->not->toContain('central-permission');
});

it('las seis rutas se generan, cada una con su permiso propio', function () {
    // Ninguna comparte permiso con otra, ni siquiera index y list, que alimentan la misma pantalla:
    // protegen cosas distintas —abrirla, y ver sus datos— y cada permiso tiene que poder explicarse
    // solo en la pantalla donde se asignan a un rol.
    $rutas = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Routes/web.php');

    foreach (['index', 'list', 'store', 'show', 'update', 'destroy'] as $accion) {
        expect(str_contains($rutas, "->middleware('permission:invoices_{$accion}')"))->toBeTrue(
            "FALLA: la ruta '{$accion}' no exige su permiso propio, o lo exige con otro nombre. "
            . '· FIX: sale de SubFeaturePermissions, el mismo sitio del que los crea el seeder; si '
            . "aquí dice otra cosa, la pantalla dará 403 a todo el mundo.\nEl archivo dice:\n" . $rutas
        );
    }
});

it('en single-app, pedir un contexto es un error y el comando lo dice', function () {
    // El otro lado de «manda el modo». Si la forma la decidiera la clave, bastaría con que un
    // contexts.json heredado de otro proyecto declarase algo para que una aplicación sin tenants
    // empezara a generar con eje de contexto. El comando se niega antes de escribir nada.
    $modulo = null;

    try {
        $modulo = $this->generateModule('Invoice', ModuleMode::SingleApp, 'central');
    } catch (Throwable $e) {
        expect($e->getMessage())->toContain('no tiene contextos');

        return;
    }

    expect($modulo)->toBeNull(
        'FALLA: se generó un módulo single-app con contexto. · FIX: el comando tiene que negarse; '
        . 'en una aplicación única no hay contextos que separar y el prefijo sería ruido.'
    );
});

it('en un modo con eje de contexto, sin contexto no se escribe ninguna ruta', function () {
    // Se prueba sobre el generador y no sobre el comando porque el comando **rellena el contexto
    // por su cuenta** cuando no se le pasa (ver la nota de la fase: asume `central`). Esta guarda
    // es la de más abajo, la que protege a los otros llamadores — `add-entity` y la generación por
    // configuración dinámica— de escribir un archivo verosímil y equivocado.
    config()->set('make-module.mode', ModuleMode::MultitenantPerTenant->value);

    $rutasDir = $this->tempPath('Modules/Sin/Routes');

    $generador = new RouteGenerator('Sin', $this->tempPath('Modules/Sin'), true, 'Sin', [
        'name'          => 'Sin',
        'subFeature'    => 'Sin',
        'functionality' => 'sins',
        // sin 'context', que es justo el caso
    ]);

    $generador->generate();

    expect(file_exists("{$rutasDir}/web.php"))->toBeFalse(
        'FALLA: se escribieron rutas sin saber el contexto. · FIX: en un modo con eje de contexto, '
        . 'sin contexto no se puede saber qué dominio sirve la ruta ni qué permiso la protege. Lo '
        . 'que se escriba será plausible y equivocado, y nadie volverá a mirarlo porque el comando '
        . 'habrá terminado en verde.'
    );
});

it('las rutas de la aplicación central no acaban en el archivo de los tenants', function () {
    // El defecto más caro de la fase. El contexto `central` caía en el camino pensado para `shared`
    // —el único que de verdad vive en los dos lados— y escribía **también** en `tenant.php`: el
    // bloque `central-…`, con su `central-permission:central_…`, dentro del archivo de rutas que se
    // sirve a cada cliente.
    //
    // Nadie lo veía porque el archivo es correcto: parsea, las rutas existen y sus permisos son los
    // que dicen ser. Solo estaban en el sitio equivocado.
    $modulo = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central');

    expect($modulo->has('Routes/tenant.php'))->toBeFalse(
        'FALLA: el contexto central escribió rutas en tenant.php. · FIX: cada contexto escribe en el '
        . 'route_file que declara; solo `shared` vive en los dos, y lo dice no declarando ninguno.'
        . ($modulo->has('Routes/tenant.php') ? "\ntenant.php dice:\n" . $modulo->contents('Routes/tenant.php') : '')
    );

    expect($modulo->has('Routes/web.php'))->toBeTrue(
        'FALLA: el contexto central no escribió sus rutas donde declara (web.php).'
    );
});

it('el marcador que el generador escribe es el que el inyector busca', function () {
    // Los dos lados de la misma pareja. El generador deja el marcador para que la siguiente
    // subfuncionalidad se añada **dentro** del grupo; el inyector lo busca cuando el archivo ya
    // existe. Componían la clave por separado y no coincidían —`{{CENTRAL_END}}` contra
    // `CENTRAL_ROUTES_END`—, así que el bloque nuevo acababa pegado al final del archivo, fuera del
    // grupo que le da dominio y middleware. Sin error, y sin protección.
    $rutas = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')
        ->contents('Routes/web.php');

    $marcador = RouteMarkers::comment('central', 'web.php');

    expect(str_contains($rutas, $marcador))->toBeTrue(
        "FALLA: el archivo generado no lleva el marcador '{$marcador}'. · FIX: la clave la decide "
        . "RouteMarkers, que es de donde la lee el inyector.\nEl archivo dice:\n" . $rutas
    );

    expect($marcador)->toBe(
        '// {{CENTRAL_ROUTES_END}}',
        'FALLA: cambió el marcador de la sección central. · FIX: es el que ya está escrito en los '
        . 'routes/web.php de los proyectos instalados; cambiarlo deja huérfanos los archivos que '
        . 'ya lo llevan.'
    );
});

it('el archivo de rutas de un tenant es PHP de verdad, no un bloque suelto', function () {
    // Tres defectos en el mismo archivo, y ninguno se veía leyéndolo por encima: salía **sin
    // `<?php`** —así que su contenido era texto plano y Laravel no registraba nada—, sin el `use`
    // del controlador al que apuntan sus rutas, y con un `Route::middleware([ , ])` de coma suelta
    // que habría sido un error de sintaxis si el archivo hubiera llegado a ser PHP.
    //
    // El chequeo de salida no los vio por el mismo motivo por el que `php -l` tampoco: un archivo
    // que no abre PHP siempre «parsea». Se descubrieron al **ejecutar** el contrato del módulo en
    // un contexto de tenant.
    $rutas = $this->generateModule('Meter', ModuleMode::MultitenantPerTenant, 'tenant-one')
        ->contents('Routes/tenant.php');

    expect(str_starts_with(trim($rutas), '<?php'))->toBeTrue(
        "FALLA: el archivo de rutas del tenant no abre etiqueta PHP. · FIX: el contenido de un "
        . "archivo nuevo lleva su cabecera; el bloque suelto es solo lo que se inyecta cuando el "
        . "archivo ya existe.\nEl archivo dice:\n" . $rutas
    );

    expect(str_contains($rutas, 'use Modules\\Meter\\Http\\Controllers\\Tenant\\TenantOne\\Meter\\TenantOneMeterController;'))->toBeTrue(
        "FALLA: el archivo no importa el controlador al que apuntan sus rutas. · FIX: sin el `use`, "
        . "cada ruta referencia una clase que no existe en ese espacio de nombres.\nEl archivo "
        . "dice:\n" . $rutas
    );

    expect(preg_match('/middleware\(\[\s*,/', $rutas))->toBe(
        0,
        'FALLA: el array de middleware sale con una coma suelta. · FIX: sin middlewares declarados '
        . 'no se envuelve nada — el bloque hereda la seguridad del grupo padre del proyecto.'
    );
});

// ─── La envoltura de tenencia: la declara el proyecto, no la adivina el paquete ──────────────
//
// Un archivo de rutas de un proyecto multitenant necesita envoltura, y esa envoltura está escrita
// en el vocabulario del paquete de tenencia que use el proyecto: la central se sirve en sus
// dominios, y una ruta de tenant identifica a su tenant antes de tocar una tabla. El paquete no lo
// deduce mirando el vendor — lo declara la configuración, que es la regla 5 un nivel más abajo.

it('con stancl, las rutas de la central se sirven solo en los dominios centrales', function () {
    config()->set('make-module.tenancy.package', 'stancl');

    $rutas = soloCodigo(
        $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')
            ->contents('Routes/web.php')
    );

    expect(str_contains($rutas, "config('tenancy.central_domains')"))->toBeTrue(
        'FALLA: las rutas de la central no se limitan a los dominios centrales. · FIX: envuélvelas '
        . "en un foreach sobre config('tenancy.central_domains') con Route::domain(); sin eso "
        . "responden también en el dominio de cada cliente.\nEl archivo dice:\n" . $rutas
    );

    expect(str_contains($rutas, 'Route::domain($dominio)'))->toBeTrue(
        'FALLA: el grupo no ata las rutas a un dominio. · FIX: recorrer los dominios sin usarlos '
        . 'deja la envoltura decorativa.'
    );
});

it('con stancl, las rutas de un tenant lo identifican antes de responder', function () {
    config()->set('make-module.tenancy.package', 'stancl');

    $rutas = $this->generateModule('Meter', ModuleMode::MultitenantPerTenant, 'tenant-one')
        ->contents('Routes/tenant.php');

    $codigo = soloCodigo($rutas);

    foreach (['InitializeTenancyByDomain::class', 'PreventAccessFromCentralDomains::class'] as $mw) {
        expect(str_contains($codigo, $mw))->toBeTrue(
            "FALLA: el bloque del tenant no exige '{$mw}'. · FIX: sin identificación, la ruta "
            . 'responde en cualquier dominio y contra la base que estuviera conectada — que es el '
            . "aislamiento que el modo entero existe para dar.\nEl archivo dice:\n" . $rutas
        );
    }

    expect(str_contains($codigo, 'use Stancl\\Tenancy\\Middleware\\InitializeTenancyByDomain;'))->toBeTrue(
        'FALLA: el middleware se usa como clase y el archivo no lo importa. · FIX: un `::class` sin '
        . "su `use` nombra una clase inexistente en ese espacio de nombres.\nEl archivo dice:\n" . $rutas
    );

    expect(str_contains($codigo, "'InitializeTenancyByDomain::class'"))->toBeFalse(
        'FALLA: el middleware salió entrecomillado. · FIX: entre comillas es un alias literal que '
        . 'ningún kernel resuelve, y la ruta falla al registrarse en el proyecto del usuario.'
    );
});

it('sin paquete de tenencia declarado no se inventa envoltura: el archivo dice dónde va', function () {
    // D8: con cualquier valor distinto de los soportados el paquete NO envuelve y deja el hueco
    // señalado. Y por eso `none` es el valor por defecto y no `stancl`: escribir la envoltura de
    // stancl en un proyecto que no lo tiene produce un archivo que referencia clases inexistentes y
    // la aplicación deja de arrancar. Dejarla fuera deja una nota en un archivo que sigue siendo
    // válido — un fallo ruidoso y recuperable en vez de uno que lo tumba todo.
    config()->set('make-module.tenancy.package', 'none');

    $rutas = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')
        ->contents('Routes/web.php');

    expect(str_contains(soloCodigo($rutas), 'central_domains'))->toBeFalse(
        'FALLA: se escribió una envoltura de stancl en un proyecto que no lo declara. · FIX: sin '
        . 'paquete soportado la envoltura la escribe el desarrollador.'
    );

    expect(str_contains($rutas, 'FALTA LA ENVOLTURA'))->toBeTrue(
        'FALLA: el archivo sale sin envoltura y sin decirlo. · FIX: el hueco se señala en el propio '
        . "archivo, con qué haría stancl; en silencio nadie lo descubre.\nEl archivo dice:\n" . $rutas
    );
});

it('un paquete de tenencia que no existe es un error, no un archivo sin envoltura', function () {
    // Hoy los valores válidos son dos y solo dos. Un tercero no es «un paquete que todavía no
    // sabemos envolver»: es una declaración que el generador no puede cumplir, y tratarla como
    // «ninguno» devuelve archivos sin envoltura mientras la configuración del proyecto dice lo
    // contrario — las dos mitades plausibles apuntando a sitios distintos, otra vez.
    //
    // Ausente sí es distinto y no falla: significa «no elegí», y a eso responde la nota del archivo.
    config()->set('make-module.tenancy.package', 'tenancy-for-laravel');

    expect(fn () => TenancyPackage::current())
        ->toThrow(InvalidArgumentException::class);

    try {
        TenancyPackage::current();
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('tenancy-for-laravel');
        expect($e->getMessage())->toContain('stancl');
        expect(str_contains($e->getMessage(), 'FIX:'))->toBeTrue(
            'R30: el mensaje trae el error y cómo corregirlo, con los valores que sí valen.'
        );
    }
});

it('en single-app no hay envoltura de tenencia que declarar', function () {
    // La otra cara de la regla: aquí no hay dominios centrales que separar ni tenant que
    // identificar, así que declarar stancl no puede cambiar un solo archivo generado.
    config()->set('make-module.tenancy.package', 'stancl');

    $rutas = $this->generateModule('Invoice', ModuleMode::SingleApp)
        ->contents('Routes/web.php');

    expect($rutas)->not->toContain('central_domains');
    expect($rutas)->not->toContain('InitializeTenancyByDomain');
    expect($rutas)->not->toContain('FALTA LA ENVOLTURA');
});

it('en multitenant las rutas llevan el prefijo y el middleware de su contexto', function () {
    $rutas = $this->generateModule('Invoice', ModuleMode::MultitenantPerTenant, 'central')
        ->contents('Routes/web.php');

    expect(str_contains($rutas, "Route::prefix('central-invoices')"))->toBeTrue(
        'FALLA: la URL no lleva el prefijo del contexto. · FIX: en multitenant el contexto entra '
        . 'en la URL, o dos contextos con la misma subfuncionalidad chocan.'
    );

    expect(str_contains($rutas, "->middleware('central-permission:central_invoices_index')"))->toBeTrue(
        'FALLA: el permiso no lleva el prefijo de su contexto, o el middleware no es el central. '
        . '· FIX: los dos los responde el modo; si aquí sale el de tenant, es que la forma la '
        . "decidió otra cosa.\nEl archivo dice:\n" . $rutas
    );
});
