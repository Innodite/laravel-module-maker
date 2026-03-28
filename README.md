# Laravel Module Maker

Scaffolding de módulos Laravel con arquitectura modular multitenant. Genera la estructura completa de un módulo (Controller, Service, Repository, Provider, Routes, Model, Migration, Seeder, Factory, Tests) respetando la convención de nombres y carpetas según el contexto arquitectónico del proyecto.

---

## Instalación

```bash
composer require innodite/laravel-module-maker
php artisan innodite:module-setup
```

Agrega el autoload de módulos en `composer.json`:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Modules\\": "Modules/"
    }
}
```

```bash
composer dump-autoload
```

---

## Setup

`innodite:module-setup` hace lo siguiente automáticamente:

- Crea la carpeta `Modules/` si no existe
- Publica los stubs en `Modules/module-maker-config/stubs/`
- Publica `Modules/module-maker-config/contexts.json` — **este es el archivo que debes editar**
- Publica JSONs de ejemplo (`blog.json`, `post.json`, etc.)

---

## contexts.json — Fuente de verdad del proyecto

Ubicación: `Modules/module-maker-config/contexts.json`

Cada clave de `contexts` contiene un **array de sub-contextos** con el campo `name`. El contexto `tenant` contiene los tenants del proyecto.

```json
{
    "contexts": {
        "central": [
            {
                "name": "App Central",
                "class_prefix": "Central",
                "folder": "Central",
                "namespace_path": "Central",
                "route_prefix": "central",
                "route_name": "central.",
                "permission_prefix": "central",
                "permission_middleware": "central-permission",
                "route_middleware": ["web", "auth"],
                "wrap_central_domains": true
            }
        ],

        "shared": [
            {
                "name": "Shared",
                "class_prefix": "Shared",
                "folder": "Shared",
                "namespace_path": "Shared",
                "route_prefix": "shared",
                "route_name": "shared.",
                "permission_prefix": "shared",
                "permission_middleware": "central-permission",
                "route_middleware": ["web", "auth"],
                "wrap_central_domains": false
            }
        ],

        "tenant": [
            {
                "name": "Mi Tenant",
                "class_prefix": "TenantMiTenant",
                "folder": "Tenant/MiTenant",
                "namespace_path": "Tenant\\MiTenant",
                "route_prefix": "mi-tenant",
                "route_name": "mi-tenant.",
                "permission_prefix": "mi_tenant",
                "permission_middleware": "tenant-permission",
                "route_middleware": ["web", "auth", "tenant-auth"]
            }
        ],

        "tenant_shared": [
            {
                "name": "Tenant Shared",
                "class_prefix": "TenantShared",
                "folder": "Tenant/Shared",
                "namespace_path": "Tenant\\Shared",
                "route_prefix": null,
                "route_name": null,
                "permission_prefix": null,
                "permission_middleware": "tenant-permission",
                "route_middleware": ["web", "auth", "tenant-auth"],
                "wrap_central_domains": false
            }
        ]
    }
}
```

> Puedes tener **múltiples variantes** en un mismo contexto. Por ejemplo, `shared` puede tener `Shared` y `SharedPoint`, y el comando te preguntará cuál usar.

---

## Uso

### Modo interactivo — módulo completo

```bash
php artisan innodite:make-module NombreModulo
```

**Flujo:**

```
1. ¿En qué contexto?       → central / shared / tenant / tenant_shared
2. ¿Cuál variante?         → solo si el contexto tiene más de un item
3. ¿Cómo generar?          → Automático o Desde JSON
4. Funcionalidad (si auto) → prefijo de ruta (ej: users, campaign-goals)
```

**Ejemplo — tenant Energía España, módulo Users:**

```
php artisan innodite:make-module Users

