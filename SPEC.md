# Laravel Module Maker — Especificación del Paquete

**Paquete:** `innodite/laravel-module-maker`
**Versión actual:** v2.4.0
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
4. Publica los JSONs de ejemplo (`blog.json`, `post.json`, etc.)
5. Publica `contexts.json` en `Modules/module-maker-config/contexts.json` ← **EDITAR ESTE**
6. Modifica `DatabaseSeeder.php` para incluir los seeders de los módulos

> Los stubs publicados tienen prioridad sobre los del paquete. El proyecto puede personalizarlos.

---

## 3. contexts.json — Fuente de verdad del proyecto

Ubicación: `Modules/module-maker-config/contexts.json`

### Estructura

Cada clave de `contexts` mapea a un **array de sub-contextos con `name`**. Esto permite tener múltiples variantes por contexto (ej: `shared` puede tener `SharedBase` y `SharedPoint`).

```json
{
    "_readme": "...",

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
            },
            {
                "name": "SharedPoint",
                "class_prefix": "SharedPoint",
                "folder": "Shared/Point",
                "namespace_path": "Shared\\Point",
                "route_prefix": "shared-point",
                "route_name": "shared-point.",
                "permission_prefix": "shared_point",
                "permission_middleware": "central-permission",
                "route_middleware": ["web", "auth"],
                "wrap_central_domains": false
            }
        ],

        "tenant": [
            {
                "name": "Energía España",
                "class_prefix": "TenantEnergySpain",
                "folder": "Tenant/EnergySpain",
                "namespace_path": "Tenant\\EnergySpain",
                "route_prefix": "energy-spain",
                "route_name": "energy-spain.",
                "permission_prefix": "energy_spain",
                "permission_middleware": "tenant-permission",
                "route_middleware": [
                    "web",
                    "Stancl\\Tenancy\\Middleware\\InitializeTenancyByDomain::class",
                    "Stancl\\Tenancy\\Middleware\\PreventAccessFromCentralDomains::class",
                    "auth",
                    "tenant-auth"
                ]
            },
            {
                "name": "Telefonía España",
                "class_prefix": "TenantTelephonySpain",
                "folder": "Tenant/TelephonySpain",
                "namespace_path": "Tenant\\TelephonySpain",
                "route_prefix": "telephony-spain",
                "route_name": "telephony-spain.",
                "permission_prefix": "telephony_spain",
                "permission_middleware": "tenant-permission",
                "route_middleware": [
                    "web",
                    "Stancl\\Tenancy\\Middleware\\InitializeTenancyByDomain::class",
                    "Stancl\\Tenancy\\Middleware\\PreventAccessFromCentralDomains::class",
                    "auth",
                    "tenant-auth"
                ]
            },
            {
                "name": "Telefonía Perú",
                "class_prefix": "TenantTelephonyPeru",
                "folder": "Tenant/TelephonyPeru",
                "namespace_path": "Tenant\\TelephonyPeru",
                "route_prefix": "telephony-peru",
                "route_name": "telephony-peru.",
                "permission_prefix": "telephony_peru",
                "permission_middleware": "tenant-permission",
                "route_middleware": [
                    "web",
                    "Stancl\\Tenancy\\Middleware\\InitializeTenancyByDomain::class",
                    "Stancl\\Tenancy\\Middleware\\PreventAccessFromCentralDomains::class",
                    "auth",
                    "tenant-auth"
                ]
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
                "route_middleware": [
                    "web",
                    "Stancl\\Tenancy\\Middleware\\InitializeTenancyByDomain::class",
                    "Stancl\\Tenancy\\Middleware\\PreventAccessFromCentralDomains::class",
                    "auth",
                    "tenant-auth"
                ],
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
| `class_prefix` | string | Prefijo de clase PHP (ej: `TenantEnergySpain`) |
| `folder` | string | Subcarpeta dentro de `Http/Controllers/`, `Services/`, etc. |
| `namespace_path` | string | Fragmento de namespace (ej: `Tenant\\EnergySpain`) |
| `route_prefix` | string\|null | Prefijo URL (ej: `energy-spain`) |
| `route_name` | string\|null | Prefijo de nombre de ruta (ej: `energy-spain.`) |
| `permission_prefix` | string\|null | Prefijo del permiso (ej: `energy_spain`) |
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
   [1] Automático (estructura limpia por defecto)
   [2] Desde JSON (usar module-maker-config/{module}.json)

4a. Si elige Automático:
    - Pregunta: funcionalidad para rutas (ej: users, campaign-goals)
    - Genera todos los archivos
    - Crea/actualiza module-maker-config/{module}.json con la config básica

4b. Si elige Desde JSON:
    - Verifica que module-maker-config/{module}.json existe
    - Lee la config del JSON
    - Genera todos los archivos con esa config
    - Si no existe el JSON → error con instrucciones
```

