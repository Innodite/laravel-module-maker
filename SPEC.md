# Laravel Module Maker — Especificación del Paquete

**Paquete:** `innodite/laravel-module-maker`
**Versión actual:** v2.5.0
**Última actualización:** 2026-03-28

---

## 1. Propósito

Scaffolding de módulos Laravel con arquitectura modular multitenant. El paquete genera la estructura completa de un módulo (Controller, Service, Repository, Provider, Routes, Model, Migration, Seeder, Factory, Tests) respetando la convención de nombres y carpetas según el contexto arquitectónico del proyecto.

---

## 2. Instalación y Setup

```bash
composer require innodite/laravel-module-maker
php artisan innodite:module-setup
```

### Qué hace `innodite:module-setup`

1. Crea `Modules/` si no existe
2. Crea `Modules/module-maker-config/` si no existe
3. Publica los stubs en `Modules/module-maker-config/stubs/`
4. Publica `contexts.json` en `Modules/module-maker-config/contexts.json` ← **EDITAR ESTE**
5. Modifica `DatabaseSeeder.php` para incluir los seeders de los módulos

> Los stubs publicados tienen prioridad sobre los del paquete. El proyecto puede personalizarlos.

### Estructura publicada en el proyecto

```
Modules/module-maker-config/
├── contexts.json     ← configurar contextos y tenants del proyecto
└── stubs/
    ├── clean/        ← stubs para modo automático (personalizables)
    └── dynamic/      ← stubs para modo --json (personalizables)
```

---

## 3. contexts.json — Fuente de verdad del proyecto

Ubicación: `Modules/module-maker-config/contexts.json`

### Estructura

Cada clave de `contexts` mapea a un **array de sub-contextos con `name`**. Esto permite tener múltiples variantes por contexto (ej: `tenant` puede tener `Tenant One` y `Tenant Two`).

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

### Reglas del contexto `tenant`

- Los items del array `tenant` son los tenants del proyecto
- Cuando el usuario selecciona `tenant`, el CLI pregunta cuál item (cuál tenant)
- Si solo hay un item, se usa directamente sin preguntar
- El contexto `tenant_shared` genera rutas para **todos** los items de `tenant`

### Reglas de otros contextos

- Si el contexto tiene un solo item → se usa sin preguntar
- Si tiene múltiples items → el CLI pregunta cuál

---

## 4. Comando `innodite:make-module`

### Modo 1 — Interactivo (sin flags)

```bash
php artisan innodite:make-module NombreModulo
```

**Flujo completo:**

```
1. ¿En qué contexto? → [central, shared, tenant, tenant_shared]

2. Si el contexto seleccionado tiene >1 items:
   ¿Cuál variante? → lista de names del array

3. ¿Cómo generar?
   [1] Automático (estructura limpia)
   [2] Desde JSON (usar module-maker-config/{module}.json)

4a. Si elige Automático:
    - Pregunta: funcionalidad para rutas (ej: products, campaign-goals)
    - Genera todos los archivos con stubs clean

4b. Si elige Desde JSON:
    - Verifica que module-maker-config/{module}.json existe
    - Lee la config del JSON
    - Genera todos los archivos con stubs dynamic
    - Si no existe el JSON → error con instrucciones
```

**Nota:** Si el módulo ya existe y no se usan flags de componente individual, muestra un error. Para añadir archivos a un módulo existente, usar `-M -C -S -R -G -Q`.

### Modo 2 — Directo con JSON (`--json`)

```bash
php artisan innodite:make-module NombreModulo --json
```

Lee directamente `module-maker-config/{nombremodulo}.json` sin preguntas interactivas.

### Modo 3 — Componente individual

```bash
php artisan innodite:make-module NombreModulo -M   # Model
php artisan innodite:make-module NombreModulo -C   # Controller + contexto
php artisan innodite:make-module NombreModulo -S   # Service + Interface + contexto
php artisan innodite:make-module NombreModulo -R   # Repository + Interface + contexto
php artisan innodite:make-module NombreModulo -G   # Migration
php artisan innodite:make-module NombreModulo -Q   # Request + contexto
```

El módulo debe existir. Se pueden combinar flags.

---

## 5. JSON del módulo — `module-maker-config/{module}.json`

Creado manualmente por el desarrollador para usar con el modo `--json`.
El archivo debe ubicarse en `Modules/module-maker-config/{nombremodulo}.json`.

### Formato

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
                { "name": "name",      "type": "string",  "length": 255 },
                { "name": "price",     "type": "decimal", "total": 10, "places": 2 },
                { "name": "is_active", "type": "boolean", "default": true }
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

### Campos del componente

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

## 6. Archivos generados

### Estructura de carpetas (ejemplo: `tenant` → `Tenant One`, módulo `Product`)

```
Modules/Product/
├── Http/
│   ├── Controllers/
│   │   └── Tenant/TenantOne/
│   │       └── TenantOneProductController.php
│   └── Requests/
│       └── ProductStoreRequest.php
├── Services/
│   └── Tenant/TenantOne/
│       ├── TenantOneProductService.php
│       └── Contracts/
│           └── TenantOneProductServiceInterface.php
├── Repositories/
│   └── Tenant/TenantOne/
│       ├── TenantOneProductRepository.php
│       └── Contracts/
│           └── TenantOneProductRepositoryInterface.php
├── Models/
│   └── Product.php
├── Providers/
│   └── ProductServiceProvider.php
├── routes/
│   └── tenant.php
├── Database/
│   ├── Migrations/
│   │   └── xxxx_create_products_table.php
│   ├── Seeders/
│   │   └── ProductSeeder.php
│   └── Factories/
│       └── ProductFactory.php
└── Tests/Unit/
    └── ProductTest.php
```