¿En qué contexto? > tenant
¿Cuál variante?   > Energía España
¿Cómo generar?    > Automático
Funcionalidad     > users
```

Genera:

```
Modules/Users/
├── Http/Controllers/Tenant/EnergySpain/TenantEnergySpainUsersController.php
├── Services/Tenant/EnergySpain/TenantEnergySpainUsersService.php
├── Services/Tenant/EnergySpain/Contracts/TenantEnergySpainUsersServiceInterface.php
├── Repositories/Tenant/EnergySpain/TenantEnergySpainUsersRepository.php
├── Repositories/Tenant/EnergySpain/Contracts/TenantEnergySpainUsersRepositoryInterface.php
├── Models/Users.php
├── Providers/UsersServiceProvider.php
├── routes/tenant.php
├── Database/Migrations/..._create_users_table.php
├── Database/Seeders/UsersSeeder.php
├── Database/Factories/UsersFactory.php
└── Tests/Unit/UsersTest.php
```

---

### Modo JSON

```bash
php artisan innodite:make-module NombreModulo --json
```

Lee `Modules/module-maker-config/{nombremodulo}.json` y genera el módulo con la configuración detallada.

**Formato del JSON:**

```json
{
    "module_name": "User",
    "components": [
        {
            "name": "User",
            "context": "tenant",
            "context_name": "Energía España",
            "functionality": "users",
            "table": "users",
            "attributes": [
                { "name": "name",      "type": "string",  "length": 255 },
                { "name": "email",     "type": "string",  "unique": true },
                { "name": "is_active", "type": "boolean", "default": true }
            ],
            "relations": [
                { "name": "branch", "type": "belongsTo", "model": "Branch" }
            ]
        }
    ]
}
```

---

### Componentes individuales

Agrega archivos específicos a un módulo existente. Si el módulo no existe, pregunta si deseas crearlo.

```bash
php artisan innodite:make-module NombreModulo -M   # Model
php artisan innodite:make-module NombreModulo -C   # Controller
php artisan innodite:make-module NombreModulo -S   # Service + Interface
php artisan innodite:make-module NombreModulo -R   # Repository + Interface
php artisan innodite:make-module NombreModulo -G   # Migration
php artisan innodite:make-module NombreModulo -Q   # Request
```

Se pueden combinar:

```bash
php artisan innodite:make-module Tokens -M -C -S -R
```

El comando pregunta el contexto y genera los archivos con la convención correcta:

```
Modules/Tokens/Http/Controllers/Shared/SharedTokensController.php
Modules/Tokens/Services/Shared/SharedTokensService.php
Modules/Tokens/Services/Shared/Contracts/SharedTokensServiceInterface.php
Modules/Tokens/Repositories/Shared/SharedTokensRepository.php
Modules/Tokens/Repositories/Shared/Contracts/SharedTokensRepositoryInterface.php
```

---

## Convención de nombres

El `class_prefix` del contexto seleccionado se antepone a todos los archivos PHP:

| Contexto            | class_prefix          | Ejemplo Controller                  |
|---------------------|-----------------------|-------------------------------------|
| central             | `Central`             | `CentralUserController`             |
| shared → Shared     | `Shared`              | `SharedUserController`              |
| shared → SharedPoint| `SharedPoint`         | `SharedPointUserController`         |
| tenant_shared       | `TenantShared`        | `TenantSharedUserController`        |
| tenant → EnergySpain| `TenantEnergySpain`   | `TenantEnergySpainUserController`   |

> El **Model** nunca lleva prefijo de contexto.

---

## Rutas generadas

### central → `routes/web.php`

```php
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::prefix('central-users')->name('central.users.')->group(function () {
            // CRUD con middleware('central-permission:central_users_{action}')
        });
        // {{CENTRAL_END}}
    });
}
```

### shared → `routes/web.php`

```php
Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('shared-users')->name('shared.users.')->group(function () {
        // CRUD con middleware('central-permission:shared_users_{action}')
    });
    // {{SHARED_END}}
});
```

### tenant → `routes/tenant.php`

```php
// ──────────────────────────────────────────────────────────────
// Energía España — Users
// ──────────────────────────────────────────────────────────────
Route::middleware(['web', InitializeTenancyByDomain::class, ...])->group(function () {
    Route::prefix('energy-spain-users')->name('energy-spain.users.')->group(function () {
        // CRUD con middleware('tenant-permission:energy_spain_users_{action}')
    });
    // {{TENANT_ENERGY_SPAIN_END}}
});
```

### tenant_shared → `routes/tenant.php`

Genera un bloque por **cada tenant** definido en `contexts.tenant`.

### CRUD estándar

```
GET    /         → index   (permiso: {prefix}_{func}_index)
GET    /list     → list    (permiso: {prefix}_{func}_index)
POST   /         → store   (permiso: {prefix}_{func}_store)
GET    /{id}     → show    (permiso: {prefix}_{func}_show)
PUT    /{id}     → update  (permiso: {prefix}_{func}_update)
DELETE /{id}     → destroy (permiso: {prefix}_{func}_delete)
```

---

## Trait RendersInertiaModule

Incluido en el paquete. Resuelve automáticamente la carpeta del componente Vue a partir del prefijo de su nombre, leyendo `contexts.json`.

```php
use Innodite\LaravelModuleMaker\Traits\RendersInertiaModule;

class TenantEnergySpainUserController extends Controller
{
    use RendersInertiaModule;

    public function index(): InertiaResponse
    {
        return $this->renderModule('UserManagement', 'TenantEnergySpainUserIndex');
        // Resuelve → Modules/UserManagement/resources/js/Pages/Tenant/EnergySpain/TenantEnergySpainUserIndex.vue
    }
}
```

> Si el proyecto prefiere mantener el namespace `App\Traits\RendersInertiaModule`, puede crear un thin wrapper:
> ```php
> namespace App\Traits;
> trait RendersInertiaModule {
>     use \Innodite\LaravelModuleMaker\Traits\RendersInertiaModule;
> }
> ```

---

## Auto-discovery de módulos

El paquete registra automáticamente todos los módulos en `Modules/` al arrancar la aplicación. No es necesario registrar nada en `bootstrap/providers.php`.

| Qué registra    | Cómo lo encuentra                                    |
|-----------------|------------------------------------------------------|
| ServiceProvider | `Modules\{Module}\Providers\{Module}ServiceProvider` |
| Rutas web       | `routes/*.php` (excepto `api.php`)                   |
| Rutas api       | `routes/api.php`                                     |
| Vistas          | `resources/views/`                                   |
| Traducciones    | `resources/lang/`                                    |
| Migraciones     | `Database/Migrations/` (solo raíz)                   |

---

## Stubs personalizables

Publicados en `Modules/module-maker-config/stubs/clean/`. Tienen prioridad sobre los del paquete.

| Stub                       | Genera                                  |
|----------------------------|-----------------------------------------|
| `controller.stub`          | Controller con RendersInertiaModule     |
| `service.stub`             | Service                                 |
| `service-interface.stub`   | Interface del Service                   |
| `repository.stub`          | Repository                              |
| `repository-interface.stub`| Interface del Repository                |
| `provider.stub`            | ServiceProvider con bindings            |
| `model.stub`               | Model Eloquent                          |
| `request.stub`             | Form Request                            |
| `migration.stub`           | Migration con timestamps                |
| `seeder.stub`              | Seeder                                  |
| `factory.stub`             | Factory                                 |
| `test.stub`                | Test Unit                               |