**Nota:** Si el módulo ya existe y se vuelve a correr el comando, ofrece agregar un nuevo contexto/tenant al módulo existente (no sobreescribe).

### Modo 2 — Directo con JSON (`--json`)

```bash
php artisan innodite:make-module NombreModulo --json
```

Igual que elegir "Desde JSON" en el modo interactivo, pero sin preguntas. Lee directamente `module-maker-config/{nombremodulo}.json`.

### Modo 3 — Componente individual

```bash
php artisan innodite:make-module NombreModulo --model=User
php artisan innodite:make-module NombreModulo --controller=UserController
php artisan innodite:make-module NombreModulo --service=UserService
php artisan innodite:make-module NombreModulo --repository=UserRepository
php artisan innodite:make-module NombreModulo --migration=User
php artisan innodite:make-module NombreModulo --request=UserStoreRequest
```

El módulo debe existir. Se pueden combinar flags.

---

## 5. JSON del módulo — `module-maker-config/{module}.json`

Creado automáticamente en modo interactivo. Enriquecible a mano para el modo `--json`.

### Formato

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
                { "name": "name", "type": "string", "length": 255 },
                { "name": "email", "type": "string", "unique": true },
                { "name": "is_active", "type": "boolean", "default": true }
            ],
            "indexes": [
                { "columns": "email", "type": "index" }
            ],
            "relations": [
                { "name": "branch", "type": "belongsTo", "model": "Branch" }
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
| `context_name` | ✅ | `name` del sub-contexto seleccionado (identifica cuál item del array usar) |
| `functionality` | ✅ | Prefijo de ruta en kebab-case (ej: `users`, `campaign-goals`) |
| `table` | ❌ | Nombre de la tabla (default: snake_plural del modelo) |
| `attributes` | ❌ | Columnas para la migración |
| `indexes` | ❌ | Índices de la migración |
| `relations` | ❌ | Relaciones Eloquent del modelo |

---

## 6. Archivos generados

### Estructura de carpetas (ejemplo: `tenant` → `Energía España`, módulo `User`)

```
Modules/User/
├── Http/
│   ├── Controllers/
│   │   └── Tenant/EnergySpain/
│   │       └── TenantEnergySpainUserController.php
│   └── Requests/
│       └── UserStoreRequest.php
├── Services/
│   └── Tenant/EnergySpain/
│       ├── TenantEnergySpainUserService.php
│       └── Contracts/
│           └── TenantEnergySpainUserServiceInterface.php
├── Repositories/
│   └── Tenant/EnergySpain/
│       ├── TenantEnergySpainUserRepository.php
│       └── Contracts/
│           └── TenantEnergySpainUserRepositoryInterface.php
├── Models/
│   └── User.php
├── Providers/
│   └── UserServiceProvider.php
├── routes/
│   └── tenant.php
├── Database/
│   ├── Migrations/
│   │   └── 2026_xx_xx_create_users_table.php
│   ├── Seeders/
│   │   └── UserSeeder.php
│   └── Factories/
│       └── UserFactory.php
└── Tests/Unit/
    └── UserTest.php
```

### Convención de nombres PHP

| Contexto / Variante | class_prefix | Ejemplo Controller |
|---|---|---|
| central → App Central | `Central` | `CentralUserController` |
| shared → Shared | `Shared` | `SharedUserController` |
| shared → SharedPoint | `SharedPoint` | `SharedPointUserController` |
| tenant_shared → Tenant Shared | `TenantShared` | `TenantSharedUserController` |
| tenant → Energía España | `TenantEnergySpain` | `TenantEnergySpainUserController` |
| tenant → Telefonía España | `TenantTelephonySpain` | `TenantTelephonySpainUserController` |

### Convención de nombres Vue

Mismo prefijo que PHP. `RendersInertiaModule` lo resuelve automáticamente:

| Componente Vue | Carpeta resuelta |
|---|---|
| `CentralUserList.vue` | `Pages/Central/` |
| `SharedUserList.vue` | `Pages/Shared/` |
| `SharedPointUserList.vue` | `Pages/Shared/Point/` |
| `TenantSharedUserList.vue` | `Pages/Tenant/Shared/` |
| `TenantEnergySpainUserList.vue` | `Pages/Tenant/EnergySpain/` |

---

## 7. Rutas generadas

### central

Archivo: `routes/web.php`

```php
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::prefix('central-users')->name('central.users.')->group(function () {
            // CRUD con middleware('central-permission:central_users_{action}')
        });
        // {{CENTRAL_APP_CENTRAL_END}}
    });
}
```

### shared

Archivo: `routes/web.php`

```php
Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('shared-users')->name('shared.users.')->group(function () {
        // CRUD con middleware('central-permission:shared_users_{action}')
    });
    // {{SHARED_SHARED_END}}
});
```

### tenant (específico)

Archivo: `routes/tenant.php`

```php
// ─────────────────────────────────────────────────────────────────
// Energía España — User
// ─────────────────────────────────────────────────────────────────
Route::middleware(['web', InitializeTenancyByDomain::class, ...])->group(function () {
    Route::prefix('energy-spain-users')->name('energy-spain.users.')->group(function () {
        // CRUD con middleware('tenant-permission:energy_spain_users_{action}')
    });
    // {{TENANT_ENERGY_SPAIN_END}}
});
```

### tenant_shared

Archivo: `routes/tenant.php` — genera un bloque por cada item del contexto `tenant`.

### Marcador de append

Formato: `// {{CONTEXT_SUBNAME_END}}` en SCREAMING_SNAKE_CASE del `class_prefix`.
Ejemplo: `class_prefix: TenantEnergySpain` → marcador `{{TENANT_ENERGY_SPAIN_END}}`

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
class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TenantEnergySpainUserServiceInterface::class,
            TenantEnergySpainUserService::class
        );
        $this->app->bind(
            TenantEnergySpainUserRepositoryInterface::class,
            TenantEnergySpainUserRepository::class
        );
    }
}
```

---

## 9. Trait RendersInertiaModule

**Namespace:** `Innodite\LaravelModuleMaker\Traits\RendersInertiaModule`

```php
use Innodite\LaravelModuleMaker\Traits\RendersInertiaModule;

