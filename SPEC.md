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
3. Publica los stubs del paquete en `Modules/module-maker-config/stubs/`
4. Publica los JSONs de ejemplo (`blog.json`, `post.json`, etc.) en `Modules/module-maker-config/`
5. Publica `contexts.json` en `Modules/module-maker-config/contexts.json` ← **EDITAR ESTE**
6. Modifica `DatabaseSeeder.php` para incluir `InnoditeModuleSeeder`

> **IMPORTANTE:** El proyecto debe editar `Modules/module-maker-config/contexts.json` para configurar sus propios tenants y middleware. Los stubs publicados tienen prioridad sobre los del paquete.

---

## 3. contexts.json — Fuente de verdad del proyecto

Ubicación: `Modules/module-maker-config/contexts.json`

### Estructura

```json
{
    "_readme": "...",

    "contexts": {
        "central": { ... },
        "shared": { ... },
        "tenant": { ... },
        "tenant_shared": { ... }
    },

    "tenants": {
        "energy_spain": { ... },
        "telephony_spain": { ... },
        "telephony_peru": { ... }
    }
}
```

### Sección `contexts` — los 4 contextos arquitectónicos (FIJOS)

| Contexto | Descripción |
|---|---|
| `central` | Solo para la app central |
| `shared` | Compartido entre app central y todos los tenants |
| `tenant` | Para un tenant específico (se selecciona cuál al generar) |
| `tenant_shared` | Compartido por todos los tenants |

Cada contexto tiene:

```json
{
    "label": "Nombre legible para el CLI",
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
```

> El contexto `tenant` tiene `class_prefix: null`, `folder: null`, etc. — los valores reales vienen del tenant específico seleccionado.

### Sección `tenants` — instancias del contexto `tenant`

Cada tenant tiene los mismos campos que un contexto (con valores concretos):

```json
{
    "label": "Energía España",
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
}
```

---

## 4. Comando `innodite:make-module`

### Modo 1 — Interactivo (sin flags)

```bash
php artisan innodite:make-module NombreModulo
```

**Flujo:**
1. Pregunta: ¿En qué contexto? (lista de `contexts.contexts`)
2. Si selecciona `tenant`: pregunta ¿Para cuál tenant? (lista de `contexts.tenants`)
3. Pregunta: nombre de la funcionalidad para rutas (default: nombre módulo en plural kebab)
4. Genera todos los archivos con el contexto y tenant seleccionados
5. **TODO:** Crear `Modules/module-maker-config/{module}.json` con la config básica

**Archivos generados:**
- `Http/Controllers/{contextFolder}/{PrefixModuloController}.php`
- `Services/{contextFolder}/{PrefixModuloService}.php`
- `Services/{contextFolder}/Contracts/{PrefixModuloServiceInterface}.php`
- `Repositories/{contextFolder}/{PrefixModuloRepository}.php`
- `Repositories/{contextFolder}/Contracts/{PrefixModuloRepositoryInterface}.php`
- `Http/Requests/{Modulo}StoreRequest.php`
- `Models/{Modulo}.php`
- `Providers/{Modulo}ServiceProvider.php`
- `routes/web.php` (central/shared) o `routes/tenant.php` (tenant/tenant_shared)
- `Database/Migrations/{timestamp}_create_{modulos}_table.php`
- `Database/Seeders/{Modulo}Seeder.php`
- `Database/Factories/{Modulo}Factory.php`
- `Tests/Unit/{Modulo}Test.php`

**Ejemplo de resultado con contexto `tenant` → `energy_spain`, funcionalidad `users`:**
```
Http/Controllers/Tenant/EnergySpain/TenantEnergySpainUserController.php
Services/Tenant/EnergySpain/TenantEnergySpainUserService.php
Services/Tenant/EnergySpain/Contracts/TenantEnergySpainUserServiceInterface.php
Repositories/Tenant/EnergySpain/TenantEnergySpainUserRepository.php
Repositories/Tenant/EnergySpain/Contracts/TenantEnergySpainUserRepositoryInterface.php
routes/tenant.php  ← con bloque energy-spain-users
```

### Modo 2 — Desde JSON (`--json`)

```bash
php artisan innodite:make-module NombreModulo --json
```

**Flujo:**
1. Busca `Modules/module-maker-config/{nombremodulo}.json`
2. Lee la configuración completa (componentes, contextos, atributos, relaciones)
3. Genera todos los archivos con la config del JSON

**Formato del JSON del módulo:**

