<?php

declare(strict_types=1);

use Innodite\LaravelModuleMaker\Exceptions\TenantBootstrapFailedException;
use Innodite\LaravelModuleMaker\Services\TenantBootstrapper;
use Innodite\LaravelModuleMaker\Support\ModuleMode;

/**
 * El arranque de un tenant recién creado, llamado desde el código del proyecto.
 *
 * **Lo que estas pruebas fijan es cuándo NO se levanta.** El camino feliz es el mismo
 * `DeploymentRunner` que ya cubre su propia prueba; lo que esta clase añade encima son las cuatro
 * comprobaciones que deciden si levantar es posible — y la decisión de que ninguna de ellas avise y
 * siga, porque un alta que devuelve «bien» con la base vacía se descubre cuando el cliente entra.
 *
 * ⚠️ **Lo que aquí no se puede comprobar, dicho a las claras:** que el contexto del cliente se abra
 * y se cierre de verdad. `stancl/tenancy` no es dependencia de desarrollo de este paquete, así que
 * en las pruebas `tenancy()` no existe. Es una deuda **preexistente** —el despliegue por tenants del
 * comando tampoco lo comprobaba— y se cierra o bien añadiendo el paquete a `require-dev`, o bien en
 * el proyecto anfitrión, que sí lo tiene.
 */
class TenantDoble
{
    public function __construct(private readonly string $clave = 'acme')
    {
    }

    public function getTenantKey(): string
    {
        return $this->clave;
    }
}

beforeEach(function () {
    config()->set('make-module.mode', ModuleMode::MultitenantShared->value);
    config()->set('make-module.deploy', ['tenant' => ['Invoice/Invoice']]);
});

it('el entorno se dice entero, y si no, no se levanta nada', function () {
    // Se comprueba lo primero, antes que la tenencia o la configuración: quien se equivocó de
    // palabra tiene que leer eso, no un discurso sobre paquetes de tenencia.
    expect(fn () => (new TenantBootstrapper($this->app))->bootstrap(new TenantDoble(), 'prod'))
        ->toThrow(TenantBootstrapFailedException::class, "'prod' no es un entorno de despliegue");
});

it('el mensaje del entorno equivocado trae su arreglo', function () {
    try {
        (new TenantBootstrapper($this->app))->bootstrap(new TenantDoble(), 'preprod');
    } catch (TenantBootstrapFailedException $e) {
        expect($e->getMessage())->toContain('FALLA:')
            ->and($e->getMessage())->toContain('FIX:')
            ->and($e->getMessage())->toContain("'stage' o 'production'");

        return;
    }

    $this->fail('Un entorno inexistente tiene que detener el arranque.');
});

it('en una aplicación única no hay tenants que levantar', function () {
    config()->set('make-module.mode', ModuleMode::SingleApp->value);

    expect(fn () => (new TenantBootstrapper($this->app))->bootstrap(new TenantDoble(), 'production'))
        ->toThrow(TenantBootstrapFailedException::class, 'no hay tenants que levantar');
});

it('con el orden de despliegue vacío no se levanta: sería un alta con la base vacía', function () {
    // ⭐ La decisión de fondo. Por consola esto es un aviso que alguien lee; en un alta automática,
    // seguir devolvería una cuenta que parece lista y no tiene una sola tabla.
    config()->set('make-module.deploy', []);

    expect(fn () => (new TenantBootstrapper($this->app))->bootstrap(new TenantDoble(), 'production'))
        ->toThrow(TenantBootstrapFailedException::class, 'no habría nada que levantar');
});

it('sin tenencia que el generador sepa inicializar, tampoco: escribiría en la base central', function () {
    // Y este es el fallo que no da error: sin inicializar el contexto, el seeder siembra la base
    // por defecto —la central— creyendo que siembra la del cliente.
    config()->set('make-module.tenancy_package', 'none');

    expect(fn () => (new TenantBootstrapper($this->app))->bootstrap(new TenantDoble(), 'production'))
        ->toThrow(TenantBootstrapFailedException::class, 'paquete de tenencia');
});

it('si falta el seeder de despliegue del proyecto, el mensaje dice quién lo escribe', function () {
    // ⚠️ Aquí se comprueba el MENSAJE y no la ruta, y conviene saber por qué: la comprobación del
    // seeder va después de la de tenencia, y sin `stancl/tenancy` instalada el arranque se detiene
    // siempre antes. La ruta la ejerce cualquier proyecto real, que sí la tiene; aquí se fija que
    // el día que se llegue, el mensaje diga quién escribe ese archivo — porque no es el paquete.
    $mensaje = TenantBootstrapFailedException::seederMissing('Database\\Seeders\\InnoditeTenantDeploySeeder')
        ->getMessage();

    expect($mensaje)->toContain('FALLA:')
        ->and($mensaje)->toContain('FIX:')
        ->and($mensaje)->toContain('innodite:module-setup')
        ->and($mensaje)->toContain('es del proyecto, no del paquete');
});