class TenantEnergySpainUserController extends Controller
{
    use RendersInertiaModule;

    public function index(): InertiaResponse
    {
        return $this->renderModule('UserManagement', 'TenantEnergySpainUserIndex');
    }
}
```

**Resolución del prefijo:**
1. Extrae el prefijo del nombre del componente (`TenantEnergySpain`)
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

- [x] Trait `RendersInertiaModule` en el paquete con prefijos dinámicos desde contexts.json
- [x] Comando interactivo con selección de contexto y tenant
- [ ] **`contexts.json`:** migrar estructura a arrays de sub-items con `name`
- [ ] **Modo interactivo:** preguntar ¿Automático o desde JSON?
- [ ] **Modo interactivo:** si hay múltiples items en el contexto, preguntar cuál
- [ ] **Modo interactivo:** crear `module-maker-config/{module}.json` automáticamente con `context_name`
- [ ] **Marcadores de rutas:** usar `class_prefix` en SCREAMING_SNAKE_CASE (no nombre del contexto)
- [ ] **`RendersInertiaModule`:** actualizar para leer la nueva estructura de arrays
- [ ] **Modo `--json`:** si tiene atributos, generar la migration con las columnas correctas
- [ ] **Componente `--vue`:** generar el Vue con convención de nombres correcta