```json
{
    "module_name": "User",
    "components": [
        {
            "name": "User",
            "context": "tenant",
            "tenant": "energy_spain",
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

> **Nota:** Un módulo puede tener múltiples componentes en distintos contextos dentro del mismo JSON.

### Modo 3 — Componente individual

```bash
php artisan innodite:make-module NombreModulo --model=User
php artisan innodite:make-module NombreModulo --controller=UserController
php artisan innodite:make-module NombreModulo --service=UserService
php artisan innodite:make-module NombreModulo --repository=UserRepository
php artisan innodite:make-module NombreModulo --migration=User
php artisan innodite:make-module NombreModulo --request=UserStoreRequest
```

> El módulo debe existir previamente. Se puede combinar: `--model=User --migration=User`

---

## 5. Convención de nombres

### PHP (backend)

| Contexto | class_prefix | Ejemplo |
|---|---|---|
| central | `Central` | `CentralUserController` |
| shared | `Shared` | `SharedUserController` |
| tenant_shared | `TenantShared` | `TenantSharedUserController` |
| tenant → energy_spain | `TenantEnergySpain` | `TenantEnergySpainUserController` |
| tenant → telephony_spain | `TenantTelephonySpain` | `TenantTelephonySpainUserController` |
| tenant → telephony_peru | `TenantTelephonyPeru` | `TenantTelephonyPeruUserController` |

### Carpetas (backend)

| Contexto | folder |
|---|---|
| central | `Central/` |
| shared | `Shared/` |
| tenant_shared | `Tenant/Shared/` |
| tenant → energy_spain | `Tenant/EnergySpain/` |

### Vue (frontend) — mismo prefijo que PHP

| Componente | Carpeta resuelta por RendersInertiaModule |
|---|---|
| `CentralUserList.vue` | `Pages/Central/` |
| `SharedUserList.vue` | `Pages/Shared/` |
| `TenantSharedUserList.vue` | `Pages/Tenant/Shared/` |
| `TenantEnergySpainUserList.vue` | `Pages/Tenant/EnergySpain/` |

---

## 6. Rutas generadas

### central

```php
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::prefix('central-users')->name('central.users.')->group(function () {
            // CRUD routes con middleware('central-permission:central_users_{action}')
        });
        // {{CENTRAL_END}}
    });
}
```

Archivo: `routes/web.php`

### shared

```php
Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('shared-users')->name('shared.users.')->group(function () {
        // CRUD routes con middleware('central-permission:shared_users_{action}')
    });
    // {{SHARED_END}}
});
```

Archivo: `routes/web.php`

### tenant (específico)

```php
// ─────────────────────────────────────────────────
// Energía España — User
// ─────────────────────────────────────────────────
Route::middleware(['web', InitializeTenancyByDomain::class, ...])->group(function () {
    Route::prefix('energy-spain-users')->name('energy-spain.users.')->group(function () {
        // CRUD routes con middleware('tenant-permission:energy_spain_users_{action}')
    });
    // {{ENERGY_SPAIN_END}}
});
```

Archivo: `routes/tenant.php`

### tenant_shared

Genera un bloque por cada tenant definido en `contexts.tenants`, todos en `routes/tenant.php`.

### Marcadores para append

Cuando el archivo de rutas ya existe, el comando detecta el marcador (`// {{ENERGY_SPAIN_END}}`) e inserta el nuevo bloque antes de él, sin tocar el resto del archivo.

### CRUD routes generadas por defecto

```
GET    /                → index   (permiso: {prefix}_{func}_index)
GET    /list            → list    (permiso: {prefix}_{func}_index)
POST   /                → store   (permiso: {prefix}_{func}_store)
GET    /{id}            → show    (permiso: {prefix}_{func}_show)
PUT    /{id}            → update  (permiso: {prefix}_{func}_update)
DELETE /{id}            → destroy (permiso: {prefix}_{func}_delete)
```

---

## 7. Provider generado

```php
class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantEnergySpainUserServiceInterface::class, TenantEnergySpainUserService::class);
        $this->app->bind(TenantEnergySpainUserRepositoryInterface::class, TenantEnergySpainUserRepository::class);
    }
}
```

Los namespaces se derivan automáticamente del contexto y tenant seleccionados.

---

## 8. Trait RendersInertiaModule

**Namespace:** `Innodite\LaravelModuleMaker\Traits\RendersInertiaModule`

Uso en controller:

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

**Resolución:**
1. Lee el prefijo del nombre del componente (`TenantEnergySpain`)
2. Busca ese prefijo en `contexts.tenants` y `contexts.contexts` de `contexts.json`
3. Mapea al `folder` correspondiente (`Tenant/EnergySpain`)
4. Verifica que el `.vue` existe en `Modules/{Module}/resources/js/Pages/{folder}/{component}.vue`
5. Lanza `RuntimeException` con mensaje descriptivo si no existe

---

## 9. Stubs

Los stubs se publican en `Modules/module-maker-config/stubs/` y tienen prioridad sobre los del paquete. El proyecto puede personalizarlos.

### Stubs disponibles (clean)

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

## 10. Pendientes / TODO

- [ ] **Modo interactivo:** crear `module-maker-config/{module}.json` automáticamente al generar
- [ ] **Modo `--json`:** si el JSON ya tiene atributos, generar la migration con las columnas
- [ ] **Componente `--vue`:** generar el archivo Vue con la convención de nombres correcta
- [ ] **Comando `innodite:make-module NombreModulo --add-tenant=telephony_spain`:** agregar un tenant al módulo existente (genera controller/service/repo/rutas para ese tenant)
- [ ] **Validación:** verificar que el módulo no rompe el autoload antes de generarlo

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
