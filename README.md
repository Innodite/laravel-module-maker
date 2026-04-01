# Innodite Laravel Module Maker

**v3.0.0** — Generador de módulos Laravel con arquitectura de contextos dinámicos (Central, Shared, Tenant) para proyectos multi-tenant. Genera backend completo, inyecta rutas y crea vistas Vue 3 listas para usar — todo con un solo comando.

---

## Tabla de Contenidos

- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Arquitectura Frontend](#arquitectura-frontend)
- [Guía de comandos](#guía-de-comandos)
- [Flujo completo por contexto](#flujo-completo-por-contexto)
- [Tabla comparativa de contextos](#tabla-comparativa-de-contextos)
- [Bridge Frontend-Backend](#bridge-frontend-backend)
- [Estructura de contextos](#estructura-de-contextos-contextsjson)
- [Estructura completa de un módulo generado](#estructura-completa-de-un-módulo-generado)
- [Convenciones de nomenclatura](#convenciones-de-nomenclatura)
- [Flujo de inyección de rutas](#flujo-de-inyección-de-rutas)
- [Auditoría](#auditoría)
- [Pruebas](#pruebas)
- [Estándares de código](#estándares-de-código)
- [Publicar en Packagist](#publicar-en-packagist--repositorio-privado)
- [Changelog](#changelog)
- [Licencia](#licencia)

---

## Requisitos

| Dependencia | Versión mínima |
|---|---|
| PHP | 8.2+ |
| Laravel | 11.0+ |
| illuminate/support | ^11.0\|^12.0 |
| illuminate/console | ^11.0\|^12.0 |
| illuminate/filesystem | ^11.0\|^12.0 |
| illuminate/routing | ^11.0\|^12.0 |
| @inertiajs/vue3 | ^1.0 (frontend) |
| Vue | ^3.0 (frontend) |

> Compatible opcionalmente con `stancl/tenancy` y `spatie/laravel-permission`.

---

## Instalación

```bash
composer require innodite/laravel-module-maker
```

Al instalar por primera vez, el paquete detecta la ausencia de configuración y sugiere el setup en consola.

### Inicializar el proyecto (requerido)

```bash
php artisan innodite:module-setup
```

Crea `module-maker-config/` en la raíz del proyecto con:
- `contexts.json` — Definición de contextos y tenants
- `stubs/contextual/` — Plantillas PHP y Vue personalizables

### Publicar assets manualmente

```bash
# Configuración make-module.php
php artisan vendor:publish --tag=module-maker-config

# Stubs PHP y Vue para personalización
php artisan vendor:publish --tag=module-maker-stubs

# contexts.json de ejemplo
php artisan vendor:publish --tag=module-maker-contexts

# Composables Vue 3
php artisan vendor:publish --tag=module-maker-frontend
```

---

## Arquitectura Frontend

> **Regla fundamental** — No negociable en este paquete.

| Responsabilidad | Tecnología |
|---|---|
| Navegación entre páginas | Inertia.js (`router.visit()`) |
| Carga y mutación de datos | axios (`GET`, `POST`, `PUT`, `DELETE`) |
| Contexto activo y permisos | Props de Inertia — compartidos por `InnoditeContextBridge` |

Los controladores retornan **JSON puro**. Las vistas Vue son *shells* que se autocargan al montarse vía axios. Inertia nunca transporta datos de negocio, solo gestiona la navegación SPA.

---

## Guía de comandos

### `innodite:make-module` — Generador principal

Genera backend completo + vistas Vue en un solo comando.

```bash
# Módulo completo (backend + vistas + rutas inyectadas)
php artisan innodite:make-module User --context=central

# Selección interactiva de contexto
php artisan innodite:make-module User

# Tenant específico (por name, class_prefix o slug)
php artisan innodite:make-module Product --context=tenant-one

# Contexto shared (rutas en web.php Y tenant.php simultáneamente)
php artisan innodite:make-module Invoice --context=shared

# Sin inyección de rutas en el proyecto
php artisan innodite:make-module Report --context=central --no-routes

# Componentes individuales en módulo existente
php artisan innodite:make-module User --context=central -S -R   # Service + Repository
php artisan innodite:make-module User --context=central -C      # Controller + rutas
php artisan innodite:make-module User --context=central -G      # Migration
php artisan innodite:make-module User --context=central -M -Q   # Model + Request

# Desde JSON de configuración dinámica
php artisan innodite:make-module User --json
```

**Flags de componentes:**

| Flag | Componente generado |
|---|---|
| `-M` / `--model` | Modelo Eloquent con `$table` definida |
| `-C` / `--controller` | Controlador JSON + inyección de rutas CRUD |
| `-S` / `--service` | Servicio + Interface en `Services/Contracts/` |
| `-R` / `--repository` | Repositorio + Interface en `Repositories/Contracts/` |
| `-G` / `--migration` | Migración anónima contextualizada |
| `-Q` / `--request` | Form Request validado |

**Validaciones de seguridad:**
- Nombres no PascalCase son rechazados
- Palabras reservadas de PHP y Laravel bloqueadas: `class`, `model`, `auth`, `route`, etc.
- Módulos duplicados bloqueados con opción de añadir componentes
- En caso de error, se ofrece **rollback** para eliminar archivos generados

---

### `innodite:module-setup` — Configuración inicial

```bash
php artisan innodite:module-setup
```

Crea la estructura de configuración del paquete en la raíz del proyecto. Debe ejecutarse una sola vez al inicializar un nuevo proyecto que use este paquete.

---

### `innodite:module-check` — Diagnóstico de entorno

```bash
php artisan innodite:module-check
```

Verifica el entorno del proyecto e informa sobre:

1. `contexts.json` — validez, estructura y claves requeridas
2. Permisos de escritura en `Modules/`, `routes/`, `storage/logs/`
3. Colisiones de nombres entre módulos y ServiceProviders
4. Últimas 5 entradas del log de auditoría

---

### `innodite:check-env` — Contrato de Datos Frontend-Backend

```bash
php artisan innodite:check-env
```

Verifica el bridge Inertia y, si algo falta, imprime el **bloque de código exacto** a copiar:

1. Modelo User — `HasRoles` (Spatie) o `InnoditeUserPermissions`
2. `HandleInertiaRequests` — `auth.permissions` compartido
3. `InnoditeContextBridge` — registrado en el stack web

---

### `innodite:publish-frontend` — Composables Vue 3

```bash
php artisan innodite:publish-frontend
php artisan innodite:publish-frontend --force  # sobreescribir
```

Publica en `resources/js/Composables/`:
- `useModuleContext.js`
- `usePermissions.js`

---

## Flujo completo por contexto

Esta sección documenta el flujo de generación completo para cada uno de los 4 contextos disponibles: qué archivos crea, dónde los ubica y cómo inyecta las rutas.

---

### Contexto `central`

**Comando:**

```bash
php artisan innodite:make-module User --context=central
```

#### Archivos PHP generados

| Archivo | Ubicación |
|---|---|
| `CentralUser.php` | `Modules/User/Models/Central/` |
| `CentralUserController.php` | `Modules/User/Http/Controllers/Central/` |
| `CentralUserService.php` | `Modules/User/Services/Central/` |
| `CentralUserServiceInterface.php` | `Modules/User/Services/Contracts/Central/` |
| `CentralUserRepository.php` | `Modules/User/Repositories/Central/` |
| `CentralUserRepositoryInterface.php` | `Modules/User/Repositories/Contracts/Central/` |
| `CentralUserStoreRequest.php` | `Modules/User/Http/Requests/Central/` |
| `UserServiceProvider.php` | `Modules/User/Providers/` |
| `*_create_central_users_table.php` | `Modules/User/Database/Migrations/Central/` |
| `UserDatabaseSeeder.php` | `Modules/User/Database/Seeders/` |
| `CentralUserTest.php` | `Modules/User/Tests/Unit/` |

#### Archivos Vue generados

| Archivo | Ubicación |
|---|---|
| `CentralUserIndex.vue` | `Modules/User/Resources/js/Pages/Central/` |
| `CentralUserCreate.vue` | `Modules/User/Resources/js/Pages/Central/` |
| `CentralUserEdit.vue` | `Modules/User/Resources/js/Pages/Central/` |
| `CentralUserShow.vue` | `Modules/User/Resources/js/Pages/Central/` |

#### Ruta inyectada en `routes/web.php`

```php
// Bloque generado para: User (Contexto: App Central)
Route::prefix('central')->name('central.')->middleware(['web','auth'])->group(function () {
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/',          [CentralUserController::class, 'index'])->name('index');
        Route::get('/create',    [CentralUserController::class, 'create'])->name('create');
        Route::post('/',         [CentralUserController::class, 'store'])->name('store');
        Route::get('/{id}',      [CentralUserController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [CentralUserController::class, 'edit'])->name('edit');
        Route::put('/{id}',      [CentralUserController::class, 'update'])->name('update');
        Route::delete('/{id}',   [CentralUserController::class, 'destroy'])->name('destroy');
    });
});
// {{CENTRAL_ROUTES_END}}
```

#### Resolución de `contextRoute()`

```js
contextRoute('users.index')
// Resuelve: 'central.users.index'
```

El composable `useModuleContext` lee `auth.context.route_prefix` desde las props de Inertia y antepone el prefijo de contexto activo a la clave de ruta.

---

### Contexto `shared`

**Comando:**

```bash
php artisan innodite:make-module Invoice --context=shared
```

#### Archivos PHP generados

| Archivo | Ubicación |
|---|---|
| `SharedInvoice.php` | `Modules/Invoice/Models/Shared/` |
| `SharedInvoiceController.php` | `Modules/Invoice/Http/Controllers/Shared/` |
| `SharedInvoiceService.php` | `Modules/Invoice/Services/Shared/` |
| `SharedInvoiceServiceInterface.php` | `Modules/Invoice/Services/Contracts/Shared/` |
| `SharedInvoiceRepository.php` | `Modules/Invoice/Repositories/Shared/` |
| `SharedInvoiceRepositoryInterface.php` | `Modules/Invoice/Repositories/Contracts/Shared/` |
| `SharedInvoiceStoreRequest.php` | `Modules/Invoice/Http/Requests/Shared/` |
| `InvoiceServiceProvider.php` | `Modules/Invoice/Providers/` |
| `*_create_shared_invoices_table.php` | `Modules/Invoice/Database/Migrations/Shared/` |
| `InvoiceDatabaseSeeder.php` | `Modules/Invoice/Database/Seeders/` |
| `SharedInvoiceTest.php` | `Modules/Invoice/Tests/Unit/` |

#### Archivos Vue generados

| Archivo | Ubicación |
|---|---|
| `SharedInvoiceIndex.vue` | `Modules/Invoice/Resources/js/Pages/Shared/` |
| `SharedInvoiceCreate.vue` | `Modules/Invoice/Resources/js/Pages/Shared/` |
| `SharedInvoiceEdit.vue` | `Modules/Invoice/Resources/js/Pages/Shared/` |
| `SharedInvoiceShow.vue` | `Modules/Invoice/Resources/js/Pages/Shared/` |

#### Dualidad de rutas — inyección simultánea en DOS archivos

El contexto `shared` es único: sus rutas son accesibles tanto desde el panel central como desde el panel tenant. Por eso el generador inyecta rutas en **dos archivos de rutas simultáneamente**.

**En `routes/web.php`** (acceso desde el panel central):

```php
// Bloque generado para: Invoice (Contexto: Shared — panel central)
Route::prefix('central/shared')->name('central.shared.')->middleware(['web','auth'])->group(function () {
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/',          [SharedInvoiceController::class, 'index'])->name('index');
        Route::get('/create',    [SharedInvoiceController::class, 'create'])->name('create');
        Route::post('/',         [SharedInvoiceController::class, 'store'])->name('store');
        Route::get('/{id}',      [SharedInvoiceController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [SharedInvoiceController::class, 'edit'])->name('edit');
        Route::put('/{id}',      [SharedInvoiceController::class, 'update'])->name('update');
        Route::delete('/{id}',   [SharedInvoiceController::class, 'destroy'])->name('destroy');
    });
});
// {{CENTRAL_ROUTES_END}}
```

**En `routes/tenant.php`** (acceso desde el panel tenant):

```php
// Bloque generado para: Invoice (Contexto: Shared — panel tenant)
Route::prefix('tenant/shared')->name('tenant.shared.')->middleware(['web','auth'])->group(function () {
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/',          [SharedInvoiceController::class, 'index'])->name('index');
        Route::get('/create',    [SharedInvoiceController::class, 'create'])->name('create');
        Route::post('/',         [SharedInvoiceController::class, 'store'])->name('store');
        Route::get('/{id}',      [SharedInvoiceController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [SharedInvoiceController::class, 'edit'])->name('edit');
        Route::put('/{id}',      [SharedInvoiceController::class, 'update'])->name('update');
        Route::delete('/{id}',   [SharedInvoiceController::class, 'destroy'])->name('destroy');
    });
});
// {{TENANT_SHARED_ROUTES_END}}
```

#### Resolución de `contextRoute()` en `shared`

El mismo componente Vue resuelve diferente según el panel activo, gracias a la prop `auth.context.route_prefix` inyectada por `InnoditeContextBridge`:

```js
// Desde el panel central (route_prefix = 'central.shared')
contextRoute('invoices.index')
// Resuelve: 'central.shared.invoices.index'

// Desde el panel tenant (route_prefix = 'tenant.shared')
contextRoute('invoices.index')
// Resuelve: 'tenant.shared.invoices.index'
```

Las vistas Vue no cambian — el composable adapta la ruta automáticamente según el contexto activo en sesión.

---

### Contexto `tenant_shared`

**Comando:**

```bash
php artisan innodite:make-module Role --context=tenant_shared
```

#### Archivos PHP generados

| Archivo | Ubicación |
|---|---|
| `TenantSharedRole.php` | `Modules/Role/Models/Tenant/Shared/` |
| `TenantSharedRoleController.php` | `Modules/Role/Http/Controllers/Tenant/Shared/` |
| `TenantSharedRoleService.php` | `Modules/Role/Services/Tenant/Shared/` |
| `TenantSharedRoleServiceInterface.php` | `Modules/Role/Services/Contracts/Tenant/Shared/` |
| `TenantSharedRoleRepository.php` | `Modules/Role/Repositories/Tenant/Shared/` |
| `TenantSharedRoleRepositoryInterface.php` | `Modules/Role/Repositories/Contracts/Tenant/Shared/` |
| `TenantSharedRoleStoreRequest.php` | `Modules/Role/Http/Requests/Tenant/Shared/` |
| `RoleServiceProvider.php` | `Modules/Role/Providers/` |
| `*_create_tenant_shared_roles_table.php` | `Modules/Role/Database/Migrations/Tenant/Shared/` |
| `RoleDatabaseSeeder.php` | `Modules/Role/Database/Seeders/` |
| `TenantSharedRoleTest.php` | `Modules/Role/Tests/Unit/` |

#### Archivos Vue generados

| Archivo | Ubicación |
|---|---|
| `TenantSharedRoleIndex.vue` | `Modules/Role/Resources/js/Pages/Tenant/Shared/` |
| `TenantSharedRoleCreate.vue` | `Modules/Role/Resources/js/Pages/Tenant/Shared/` |
| `TenantSharedRoleEdit.vue` | `Modules/Role/Resources/js/Pages/Tenant/Shared/` |
| `TenantSharedRoleShow.vue` | `Modules/Role/Resources/js/Pages/Tenant/Shared/` |

#### Ruta inyectada en `routes/tenant.php`

El contexto `tenant_shared` tiene `route_prefix: null` — las rutas se definen sin prefijo URL para que cada tenant acceda directamente bajo su propio dominio.

```php
// Bloque generado para: Role (Contexto: Tenant Shared)
Route::middleware(['web','auth'])->group(function () {
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/',          [TenantSharedRoleController::class, 'index'])->name('index');
        Route::get('/create',    [TenantSharedRoleController::class, 'create'])->name('create');
        Route::post('/',         [TenantSharedRoleController::class, 'store'])->name('store');
        Route::get('/{id}',      [TenantSharedRoleController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [TenantSharedRoleController::class, 'edit'])->name('edit');
        Route::put('/{id}',      [TenantSharedRoleController::class, 'update'])->name('update');
        Route::delete('/{id}',   [TenantSharedRoleController::class, 'destroy'])->name('destroy');
    });
});
// {{TENANT_SHARED_ROUTES_END}}
```

> **Nota:** Sin `route_prefix`, el nombre de ruta tampoco lleva prefijo de contexto. `contextRoute('roles.index')` devuelve simplemente `'roles.index'`.

---

### Contexto `tenant` (tenant específico — ej: TenantOne)

**Comando:**

```bash
php artisan innodite:make-module Product --context=tenant-one
```

El paquete resuelve `tenant-one` buscando en el array `tenant` de `contexts.json` por `name`, `class_prefix` o slug derivado del nombre.

#### Archivos PHP generados

| Archivo | Ubicación |
|---|---|
| `TenantOneProduct.php` | `Modules/Product/Models/Tenant/TenantOne/` |
| `TenantOneProductController.php` | `Modules/Product/Http/Controllers/Tenant/TenantOne/` |
| `TenantOneProductService.php` | `Modules/Product/Services/Tenant/TenantOne/` |
| `TenantOneProductServiceInterface.php` | `Modules/Product/Services/Contracts/Tenant/TenantOne/` |
| `TenantOneProductRepository.php` | `Modules/Product/Repositories/Tenant/TenantOne/` |
| `TenantOneProductRepositoryInterface.php` | `Modules/Product/Repositories/Contracts/Tenant/TenantOne/` |
| `TenantOneProductStoreRequest.php` | `Modules/Product/Http/Requests/Tenant/TenantOne/` |
| `ProductServiceProvider.php` | `Modules/Product/Providers/` |
| `*_create_tenant_one_products_table.php` | `Modules/Product/Database/Migrations/Tenant/TenantOne/` |
| `ProductDatabaseSeeder.php` | `Modules/Product/Database/Seeders/` |
| `TenantOneProductTest.php` | `Modules/Product/Tests/Unit/` |

#### Archivos Vue generados

| Archivo | Ubicación |
|---|---|
| `TenantOneProductIndex.vue` | `Modules/Product/Resources/js/Pages/Tenant/TenantOne/` |
| `TenantOneProductCreate.vue` | `Modules/Product/Resources/js/Pages/Tenant/TenantOne/` |
| `TenantOneProductEdit.vue` | `Modules/Product/Resources/js/Pages/Tenant/TenantOne/` |
| `TenantOneProductShow.vue` | `Modules/Product/Resources/js/Pages/Tenant/TenantOne/` |

#### Ruta inyectada en `routes/tenant.php`

```php
// Bloque generado para: Product (Contexto: Tenant One)
Route::prefix('tenant-one')->name('tenant-one.')->middleware(['web','auth','tenant-auth'])->group(function () {
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/',          [TenantOneProductController::class, 'index'])->name('index');
        Route::get('/create',    [TenantOneProductController::class, 'create'])->name('create');
        Route::post('/',         [TenantOneProductController::class, 'store'])->name('store');
        Route::get('/{id}',      [TenantOneProductController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [TenantOneProductController::class, 'edit'])->name('edit');
        Route::put('/{id}',      [TenantOneProductController::class, 'update'])->name('update');
        Route::delete('/{id}',   [TenantOneProductController::class, 'destroy'])->name('destroy');
    });
});
// {{TENANT_ONE_ROUTES_END}}
```

#### Resolución de `contextRoute()`

```js
contextRoute('products.index')
// Resuelve: 'tenant-one.products.index'
```

---

## Tabla comparativa de contextos

| Contexto key | Clase prefijo | Carpeta PHP | Carpeta Vue | Archivo de rutas | Marcador de ruta | Nombre de ruta ejemplo |
|---|---|---|---|---|---|---|
| `central` | `Central` | `Central/` | `Pages/Central/` | `routes/web.php` | `{{CENTRAL_ROUTES_END}}` | `central.users.index` |
| `shared` | `Shared` | `Shared/` | `Pages/Shared/` | `web.php` + `tenant.php` | `{{CENTRAL_ROUTES_END}}` + `{{TENANT_SHARED_ROUTES_END}}` | `central.shared.invoices.index` / `tenant.shared.invoices.index` |
| `tenant_shared` | `TenantShared` | `Tenant/Shared/` | `Pages/Tenant/Shared/` | `routes/tenant.php` | `{{TENANT_SHARED_ROUTES_END}}` | `roles.index` (sin prefijo) |
| `tenant` (TenantOne) | `TenantOne` | `Tenant/TenantOne/` | `Pages/Tenant/TenantOne/` | `routes/tenant.php` | `{{TENANT_ONE_ROUTES_END}}` | `tenant-one.products.index` |

> El contexto `tenant_shared` es el único con `route_prefix: null`, lo que elimina el prefijo de URL y nombre de ruta. Todos los demás contextos aplican su propio prefijo.

---

## Vistas Vue generadas

Cada módulo genera 4 vistas Vue 3 (`<script setup>`) que siguen la arquitectura axios + Inertia:

### Flujo de datos en cada vista

```
Montaje  → axios.get(route(contextRoute('users.index')))   ← carga datos
Guardar  → axios.post/put(route(...))                      ← muta datos
Navegar  → router.visit(route(contextRoute('users.xxx')))  ← Inertia solo navega
Permisos → can('users.edit')                               ← oculta/muestra UI
```

### `CentralUserIndex.vue` — Lista paginada

```vue
<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useModuleContext } from '@/Composables/useModuleContext'
import { usePermissions } from '@/Composables/usePermissions'

const { contextRoute } = useModuleContext()
const { can }          = usePermissions()

const items = ref([])
const meta  = ref({ current_page: 1, last_page: 1, total: 0 })

async function fetchItems(page = 1) {
    const { data } = await axios.get(route(contextRoute('users.index')), { params: { page } })
    items.value = data.data
    meta.value  = { current_page: data.current_page, last_page: data.last_page, total: data.total }
}

async function destroy(id) {
    if (!confirm('¿Eliminar?')) return
    await axios.delete(route(contextRoute('users.destroy'), { id }))
    fetchItems(meta.value.current_page)
}

onMounted(() => fetchItems())
</script>
```

- Botón "Nuevo" visible solo si `can('users.create')`
- Botones Editar/Eliminar visibles solo si `can('users.edit')` / `can('users.delete')`
- Paginación automática con botones numéricos
- Manejo de errores con mensaje visible en pantalla

### `CentralUserCreate.vue` — Formulario de creación

```vue
async function submit() {
    await axios.post(route(contextRoute('users.store')), form.value)
    router.visit(route(contextRoute('users.index')))  // navega con Inertia
}
```

- Errores de validación Laravel 422 mostrados campo a campo
- Botón deshabilitado durante el envío (previene doble submit)
- Cancela navegando al índice con `router.visit()`

### `CentralUserEdit.vue` — Formulario de edición

```vue
onMounted(async () => {
    const { data } = await axios.get(route(contextRoute('users.show'), { id: props.id }))
    form.value = { ...data }  // rellena el formulario con datos existentes
})

async function submit() {
    await axios.put(route(contextRoute('users.update'), { id: props.id }), form.value)
    router.visit(route(contextRoute('users.index')))
}
```

- Recibe `id` como prop de Inertia (solo el ID, no el objeto completo)
- Carga el registro vía axios al montarse
- Actualiza con `axios.put` y navega al índice

### `CentralUserShow.vue` — Vista de detalle

```vue
onMounted(async () => {
    const { data } = await axios.get(route(contextRoute('users.show'), { id: props.id }))
    item.value = data
})
```

- Muestra `created_at` y `updated_at` por defecto
- Botones Editar/Eliminar protegidos por `can()`
- Elimina con `axios.delete` y redirige al índice

### Personalización de vistas

Los stubs fuente están en `module-maker-config/stubs/contextual/` tras ejecutar:

```bash
php artisan vendor:publish --tag=module-maker-stubs
```

Archivos de vista editables:
- `vue-index.stub` — plantilla del listado
- `vue-create.stub` — plantilla del formulario de creación
- `vue-edit.stub` — plantilla del formulario de edición
- `vue-show.stub` — plantilla del detalle

---

## Bridge Frontend-Backend

### Middleware `InnoditeContextBridge`

Intercepta cada request e inyecta vía `Inertia::share()`:

| Prop | Valor ejemplo |
|---|---|
| `auth.context.route_prefix` | `central`, `tenant-one`, `central.shared` |
| `auth.context.permission_prefix` | `central`, `tenant_one`, `tenant` |
| `auth.permissions` | `['central.users.edit', 'users.view', ...]` |

**Cadena de resolución de permisos:**
1. Spatie Permission → `$user->getAllPermissions()->pluck('name')`
2. `InnoditeUserPermissions` → `$user->getInnoditePermissions()`
3. Fail-safe → `[]` + Warning en log

**Registrar en `bootstrap/app.php` (Laravel 11+):**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', [
        \Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge::class,
    ]);
})
```

**Alias para rutas específicas:**

```php
Route::middleware('innodite.bridge')->group(fn() => ...);
```

---

### Interfaz `InnoditeUserPermissions`

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

---

### Composable `useModuleContext.js`

```js
const { contextRoute, routePrefix, permissionPrefix } = useModuleContext()

route(contextRoute('roles.index'))
// Central       → 'central.roles.index'
// TenantOne     → 'tenant-one.roles.index'
// TenantShared  → 'roles.index'  (sin prefijo — fail-safe con warning DEV)
// Shared/web    → 'central.shared.roles.index'
// Shared/tenant → 'tenant.shared.roles.index'
```

---

### Composable `usePermissions.js`

```js
const { can, canAny, canAll } = usePermissions()

can('roles.edit')                      // true si tiene 'central.roles.edit' O 'roles.edit'
canAny(['roles.edit', 'roles.create']) // true si tiene al menos uno
canAll(['roles.view', 'roles.edit'])   // true si tiene todos
```

**Estrategia dual:** verifica `{prefix}.{perm}` y `{perm}` plano simultáneamente — el mismo componente funciona en cualquier contexto sin cambios.

---

## Estructura de contextos (`contexts.json`)

```json
{
    "contexts": {
        "central": [{
            "name": "App Central",
            "class_prefix": "Central",
            "folder": "Central",
            "namespace_path": "Central",
            "route_file": "web.php",
            "route_prefix": "central",
            "route_name": "central.",
            "permission_prefix": "central",
            "route_middleware": ["web", "auth"]
        }],
        "shared": [{
            "name": "Shared",
            "class_prefix": "Shared",
            "folder": "Shared",
            "namespace_path": "Shared",
            "route_file": ["web.php", "tenant.php"],
            "web_route_prefix": "central.shared",
            "web_route_name": "central.shared.",
            "tenant_route_prefix": "tenant.shared",
            "tenant_route_name": "tenant.shared.",
            "route_middleware": []
        }],
        "tenant_shared": [{
            "name": "Tenant Shared",
            "class_prefix": "TenantShared",
            "folder": "Tenant/Shared",
            "namespace_path": "Tenant\\Shared",
            "route_file": "tenant.php",
            "route_prefix": null,
            "route_name": null,
            "permission_prefix": "tenant",
            "route_middleware": []
        }],
        "tenant": [{
            "name": "Tenant One",
            "class_prefix": "TenantOne",
            "folder": "Tenant/TenantOne",
            "namespace_path": "Tenant\\TenantOne",
            "route_file": "tenant.php",
            "route_prefix": "tenant-one",
            "route_name": "tenant-one.",
            "permission_prefix": "tenant_one",
            "route_middleware": ["web", "auth", "tenant-auth"]
        }]
    }
}
```

> El array `tenant` puede contener múltiples entradas, una por cada tenant específico del proyecto. Cada entrada genera su propio marcador de rutas basado en su `class_prefix`.

---

## Estructura completa de un módulo generado

El siguiente árbol corresponde a `innodite:make-module User --context=central`:

```
Modules/
└── User/
    ├── Http/
    │   ├── Controllers/
    │   │   └── Central/
    │   │       └── CentralUserController.php      (retorna JSON puro)
    │   └── Requests/
    │       └── Central/
    │           └── CentralUserStoreRequest.php
    ├── Models/
    │   └── Central/
    │       └── CentralUser.php                    (con $table definida)
    ├── Services/
    │   ├── Central/
    │   │   └── CentralUserService.php
    │   └── Contracts/
    │       └── Central/
    │           └── CentralUserServiceInterface.php
    ├── Repositories/
    │   ├── Central/
    │   │   └── CentralUserRepository.php
    │   └── Contracts/
    │       └── Central/
    │           └── CentralUserRepositoryInterface.php
    ├── Providers/
    │   └── UserServiceProvider.php                (binding automático Interface↔Implementation)
    ├── Database/
    │   ├── Migrations/
    │   │   └── Central/
    │   │       └── *_create_central_users_table.php   (migración anónima)
    │   ├── Seeders/
    │   │   └── UserDatabaseSeeder.php
    │   └── Factories/
    │       └── UserFactory.php
    ├── Resources/
    │   └── js/
    │       └── Pages/
    │           └── Central/
    │               ├── CentralUserIndex.vue       (lista paginada, axios.get)
    │               ├── CentralUserCreate.vue      (formulario, axios.post)
    │               ├── CentralUserEdit.vue        (formulario, axios.get + axios.put)
    │               └── CentralUserShow.vue        (detalle, axios.get)
    ├── Routes/
    │   └── web.php                                (rutas CRUD — referencia local)
    └── Docs/
        ├── history.md
        ├── architecture.md
        └── schema.md
```

---

## Convenciones de nomenclatura

| Contexto | Prefijo de clase | Ejemplo Vue | Ejemplo PHP |
|---|---|---|---|
| `central` | `Central` | `CentralUserIndex.vue` | `CentralUserController.php` |
| `shared` | `Shared` | `SharedInvoiceIndex.vue` | `SharedInvoiceService.php` |
| `tenant_shared` | `TenantShared` | `TenantSharedRoleIndex.vue` | `TenantSharedRoleRepository.php` |
| `tenant` (TenantOne) | `TenantOne` | `TenantOneProductIndex.vue` | `TenantOneProductController.php` |

**Reglas adicionales:**
- El nombre del módulo siempre va en PascalCase (ej: `User`, `InvoiceItem`, `TaxReport`)
- Las migraciones son anónimas (`return new class extends Migration`) para evitar colisiones de nombres
- Los ServiceProviders llevan el nombre del módulo sin prefijo de contexto (`UserServiceProvider`, no `CentralUserServiceProvider`)
- Los seeders tampoco llevan prefijo de contexto (`UserDatabaseSeeder`)

---

## Flujo de inyección de rutas

### Marcadores en `routes/web.php`

```php
// Al final del archivo, por contexto central y shared-web:
// {{CENTRAL_ROUTES_END}}
```

### Marcadores en `routes/tenant.php`

```php
// Por contexto tenant_shared y shared-tenant:
// {{TENANT_SHARED_ROUTES_END}}

// Por cada tenant específico (uno por tenant):
// {{TENANT_ONE_ROUTES_END}}
// {{TENANT_TWO_ROUTES_END}}
```

### Proceso interno de inyección

```
1. resolveMarkerKey()   → contexto + route_file → clave del marcador
                          central + web.php      → CENTRAL_ROUTES_END
                          tenant-one + tenant.php → TENANT_ONE_ROUTES_END

2. blockExists()        → busca firma del bloque existente
                          si ya existe: OMITE (operación idempotente)

3. detectIndentation()  → inspecciona el archivo destino
                          preserva espacios o tabs del estilo existente

4. ensureUseStatement() → verifica que existe `use App\Http\Controllers\...`
                          inserta el `use` si no está presente

5. buildBlock()         → genera el grupo de 7 rutas CRUD con comentario de cabecera

6. str_replace()        → inserta el bloque inmediatamente antes del marcador
                          el marcador permanece en su lugar para futuros módulos
```

### Contexto `shared` — Dualidad de rutas

| Archivo destino | Prefijo URL | Nombre de ruta | Marcador |
|---|---|---|---|
| `routes/web.php` | `central/shared` | `central.shared.` | `{{CENTRAL_ROUTES_END}}` |
| `routes/tenant.php` | `tenant/shared` | `tenant.shared.` | `{{TENANT_SHARED_ROUTES_END}}` |

---

## Auditoría

`storage/logs/module_maker.log` — formato NDJSON (una entrada JSON por línea):

```json
{"timestamp":"2025-01-01T12:00:00+00:00","event":"module.created","package":"innodite/laravel-module-maker","version":"3.0.0","module":"User","context_key":"central","context_name":"App Central","routes":true}
```

| Evento | Cuándo se registra |
|---|---|
| `module.created` | Módulo completo generado correctamente |
| `module.components` | Componentes individuales añadidos a módulo existente |
| `routes.injected` | Rutas inyectadas exitosamente en el proyecto |
| `module.rollback` | Rollback ejecutado tras error durante la generación |

```php
// Acceso programático al log
ModuleAuditor::readLog();  // devuelve array de entradas
ModuleAuditor::logPath();  // devuelve ruta absoluta al archivo de log
```

---

## Pruebas

```bash
composer test           # todos los tests
composer test:unit      # solo unitarios
composer test:feature   # solo integración
composer test:coverage  # con cobertura HTML en /coverage
```

Los tests generados por `make-module` se ubican en `Modules/{Name}/Tests/Unit/` y tienen stubs base listos para ser completados.

---

## Estándares de código

```bash
composer lint         # verificar PSR-12
composer lint:fix     # corregir automáticamente
composer lint:strict  # verificar declaraciones strict_types
```

El paquete incluye configuración de PHP CS Fixer compatible con PSR-12. Todos los archivos PHP generados incluyen `declare(strict_types=1)` por defecto.

---

## Publicar en Packagist / repositorio privado

### Repositorio público (Packagist)

```bash
git init && git add . && git commit -m "feat: release v3.0.0"
git tag v3.0.0 && git push origin main --tags
```

Luego registrar el repositorio en [packagist.org](https://packagist.org) con la URL del repositorio.

### Repositorio privado (VCS)

Agregar en el `composer.json` del proyecto consumidor:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/innodite/laravel-module-maker"
        }
    ]
}
```

```bash
composer require innodite/laravel-module-maker:^3.0
```

---

## Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para el historial completo de versiones.

---

## Licencia

MIT — [Anthony Filgueira](https://www.innodite.com)