### Convención de nombres PHP

| Contexto / Variante          | class_prefix    | Ejemplo Controller               |
|------------------------------|-----------------|----------------------------------|
| central → App Central        | `Central`       | `CentralProductController`       |
| shared → Shared              | `Shared`        | `SharedProductController`        |
| tenant_shared → Tenant Shared| `TenantShared`  | `TenantSharedProductController`  |
| tenant → Tenant One          | `TenantOne`     | `TenantOneProductController`     |
| tenant → Tenant Two          | `TenantTwo`     | `TenantTwoProductController`     |

> El **Model** nunca lleva prefijo de contexto.

### Convención de nombres Vue

Mismo prefijo que PHP. `RendersInertiaModule` lo resuelve automáticamente:

| Componente Vue | Carpeta resuelta |
|---|---|
| `CentralProductList.vue` | `Pages/Central/` |
| `SharedProductList.vue` | `Pages/Shared/` |
| `TenantSharedProductList.vue` | `Pages/Tenant/Shared/` |
| `TenantOneProductList.vue` | `Pages/Tenant/TenantOne/` |
| `TenantTwoProductList.vue` | `Pages/Tenant/TenantTwo/` |

---

## 7. Rutas generadas

### central

Archivo: `routes/web.php`

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

### shared

Archivo: `routes/web.php`

```php
Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('shared-products')->name('shared.products.')->group(function () {
        // CRUD con middleware('central-permission:shared_products_{action}')
    });
    // {{SHARED_END}}
});
```

### tenant (específico)

Archivo: `routes/tenant.php`

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

### tenant_shared

Archivo: `routes/tenant.php` — genera un bloque por cada item del contexto `tenant`.

### Marcador de append

Formato: `// {{CLASS_PREFIX_SCREAMING_SNAKE_END}}`
Ejemplo: `class_prefix: TenantOne` → marcador `// {{TENANT_ONE_END}}`

Permite añadir nuevas funcionalidades al archivo de rutas existente sin sobreescribir las anteriores.

### CRUD routes

```
GET    /        → index   (permiso: {prefix}_{func}_index)
GET    /list    → list    (permiso: {prefix}_{func}_index)
POST   /        → store   (permiso: {prefix}_{func}_store)
GET    /{id}    → show    (permiso: {prefix}_{func}_show)
PUT    /{id}    → update  (permiso: {prefix}_{func}_update)
DELETE /{id}    → destroy (permiso: {prefix}_{func}_delete)
```

---

## 8. Provider generado

```php
class ProductServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TenantOneProductServiceInterface::class,
            TenantOneProductService::class
        );
        $this->app->bind(
            TenantOneProductRepositoryInterface::class,
            TenantOneProductRepository::class
        );
    }
}
```

---

## 9. Trait RendersInertiaModule

**Namespace:** `Innodite\LaravelModuleMaker\Traits\RendersInertiaModule`

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

**Resolución del prefijo:**
1. Extrae el prefijo del nombre del componente (`TenantOne`)
2. Construye el mapa `class_prefix → folder` desde todos los items de `contexts.json`
3. Ordena por longitud de clave desc (prefijos más específicos primero)
4. Verifica que el `.vue` existe en `Modules/{Module}/resources/js/Pages/{folder}/{file}.vue`
5. Lanza `RuntimeException` con mensaje descriptivo si no existe o si el prefijo es inválido

---

## 10. Stubs personalizables

Publicados en `Modules/module-maker-config/stubs/clean/` (prioridad sobre los del paquete).

| Stub | Genera |
|---|---|
| `controller.stub` | Controller con RendersInertiaModule + Service injection |
| `service.stub` | Service con interface injection |
| `service-interface.stub` | Interface del service |
| `repository.stub` | Repository con model injection |
| `repository-interface.stub` | Interface del repository |
| `provider.stub` | ServiceProvider con bindings |
| `model.stub` | Model Eloquent |
| `request.stub` | Form Request |
| `migration.stub` | Migration con timestamps |
| `seeder.stub` | Seeder básico |
| `factory.stub` | Factory |
| `test.stub` | Test Unit |

---

## 11. Auto-discovery de módulos

El paquete registra automáticamente todos los módulos en `Modules/` al arrancar:

| Qué registra | Cómo |
|---|---|
| ServiceProvider | `Modules\{Module}\Providers\{Module}ServiceProvider` |
| Rutas web | `routes/*.php` (excepto `api.php`) con middleware `web` |
| Rutas api | `routes/api.php` con middleware `api` |
| Vistas | `resources/views/` |
| Traducciones | `resources/lang/` |
| Migraciones | `Database/Migrations/` (solo raíz, sin subdirectorios) |

---

## 12. Pendientes / TODO

- [x] Trait `RendersInertiaModule` con prefijos dinámicos desde `contexts.json`
- [x] Comando interactivo con selección de contexto y variante
- [x] Estructura `contexts.json` con arrays de sub-items con `name`
- [x] Archivos de ejemplo eliminados — solo `contexts.json` como referencia
- [ ] **Modo interactivo:** crear `module-maker-config/{module}.json` automáticamente al generar en modo Automático
- [ ] **Componente `--vue`:** generar el componente Vue con convención de nombres correcta
