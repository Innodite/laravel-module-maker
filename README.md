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
- Modifica `DatabaseSeeder.php` para incluir los seeders de los módulos

### Estructura publicada

```
Modules/module-maker-config/
├── contexts.json     ← configurar contextos y tenants del proyecto
└── stubs/
    ├── clean/        ← stubs para modo automático (personalizables)
    └── dynamic/      ← stubs para modo --json (personalizables)
```

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
                "name": "Tenant One",
                "class_prefix": "TenantOne",
                "folder": "Tenant/TenantOne",
                "namespace_path": "Tenant\\TenantOne",
                "route_prefix": "tenant-one",
                "route_name": "tenant-one.",
                "permission_prefix": "tenant_one",
                "permission_middleware": "tenant-permission",
                "route_middleware": ["web", "auth", "tenant-auth"]
            },
            {
                "name": "Tenant Two",
                "class_prefix": "TenantTwo",
                "folder": "Tenant/TenantTwo",
                "namespace_path": "Tenant\\TenantTwo",
                "route_prefix": "tenant-two",
                "route_name": "tenant-two.",
                "permission_prefix": "tenant_two",
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

### Campos de cada sub-contexto

| Campo | Tipo | Descripción |
|---|---|---|
| `name` | string | Nombre legible, mostrado en el CLI al seleccionar |
| `class_prefix` | string | Prefijo de clase PHP (ej: `TenantOne`) |
| `folder` | string | Subcarpeta dentro de `Http/Controllers/`, `Services/`, etc. |
| `namespace_path` | string | Fragmento de namespace (ej: `Tenant\\TenantOne`) |
| `route_prefix` | string\|null | Prefijo URL (ej: `tenant-one`) |
| `route_name` | string\|null | Prefijo de nombre de ruta (ej: `tenant-one.`) |
| `permission_prefix` | string\|null | Prefijo del permiso (ej: `tenant_one`) |
| `permission_middleware` | string | Middleware de permisos (`tenant-permission` o `central-permission`) |
| `route_middleware` | array | Stack de middleware para el grupo de rutas |
| `wrap_central_domains` | bool | Si las rutas se envuelven en `foreach central_domains` |

> Puedes tener **múltiples variantes** en un mismo contexto. Por ejemplo, `tenant` puede tener `Tenant One` y `Tenant Two`, y el comando te preguntará cuál usar.

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

**Ejemplo — contexto `tenant` → `Tenant One`, módulo `Product`:**

```
php artisan innodite:make-module Product

¿En qué contexto? > tenant
¿Cuál variante?   > Tenant One
¿Cómo generar?    > Automático
Funcionalidad     > products
```

Genera:

```
Modules/Product/
├── Http/
│   ├── Controllers/Tenant/TenantOne/TenantOneProductController.php
│   └── Requests/ProductStoreRequest.php
├── Services/Tenant/TenantOne/
│   ├── TenantOneProductService.php
│   └── Contracts/TenantOneProductServiceInterface.php
├── Repositories/Tenant/TenantOne/
│   ├── TenantOneProductRepository.php
│   └── Contracts/TenantOneProductRepositoryInterface.php
├── Models/Product.php
├── Providers/ProductServiceProvider.php
├── routes/tenant.php
├── Database/Migrations/xxxx_create_products_table.php
├── Database/Seeders/ProductSeeder.php
├── Database/Factories/ProductFactory.php
└── Tests/Unit/ProductTest.php
```

---

### Modo JSON

```bash
php artisan innodite:make-module NombreModulo --json
```

Lee `Modules/module-maker-config/{nombremodulo}.json` y genera el módulo con la configuración detallada (atributos, índices, relaciones).

**Formato del JSON:**

```json
{
    "module_name": "Product",
    "components": [
        {
            "name": "Product",
            "context": "tenant",
            "context_name": "Tenant One",
            "functionality": "products",
            "table": "products",
            "attributes": [
                { "name": "name",       "type": "string",  "length": 255 },
                { "name": "price",      "type": "decimal", "total": 10, "places": 2 },
                { "name": "is_active",  "type": "boolean", "default": true }
            ],
            "indexes": [
                { "columns": ["name"], "type": "index" }
            ],
            "relations": [
                { "name": "category", "type": "belongsTo", "model": "Category" }
            ]
        }
    ]
}
```

**Campos del componente:**

| Campo | Requerido | Descripción |
|---|---|---|
| `name` | ✅ | Nombre del modelo (StudlyCase) |
| `context` | ✅ | Clave del contexto (`central`, `shared`, `tenant`, `tenant_shared`) |
| `context_name` | ✅ | Valor del campo `name` del sub-contexto a usar |
| `functionality` | ✅ | Prefijo de ruta en kebab-case (ej: `products`, `campaign-goals`) |
| `table` | ❌ | Nombre de tabla (default: snake_plural del modelo) |
| `attributes` | ❌ | Columnas para la migración |
| `indexes` | ❌ | Índices de la migración |
| `relations` | ❌ | Relaciones Eloquent del modelo |

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
php artisan innodite:make-module Token -M -C -S -R
```

El comando pregunta el contexto y genera los archivos con la convención correcta:

```
Modules/Token/Http/Controllers/Shared/SharedTokenController.php
Modules/Token/Services/Shared/SharedTokenService.php
Modules/Token/Services/Shared/Contracts/SharedTokenServiceInterface.php
Modules/Token/Repositories/Shared/SharedTokenRepository.php
Modules/Token/Repositories/Shared/Contracts/SharedTokenRepositoryInterface.php
```

---

## Convención de nombres

El `class_prefix` del contexto seleccionado se antepone a todos los archivos PHP:

| Contexto               | class_prefix    | Ejemplo Controller              |
|------------------------|-----------------|---------------------------------|
| central                | `Central`       | `CentralProductController`      |
| shared → Shared        | `Shared`        | `SharedProductController`       |
| tenant_shared          | `TenantShared`  | `TenantSharedProductController` |
| tenant → Tenant One    | `TenantOne`     | `TenantOneProductController`    |
| tenant → Tenant Two    | `TenantTwo`     | `TenantTwoProductController`    |

> El **Model** nunca lleva prefijo de contexto.

---

## Rutas generadas

### central → `routes/web.php`

```php
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::prefix('central-products')->name('central.products.')->group(function () {
            // CRUD con middleware('central-permission:central_products_{action}')
        });
        // {{CENTRAL_END}}
    });
}
```

### shared → `routes/web.php`

```php
Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('shared-products')->name('shared.products.')->group(function () {
        // CRUD con middleware('central-permission:shared_products_{action}')
    });
    // {{SHARED_END}}
});
```

### tenant → `routes/tenant.php`

```php
// ──────────────────────────────────────────────────────────────────────────
// Tenant One — Product
// ──────────────────────────────────────────────────────────────────────────
Route::middleware(['web', 'auth', 'tenant-auth'])->group(function () {
    Route::prefix('tenant-one-products')->name('tenant-one.products.')->group(function () {
        // CRUD con middleware('tenant-permission:tenant_one_products_{action}')
    });
    // {{TENANT_ONE_END}}
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

Incluido en el paquete. Resuelve automáticamente la carpeta del componente Vue a partir del prefijo del nombre, leyendo `contexts.json`.

```php
use Innodite\LaravelModuleMaker\Traits\RendersInertiaModule;

class TenantOneProductController extends Controller
{
    use RendersInertiaModule;

    public function index(): InertiaResponse
    {
        return $this->renderModule('Product', 'TenantOneProductIndex');
        // Resuelve → Modules/Product/resources/js/Pages/Tenant/TenantOne/TenantOneProductIndex.vue
    }
}
```

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

| Stub                        | Genera                              |
|-----------------------------|-------------------------------------|
| `controller.stub`           | Controller con RendersInertiaModule |
| `service.stub`              | Service                             |
| `service-interface.stub`    | Interface del Service               |
| `repository.stub`           | Repository                          |
| `repository-interface.stub` | Interface del Repository            |
| `provider.stub`             | ServiceProvider con bindings        |
| `model.stub`                | Model Eloquent                      |
| `request.stub`              | Form Request                        |
| `migration.stub`            | Migration con timestamps            |
| `seeder.stub`               | Seeder                              |
| `factory.stub`              | Factory                             |
| `test.stub`                 | Test Unit                           |
