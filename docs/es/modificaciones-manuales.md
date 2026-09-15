Lo que el generador no escribe por su cuenta, y hay que dejar en el proyecto antes de generar el
primer módulo.

## 1 · Namespace `Modules\` en el autoload

En `composer.json` del proyecto:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/",
      "Modules\\": "Modules/"
    }
  }
}
```

```bash
composer dump-autoload
```

## 2 · Middleware `InnoditeContextBridge`

En `bootstrap/app.php` (Laravel 11+):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', [
        \Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge::class,
    ]);
})
```

Inyecta, vía `Inertia::share()`, en cada request:

| Prop | Contenido |
|---|---|
| `auth.context.route_prefix` | `central`, `tenant` — `null` en `single-app` |
| `auth.context.permission_prefix` | `central`, `tenant` — `null` en `single-app` |
| `auth.permissions` | Array de strings con los permisos del usuario autenticado |

## 3 · Resolución de permisos del usuario

El middleware resuelve los permisos del usuario en este orden, y usa el primero que encuentre:

| # | Cómo | Requiere |
|---|---|---|
| 1 | `$user->getAllPermissions()->pluck('name')` | `spatie/laravel-permission`, con `HasRoles` en el modelo `User` |
| 2 | `$user->getInnoditePermissions()` | El modelo `User` implementa `Innodite\LaravelModuleMaker\Contracts\InnoditeUserPermissions` |
| 3 | `[]` + warning en log | Ninguna de las dos anteriores |

Implementación de la interfaz, si no se usa Spatie:

```php
use Innodite\LaravelModuleMaker\Contracts\InnoditeUserPermissions;

class User extends Authenticatable implements InnoditeUserPermissions
{
    public function getInnoditePermissions(): array
    {
        return $this->permissions->pluck('name')->toArray();
    }
}
```

## 4 · Tablas de permisos y roles

El generador escribe el permiso de cada ruta y los seeders que los crean; las tablas son del
proyecto. Con `spatie/laravel-permission` instalado y migrado, ya están.

## 5 · Ziggy, con `@routes` en el layout

Las pantallas generadas resuelven sus rutas por nombre (`window.route('central.users.index')`).
Sin `@routes` en el layout Blade, `window.route` no existe y la pantalla no carga sus datos.

## 6 · `APP_KEY`

El grupo de middleware `web` cifra la sesión. Sin `APP_KEY`, la petición falla antes de llegar al
middleware de permiso — el error no menciona el paquete ni el módulo.
