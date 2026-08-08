# 🏗️ Innodite Laravel Module Maker

[![Tests](https://github.com/Innodite/laravel-module-maker/actions/workflows/tests.yml/badge.svg)](https://github.com/Innodite/laravel-module-maker/actions/workflows/tests.yml)
[![Coverage](https://github.com/Innodite/laravel-module-maker/actions/workflows/coverage.yml/badge.svg)](https://github.com/Innodite/laravel-module-maker/actions/workflows/coverage.yml)
[![Latest Version](https://img.shields.io/github/v/tag/Innodite/laravel-module-maker?label=version&color=indigo)](https://github.com/Innodite/laravel-module-maker/releases)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11%2B%20%7C%2012-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![License](https://img.shields.io/github/license/Innodite/laravel-module-maker?color=green)](LICENSE)

**v3.5.3** — Generador de módulos Laravel con arquitectura de contextos dinámicos (Central, Shared, Tenant) para proyectos multi-tenant. Genera backend completo, inyecta rutas y crea vistas Vue 3 listas para usar — todo con un solo comando. Soporta múltiples entidades por módulo con subcarpeta aislada por entidad (`{Tipo}/{Contexto}/{Entidad}/`).

## ⚠️ Versiones Deprecadas

Se consideran **deprecados** los tags históricos con referencias heredadas a software/proyecto externo.

Tags deprecados:
- `v2.5.0`
- `v3.2.7` a `v3.4.0`

Versión mínima recomendada para uso nuevo:
- `v3.4.1+`

> Nota: la deprecación es de soporte/uso recomendado. No se reescribe el historial Git publicado.

---

## 📋 Tabla de Contenidos

- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Tabla comparativa de contextos](#-tabla-comparativa-de-contextos)
- [Versiones Deprecadas](#-versiones-deprecadas)
- [Arquitectura Frontend](#-arquitectura-frontend)
- [Guía de comandos](#-guía-de-comandos)
- [Archivos generados por contexto](#-archivos-generados-por-contexto)
- [Flujo completo por contexto](#-flujo-completo-por-contexto)
- [Composables Vue 3](#-composables-vue-3)
- [Stubs contextuales](#-stubs-contextuales)
- [Bridge Frontend-Backend](#-bridge-frontend-backend)
- [Estructura de contextos](#-estructura-de-contextos-contextsjson)
- [Estructura de árbol de un módulo generado](#-estructura-de-árbol-de-un-módulo-generado)
- [Convenciones de nomenclatura](#-convenciones-de-nomenclatura)
- [Flujo de inyección de rutas](#-flujo-de-inyección-de-rutas)
- [Auditoría](#-auditoría)
- [Pruebas](#-pruebas)
- [Estándares de código](#-estándares-de-código)
- [Publicar en Packagist](#-publicar-en-packagist--repositorio-privado)
- [Changelog](#-changelog)
- [Licencia](#-licencia)

**Nuevos en v3.5.x:**
- [Subcarpeta por entidad](#-subcarpeta-por-entidad-r05) — patrón `{Tipo}/{Contexto}/{Entidad}/`
- [`innodite:add-entity`](#-innoditeadd-entity--agregar-entidad-a-módulo-existente) — nuevo comando para módulos multi-entidad

---

## ✅ Requisitos

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

## 🚀 Instalación

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

# Stubs PHP y Vue para personalización (4 carpetas contextuales)
php artisan vendor:publish --tag=module-maker-stubs

# contexts.json de ejemplo
php artisan vendor:publish --tag=module-maker-contexts

# Composables Vue 3 (useModuleContext, usePermissions)
php artisan vendor:publish --tag=module-maker-frontend
```

---

## 🗺️ Tabla comparativa de contextos

Los 4 contextos disponibles cubren todos los escenarios de un proyecto multi-tenant:

| Contexto key | Prefijo de clase | Carpeta PHP | Carpeta Vue | Archivo de rutas | Nombre de ruta ejemplo | Archivos generados |
|---|---|---|---|---|---|---|
| `central` | `Central` | `Central/` | `Pages/Central/` | `routes/web.php` | `central.users.index` | 24 |
| `shared` | `Shared` | `Shared/` | `Pages/Shared/` | `web.php` + `tenant.php` | `central.shared.invoices.index` | 16 |
| `tenant_shared` | `TenantShared` | `Tenant/Shared/` | `Pages/Tenant/Shared/` | `routes/tenant.php` | `roles.index` (sin prefijo) | 17 |
| `tenant` (ej: INNODITE) | `TenantINNODITE` | `Tenant/INNODITE/` | `Pages/Tenant/INNODITE/` | `routes/tenant.php` | `innodite.products.index` | 20 |

> **Descripción rápida de cada contexto:**
> - `central` → Panel administrativo global. Rutas en `web.php`. Prefijo `Central`.
> - `shared` → Código híbrido accesible tanto desde el panel central como desde el panel tenant. Inyecta rutas en DOS archivos simultáneamente.
> - `tenant_shared` → Estándar para todos los tenants. Sin prefijo de URL ni de nombre de ruta.
> - `tenant` → Tenants específicos del proyecto (INNODITE, ACME, etc.). Un array en `contexts.json`, cada entrada genera su propio espacio aislado.

---

## 🖥️ Arquitectura Frontend

> **Regla fundamental — No negociable en este paquete.**

| Responsabilidad | Tecnología |
|---|---|
| Navegación entre páginas | Inertia.js (`router.visit()`, `router.get()`) |
| Carga y mutación de datos | axios (`GET`, `POST`, `PUT`, `DELETE`) |
| Contexto activo y permisos | Props de Inertia — compartidos por `InnoditeContextBridge` |

Los controladores utilizan el trait `RendersInertiaModule` y el método `renderModule()` para devolver la vista Inertia correcta según el contexto. **Nunca** pasan datos de negocio por props de Inertia.

Las vistas Vue son *shells* que se autocargan al montarse vía axios. Inertia nunca transporta datos de negocio; solo gestiona la navegación SPA.

```php
// Controlador — uso de renderModule()
class CentralUserController extends Controller
{
    use RendersInertiaModule;

    public function index(): JsonResponse
    {
        $users = $this->service->paginate();
        return response()->json($users);
    }

    public function create(): \Inertia\Response
    {
        return $this->renderModule('CentralUserCreate');
        // Retorna la vista Inertia — sin datos de negocio
    }
}
```

```js
// Vista Vue — carga sus propios datos al montarse
onMounted(async () => {
    const { data } = await axios.get(route(contextRoute('users.index')))
    items.value = data.data
})
```

---

## 🛠️ Guía de comandos

### `innodite:make-module` — Generador principal

Genera backend completo + vistas Vue en un solo comando.

```bash
# Módulo completo (backend + vistas + rutas inyectadas)
php artisan innodite:make-module User --context=central

# Selección interactiva de contexto
php artisan innodite:make-module User

# Tenant específico (por name, class_prefix o slug)
php artisan innodite:make-module Product --context=innodite

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
| `-C` / `--controller` | Controlador con `RendersInertiaModule` + inyección de rutas CRUD |
| `-S` / `--service` | Servicio + Interface en `Services/Contracts/` |
| `-R` / `--repository` | Repositorio + Interface en `Repositories/Contracts/` |
| `-G` / `--migration` | Migración anónima contextualizada |
| `-Q` / `--request` | Form Request validado (Store y Update para Central/Tenant, uno para Shared/TenantShared) |

**Validaciones de seguridad:**
- Nombres no PascalCase son rechazados
- Palabras reservadas de PHP y Laravel bloqueadas: `class`, `model`, `auth`, `route`, etc.
- Módulos duplicados bloqueados con opción de añadir componentes
- En caso de error, se ofrece **rollback** para eliminar archivos generados

---

### `innodite:add-entity` — Agregar entidad a módulo existente

Agrega una nueva entidad a un módulo **ya existente**, generando sus componentes dentro de la subcarpeta de entidad correspondiente. Diseñado para módulos multi-entidad como `UserManagement` (con `User`, `Role`, `Permission`, `Module`).

```bash
# Agregar entidad Role al módulo UserManagement en contexto central
php artisan innodite:add-entity UserManagement Role --context=central

# Solo componentes específicos
php artisan innodite:add-entity UserManagement Permission --context=central -M -C -S -R -G -Q

# Sin inyectar rutas
php artisan innodite:add-entity UserManagement Module --context=central --no-routes

# Para un tenant específico
php artisan innodite:add-entity UserManagement Role --context=acme
```

**Firma:**

```
innodite:add-entity {module} {entity} {--context=} [-M] [-C] [-S] [-R] [-G] [-Q] [--no-routes]
```

| Argumento | Descripción |
|---|---|
| `module` | Nombre del módulo existente (ej: `UserManagement`) |
| `entity` | Nombre de la entidad nueva (ej: `Role`, `Permission`) |
| `--context=` | ID del contexto destino (ej: `central`, `acme`) |
| `-M` a `-Q` | Mismos flags que `make-module` (sin flags = genera todos los componentes) |
| `--no-routes` | Omite la inyección de rutas |

**Ejemplo de archivos generados** — `add-entity UserManagement Role --context=central`:

```
Modules/UserManagement/
├── Models/Central/Role/CentralRole.php
├── Http/Controllers/Central/Role/CentralRoleController.php
├── Http/Requests/Central/Role/CentralRoleStoreRequest.php
├── Http/Requests/Central/Role/CentralRoleUpdateRequest.php
├── Services/Central/Role/CentralRoleService.php
├── Services/Contracts/Central/Role/CentralRoleServiceInterface.php
├── Repositories/Central/Role/CentralRoleRepository.php
├── Repositories/Contracts/Central/Role/CentralRoleRepositoryInterface.php
└── Database/Migrations/Central/Role/..._create_roles_table.php
```

**Diferencia con `make-module`:**

| | `make-module` | `add-entity` |
|---|---|---|
| Crea módulo nuevo | ✅ | ❌ |
| Agrega a módulo existente | ❌ | ✅ |
| Valida que el módulo exista primero | — | ✅ |
| Sin flags = genera todos los componentes | ✅ | ✅ |
| Naming convention intacta | ✅ | ✅ |

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

### `innodite:migrate-plan` — Aplica el esquema del contexto

Ejecuta las migraciones del proyecto **en el orden que declaran los traits `MigrationsList`** de cada
subfuncionalidad. Antes de ejecutar valida la conexión del contexto y comprueba que la base de datos
exista, para no lanzar un despliegue parcial ni contra la base equivocada.

```bash
# Todas las migraciones del contexto central
php artisan innodite:migrate-plan --context=central

# Ver el plan sin aplicar nada
php artisan innodite:migrate-plan --context=tenant_shared --dry-run
```

| Opción | Descripción |
|---|---|
| `--context=` | **Obligatoria.** Contra qué contexto se ejecuta: `central`, `shared`, `tenant_shared` o el id de un tenant |
| `--dry-run` | Muestra el plan y no aplica nada |

> **El orden vive en el módulo, no en un JSON.** Cada subfuncionalidad declara sus migraciones en su
> trait `MigrationsList`, así que el orden viaja con el módulo cuando se copia a otro proyecto. El
> contexto se dice en voz alta con `--context`, y de él salen **a la vez** las migraciones que se
> seleccionan y la conexión contra la que se aplican.

---

### `innodite:migrate-one` — Ejecutar una migración específica

Ejecuta una sola migración, nombrada por su coordenada `Modulo:Contexto/archivo.php`. La base de
datos sale de la propia coordenada, que ya lleva encima su carpeta de contexto.

```bash
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php"

# Sin confirmación interactiva
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php" --yes

# Ver qué haría
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php" --dry-run
```

| Opción | Descripción |
|---|---|
| `--context=` | Fuerza el contexto de ejecución en vez de derivarlo de la coordenada |
| `--yes` | Omite la confirmación |
| `--dry-run` | Muestra lo que haría sin ejecutar |

---

### `innodite:deploy` — Levanta el proyecto entero

Esquema, datos y permisos, en el orden declarado en `deploy` (`config/make-module.php`), más el
webmaster al cerrar. Es el comando que sustituye a `innodite:seed-one` y a `innodite:migration-sync`.

```bash
# Aplicación única
php artisan innodite:deploy production

# Multitenant: dos despliegues, contra dos bases de datos
php artisan innodite:deploy production --context=central
php artisan innodite:deploy stage --context=tenant
```

| Argumento / opción | Descripción |
|---|---|
| `entorno` | **Obligatorio.** `stage` o `production`. Nada por defecto: `stage` puede reconstruir desde cero |
| `--context=` | Obligatorio en multitenant: `central` o `tenant` |
| `--force` | No pedir confirmación aunque `SEEDER_DESTRUCTIVE` esté activo |

El seeder que invoca lo escribe el instalador en `database/seeders/`, y es donde se lee y se amplía
la secuencia de pasos del despliegue.

> **Retirados en la v4:** `innodite:seed-one` y `innodite:migration-sync`. Los dos existían para
> mantener los manifiestos `*.order.json`, que ya no lee nadie: el orden de las migraciones lo
> declaran los traits `MigrationsList` y el de los seeders el array `deploy`. Si tu proyecto todavía
> tiene esa carpeta, los comandos de migración te lo dirán al ejecutarse; puedes borrarla.

---


### `innodite:test` — Ejecutar el contrato de una subfuncionalidad

```bash
# El contrato completo de una subfuncionalidad
php artisan innodite:test Invoice Payment

# En multitenant, diciendo en qué contexto vive
php artisan innodite:test Invoice Payment --context=central

# Acotando dentro de una pieza
php artisan innodite:test Invoice Payment --filter=test_no_se_ve_sin_permiso

# Sin corte temprano, para ver el grupo entero de una pasada
php artisan innodite:test Invoice Payment --continuar
```

**Se ejecuta por subfuncionalidad, no por módulo**, porque el contrato es de ella: son sus nueve
temas, su manifiesto y sus seis piezas. Un módulo con cuatro subfuncionalidades no tiene un
contrato, tiene cuatro — y ejecutarlos juntos mezcla el resultado de cosas que se despliegan y
fallan por separado.

**En cascada, y con corte temprano:**

```
Scaffold  →  Schema     →  Permissions  →  Deployment  →  Http
tema 0       temas 1-2     temas 3-5       tema 8         tema 7

tema 6 (la vista) lo ejecuta Vitest, aparte
```

El orden es de dependencia: cada pieza da por supuesto lo que comprobó la anterior. Si el andamiaje
no está, el esquema falla por lo mismo; si el esquema no está, los permisos fallan por lo mismo; y el
comportamiento por HTTP falla de treinta formas distintas por la misma causa única.

Treinta fallos rojos de un solo problema no informan treinta veces mejor: informan **peor**, porque
hay que leerlos todos para descubrir que eran el mismo. Por eso al primer fallo se para, y dice qué
falló, qué cubría y **qué queda sin ejecutar**.

**Antes de lanzar nada comprueba que el grupo esté entero.** Faltar el manifiesto no es «una prueba
menos»: sin él ninguna de las otras puede derivar rutas ni permisos, y lo que saldría serían fallos
que describen el síntoma y esconden la causa.

**El tema 6 se nombra siempre**, también cuando todo pasa. Un contrato «en verde» que se saltó un
tema sin decirlo es la peor clase de silencio.

#### Qué tiene que traer tu proyecto para que el grupo corra

El grupo generado se escribe **contra las tablas y contra el router**, no contra las clases de un
paquete concreto — así sobrevive a que cambies de versión, o de paquete de permisos. A cambio da por
supuestas cinco cosas, y las cinco las pone el proyecto, no el módulo:

| Lo que hace falta | Por qué |
|---|---|
| `Tests\TestCase` | La base del grupo la extiende. Es la de Laravel de toda la vida |
| `Modules\` en el autoload PSR-4 de Composer | Sin él las piezas no se cargan |
| `users` y las tablas de permisos | `permissions` (con `description` y `module_id`), `modules`, `roles`, `model_has_permissions` y `role_has_permissions`. El seeder generado llena `description` y `module_id` **si existen** |
| El alias del middleware de permiso, registrado | El que use tu proyecto: `permission`, `central-permission`, `tenant-permission`… El grupo lo lee de la propia ruta, así que reconoce cualquiera que termine en `permission` |
| `APP_KEY` | El grupo `web` cifra la sesión: sin clave, la petición revienta **antes** de llegar al middleware de permiso, y el fallo no se parece en nada a su causa |

Y una consecuencia del diseño de las migraciones: el trait `MigrationsList` declara sus rutas
**relativas a la raíz del proyecto**, porque así las recibe `migrate --path`. El módulo tiene que
vivir bajo esa raíz para que el seeder las encuentre.

**Tres pruebas nacen saltadas, y es a propósito.** Las de validación —entrada inválida, edición
inválida, y la de crear y volver a leer— se saltan mientras el FormRequest generado no declare
reglas: sin `rules()` no hay nada que pueda fallar la validación, y una prueba que pasa porque no
comprueba nada es peor que una que dice que no corrió. **Escribe tus reglas y corren solas.**

> **Viene de `innodite:test-module`, que ya no existe.** El comando anterior trabajaba por módulo y
> contexto leyendo `Modules/{Modulo}/Tests/test-config.json`. Ese archivo era el último manifiesto
> JSON del paquete y se retiró junto con `innodite:test-sync`, que lo generaba: el contexto lo dice
> ahora el comando (`--context`) y la forma del módulo la dice el modo. Un proyecto que aún tenga
> esos archivos recibe un **aviso** al ejecutar `innodite:test` — no un error: el proyecto funciona
> sin ellos, y puedes borrarlos cuando hayas comprobado que no te falta nada de ellos.
---

## 📁 Archivos generados por contexto

Esta sección muestra la lista exacta de archivos que el paquete genera para el módulo `User` en cada uno de los 4 contextos.

---

### Contexto `central` — 24 archivos

```
Modules/User/
├── Http/Controllers/Central/User/CentralUserController.php
├── Http/Requests/Central/User/CentralUserStoreRequest.php
├── Http/Requests/Central/User/CentralUserUpdateRequest.php
├── Services/Central/User/CentralUserService.php
├── Services/Contracts/Central/User/CentralUserServiceInterface.php
├── Repositories/Central/User/CentralUserRepository.php
├── Repositories/Contracts/Central/User/CentralUserRepositoryInterface.php
├── Models/Central/User/CentralUser.php
├── Database/Migrations/Central/User/XXXX_create_users_table.php
├── Database/Seeders/Central/User/CentralUserSeeder.php
├── Database/Factories/Central/User/CentralUserFactory.php
├── Tests/Feature/Central/User/CentralUserContract.php
├── Tests/Feature/Central/User/CentralUserTestCase.php
├── Tests/Feature/Central/User/CentralUserScaffoldTest.php
├── Tests/Feature/Central/User/CentralUserSchemaTest.php
├── Tests/Feature/Central/User/CentralUserPermissionsTest.php
├── Tests/Feature/Central/User/CentralUserDeploymentTest.php
├── Tests/Feature/Central/User/CentralUserHttpTest.php
├── Resources/js/Pages/Central/CentralUserIndex.vue
├── Resources/js/Pages/Central/CentralUserCreate.vue
├── Resources/js/Pages/Central/CentralUserEdit.vue
├── Resources/js/Pages/Central/CentralUserShow.vue
├── Jobs/Central/CentralUserExportJob.php
├── Notifications/Central/CentralUserWelcomeNotification.php
├── Console/Commands/Central/CentralUserCleanupCommand.php
├── Exceptions/Central/CentralUserNotFoundException.php
├── Providers/UserServiceProvider.php
└── Routes/web.php
```

> **v3.5.x** — Los componentes principales (Model, Controller, Requests, Service, Repository, Migration) se generan dentro de una subcarpeta con el nombre de la entidad: `{Tipo}/{Contexto}/{Entidad}/`. Las vistas Vue, Tests, Jobs, Notifications y Commands mantienen su estructura anterior (sin subcarpeta de entidad).

---

### Contexto `shared` — 16 archivos

```
Modules/User/
├── Http/Controllers/Shared/User/SharedUserController.php
├── Http/Requests/Shared/User/SharedUserRequest.php
├── Services/Shared/User/SharedUserService.php
├── Services/Contracts/Shared/User/SharedUserServiceInterface.php
├── Repositories/Shared/User/SharedUserRepository.php
├── Repositories/Contracts/Shared/User/SharedUserRepositoryInterface.php
├── Models/Shared/User/SharedUser.php
├── Database/Migrations/Shared/User/XXXX_create_users_table.php
├── Database/Seeders/Shared/User/SharedUserSeeder.php
├── Database/Factories/Shared/User/SharedUserFactory.php
├── Tests/Feature/Shared/User/SharedUserContract.php
├── Tests/Feature/Shared/User/SharedUserTestCase.php
├── Tests/Feature/Shared/User/SharedUserScaffoldTest.php
├── Tests/Feature/Shared/User/SharedUserSchemaTest.php
├── Tests/Feature/Shared/User/SharedUserPermissionsTest.php
├── Tests/Feature/Shared/User/SharedUserDeploymentTest.php
├── Tests/Feature/Shared/User/SharedUserHttpTest.php
├── Resources/js/Pages/Shared/SharedUserIndex.vue
├── Resources/js/Pages/Shared/SharedUserCreate.vue
├── Resources/js/Pages/Shared/SharedUserEdit.vue
└── Resources/js/Pages/Shared/SharedUserShow.vue
```

---

### Contexto `tenant_shared` — 17 archivos

```
Modules/User/
├── Http/Controllers/Tenant/Shared/User/TenantSharedUserController.php
├── Http/Requests/Tenant/Shared/User/TenantSharedUserRequest.php
├── Services/Tenant/Shared/User/TenantSharedUserService.php
├── Services/Contracts/Tenant/Shared/User/TenantSharedUserServiceInterface.php
├── Repositories/Tenant/Shared/User/TenantSharedUserRepository.php
├── Repositories/Contracts/Tenant/Shared/User/TenantSharedUserRepositoryInterface.php
├── Models/Tenant/Shared/User/TenantSharedUser.php
├── Database/Migrations/Tenant/Shared/User/XXXX_create_users_table.php
├── Database/Seeders/Tenant/Shared/User/TenantSharedUserSeeder.php
├── Database/Factories/Tenant/Shared/User/TenantSharedUserFactory.php
├── Tests/Feature/Tenant/Shared/User/TenantSharedUserContract.php
├── Tests/Feature/Tenant/Shared/User/TenantSharedUserTestCase.php
├── Tests/Feature/Tenant/Shared/User/TenantSharedUserScaffoldTest.php
├── Tests/Feature/Tenant/Shared/User/TenantSharedUserSchemaTest.php
├── Tests/Feature/Tenant/Shared/User/TenantSharedUserPermissionsTest.php
├── Tests/Feature/Tenant/Shared/User/TenantSharedUserDeploymentTest.php
├── Tests/Feature/Tenant/Shared/User/TenantSharedUserHttpTest.php
├── Resources/js/Pages/Tenant/Shared/TenantSharedUserIndex.vue
├── Resources/js/Pages/Tenant/Shared/TenantSharedUserCreate.vue
├── Resources/js/Pages/Tenant/Shared/TenantSharedUserEdit.vue
├── Resources/js/Pages/Tenant/Shared/TenantSharedUserShow.vue
└── Jobs/Tenant/Shared/TenantSharedUserReportJob.php
```

---

### Contexto `tenant` (ej: INNODITE) — 20 archivos

```
Modules/User/
├── Http/Controllers/Tenant/INNODITE/User/TenantINNODITEUserController.php
├── Http/Requests/Tenant/INNODITE/User/TenantINNODITEUserStoreRequest.php
├── Http/Requests/Tenant/INNODITE/User/TenantINNODITEUserUpdateRequest.php
├── Services/Tenant/INNODITE/User/TenantINNODITEUserService.php
├── Services/Contracts/Tenant/INNODITE/User/TenantINNODITEUserServiceInterface.php
├── Repositories/Tenant/INNODITE/User/TenantINNODITEUserRepository.php
├── Repositories/Contracts/Tenant/INNODITE/User/TenantINNODITEUserRepositoryInterface.php
├── Models/Tenant/INNODITE/User/TenantINNODITEUser.php
├── Database/Migrations/Tenant/INNODITE/User/XXXX_create_users_table.php
├── Database/Seeders/Tenant/INNODITE/User/TenantINNODITEUserSeeder.php
├── Database/Factories/Tenant/INNODITE/User/TenantINNODITEUserFactory.php
├── Tests/Feature/Tenant/INNODITE/User/TenantINNODITEUserContract.php
├── Tests/Feature/Tenant/INNODITE/User/TenantINNODITEUserTestCase.php
├── Tests/Feature/Tenant/INNODITE/User/TenantINNODITEUserScaffoldTest.php
├── Tests/Feature/Tenant/INNODITE/User/TenantINNODITEUserSchemaTest.php
├── Tests/Feature/Tenant/INNODITE/User/TenantINNODITEUserPermissionsTest.php
├── Tests/Feature/Tenant/INNODITE/User/TenantINNODITEUserDeploymentTest.php
├── Tests/Feature/Tenant/INNODITE/User/TenantINNODITEUserHttpTest.php
├── Resources/js/Pages/Tenant/INNODITE/TenantINNODITEUserIndex.vue
├── Resources/js/Pages/Tenant/INNODITE/TenantINNODITEUserCreate.vue
├── Resources/js/Pages/Tenant/INNODITE/TenantINNODITEUserEdit.vue
├── Resources/js/Pages/Tenant/INNODITE/TenantINNODITEUserShow.vue
├── Jobs/Tenant/INNODITE/TenantINNODITEUserReportJob.php
├── Notifications/Tenant/INNODITE/TenantINNODITEUserCustomAlert.php
└── Console/Commands/Tenant/INNODITE/TenantINNODITEUserImportCommand.php
```

---

## 🔄 Flujo completo por contexto

Esta sección documenta el flujo de generación completo para cada contexto: qué archivos crea, dónde los ubica y cómo inyecta las rutas.

---

### Contexto `central`

```bash
php artisan innodite:make-module User --context=central
```

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

---

### Contexto `shared`

```bash
php artisan innodite:make-module Invoice --context=shared
```

#### Dualidad de rutas — inyección simultánea en DOS archivos

El contexto `shared` es único: sus rutas son accesibles tanto desde el panel central como desde el panel tenant. El generador inyecta rutas en **dos archivos simultáneamente**.

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

El mismo componente Vue resuelve diferente según el panel activo, gracias a `auth.context.route_prefix` inyectada por `InnoditeContextBridge`:

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

```bash
php artisan innodite:make-module Role --context=tenant_shared
```

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

### Contexto `tenant` (tenant específico — ej: INNODITE)

```bash
php artisan innodite:make-module Product --context=innodite
```

El paquete resuelve `innodite` buscando en el array `tenant` de `contexts.json` por `name`, `class_prefix` o slug derivado del nombre.

#### Ruta inyectada en `routes/tenant.php`

```php
// Bloque generado para: Product (Contexto: INNODITE)
Route::prefix('innodite')->name('innodite.')->middleware(['web','auth','tenant-auth'])->group(function () {
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/',          [TenantINNODITEProductController::class, 'index'])->name('index');
        Route::get('/create',    [TenantINNODITEProductController::class, 'create'])->name('create');
        Route::post('/',         [TenantINNODITEProductController::class, 'store'])->name('store');
        Route::get('/{id}',      [TenantINNODITEProductController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [TenantINNODITEProductController::class, 'edit'])->name('edit');
        Route::put('/{id}',      [TenantINNODITEProductController::class, 'update'])->name('update');
        Route::delete('/{id}',   [TenantINNODITEProductController::class, 'destroy'])->name('destroy');
    });
});
// {{TENANT_INNODITE_ROUTES_END}}
```

#### Resolución de `contextRoute()`

```js
contextRoute('products.index')
// Resuelve: 'innodite.products.index'
```

---

## 🧩 Composables Vue 3

Los composables se publican con `php artisan innodite:publish-frontend` en `resources/js/Composables/`.

### `useModuleContext` — Detección automática de contexto

Lee `auth.context.route_prefix` desde las props de Inertia compartidas por `InnoditeContextBridge` y antepone automáticamente el prefijo correcto a cualquier clave de ruta.

```js
import { useModuleContext } from '@/Composables/useModuleContext'

const { contextRoute, routePrefix, permissionPrefix } = useModuleContext()

route(contextRoute('users.index'))
// Central              → 'central.users.index'
// Shared (web)         → 'central.shared.users.index'
// Shared (tenant)      → 'tenant.shared.users.index'
// TenantShared         → 'users.index'  (sin prefijo)
// Tenant INNODITE      → 'innodite.users.index'
```

El mismo componente Vue funciona en cualquier contexto sin cambios — el composable resuelve la ruta correcta según la sesión activa.

---

### `usePermissions` — Verificación de permisos del usuario

Lee `auth.permissions` desde las props de Inertia y permite verificar permisos de forma declarativa en las plantillas Vue.

```js
import { usePermissions } from '@/Composables/usePermissions'

const { can, canAny, canAll } = usePermissions()

can('users.create')                          // true/false
canAny(['users.edit', 'users.create'])       // true si tiene al menos uno
canAll(['users.view', 'users.edit'])         // true si tiene todos
```

**Estrategia dual:** verifica `{prefix}.{perm}` y `{perm}` plano simultáneamente. El mismo componente funciona en cualquier contexto sin cambios.

```vue
<template>
  <!-- Botón visible solo si el usuario tiene permiso -->
  <button v-if="can('users.create')" @click="goToCreate()">
    Nuevo usuario
  </button>

  <!-- Acciones de fila protegidas por permisos -->
  <button v-if="can('users.edit')" @click="edit(item.id)">Editar</button>
  <button v-if="can('users.delete')" @click="destroy(item.id)">Eliminar</button>
</template>
```

---

### Flujo de datos en las vistas Vue generadas

```
Montaje  → axios.get(route(contextRoute('users.index')))    ← carga datos
Guardar  → axios.post/put(route(...))                       ← muta datos
Navegar  → router.visit(route(contextRoute('users.xxx')))   ← Inertia solo navega
Permisos → can('users.edit')                                ← oculta/muestra UI
```

### Ejemplo — `CentralUserIndex.vue`

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

### Ejemplo — `CentralUserCreate.vue`

```vue
async function submit() {
    await axios.post(route(contextRoute('users.store')), form.value)
    router.visit(route(contextRoute('users.index')))  // navega con Inertia
}
```

- Errores de validación Laravel 422 mostrados campo a campo
- Botón deshabilitado durante el envío (previene doble submit)

### Ejemplo — `CentralUserEdit.vue`

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

- Recibe solo `id` como prop de Inertia (nunca el objeto completo)
- Carga el registro vía axios al montarse

---

## 🔧 Stubs contextuales

El sistema de stubs de v3.1.0 organiza las plantillas en **4 carpetas independientes**, una por contexto. Esto permite personalizar la salida generada para cada contexto sin afectar los demás.

### Estructura de stubs

```
module-maker-config/
└── stubs/
    └── contextual/
        ├── Central/
        │   ├── controller.stub
        │   ├── service.stub
        │   ├── repository.stub
        │   ├── model.stub
        │   ├── request-store.stub
        │   ├── request-update.stub
        │   ├── vue-index.stub
        │   ├── vue-create.stub
        │   ├── vue-edit.stub
        │   └── vue-show.stub
        ├── Shared/
        │   ├── controller.stub
        │   ├── service.stub
        │   └── ...
        ├── TenantShared/
        │   ├── controller.stub
        │   ├── service.stub
        │   └── ...
        └── TenantName/
            ├── controller.stub
            ├── service.stub
            └── ...
```

### Publicar stubs para personalización

```bash
php artisan vendor:publish --tag=module-maker-stubs
```

Copia las 4 carpetas de stubs a `module-maker-config/stubs/contextual/` en tu proyecto. A partir de ese momento, el generador usará tus stubs en lugar de los del paquete.

### Variables disponibles en los stubs

| Variable | Descripción | Ejemplo |
|---|---|---|
| `{{MODULE}}` | Nombre del módulo | `User` |
| `{{CLASS_PREFIX}}` | Prefijo de clase del contexto | `Central` |
| `{{NAMESPACE}}` | Namespace completo de la clase | `Modules\User\Http\Controllers\Central` |
| `{{CLASS_NAME}}` | Nombre completo de la clase | `CentralUserController` |
| `{{MODEL_CLASS}}` | Clase del modelo | `CentralUser` |
| `{{SERVICE_INTERFACE}}` | Interface del servicio | `CentralUserServiceInterface` |
| `{{ROUTE_PREFIX}}` | Prefijo de ruta del contexto | `central` |
| `{{TABLE_NAME}}` | Nombre de la tabla | `central_users` |

---

## 🌉 Bridge Frontend-Backend

### Middleware `InnoditeContextBridge`

Intercepta cada request e inyecta vía `Inertia::share()`:

| Prop | Valor ejemplo |
|---|---|
| `auth.context.route_prefix` | `central`, `innodite`, `central.shared` |
| `auth.context.permission_prefix` | `central`, `innodite`, `tenant` |
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

## ⚙️ Estructura de contextos (`contexts.json`)

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
        "tenant": [
            {
                "name": "INNODITE",
                "class_prefix": "TenantINNODITE",
                "folder": "Tenant/INNODITE",
                "namespace_path": "Tenant\\INNODITE",
                "route_file": "tenant.php",
                "route_prefix": "innodite",
                "route_name": "innodite.",
                "permission_prefix": "innodite",
                "route_middleware": ["web", "auth", "tenant-auth"]
            },
            {
                "name": "ACME",
                "class_prefix": "TenantACME",
                "folder": "Tenant/ACME",
                "namespace_path": "Tenant\\ACME",
                "route_file": "tenant.php",
                "route_prefix": "acme",
                "route_name": "acme.",
                "permission_prefix": "acme",
                "route_middleware": ["web", "auth", "tenant-auth"]
            }
        ]
    }
}
```

> El array `tenant` puede contener **múltiples entradas**, una por cada tenant específico del proyecto. Cada entrada genera su propio espacio de nombres, carpetas y marcador de rutas aislado.

### Claves del contexto `tenant_shared` con `route_prefix: null`

Es el único contexto sin prefijo de URL ni de nombre de ruta. `contextRoute('roles.index')` devuelve simplemente `'roles.index'` — diseñado para código estándar que se ejecuta bajo el dominio de cada tenant.

---

## 🌳 Estructura de árbol de un módulo generado

El siguiente árbol corresponde a `innodite:make-module User --context=central` (módulo completo, 24 archivos).

**Patrón v3.5.x:** Los componentes principales siguen `{Tipo}/{Contexto}/{Entidad}/{Archivo}`.

```
Modules/
└── User/
    ├── Http/
    │   ├── Controllers/
    │   │   └── Central/
    │   │       └── User/
    │   │           └── CentralUserController.php      (RendersInertiaModule + JSON)
    │   └── Requests/
    │       └── Central/
    │           └── User/
    │               ├── CentralUserStoreRequest.php
    │               └── CentralUserUpdateRequest.php
    ├── Models/
    │   └── Central/
    │       └── User/
    │           └── CentralUser.php                    (con $table definida)
    ├── Services/
    │   ├── Central/
    │   │   └── User/
    │   │       └── CentralUserService.php
    │   └── Contracts/
    │       └── Central/
    │           └── User/
    │               └── CentralUserServiceInterface.php
    ├── Repositories/
    │   ├── Central/
    │   │   └── User/
    │   │       └── CentralUserRepository.php
    │   └── Contracts/
    │       └── Central/
    │           └── User/
    │               └── CentralUserRepositoryInterface.php
    ├── Providers/
    │   └── UserServiceProvider.php                (binding automático Interface↔Implementation)
    ├── Database/
    │   ├── Migrations/
    │   │   └── Central/
    │   │       └── User/
    │   │           └── *_create_users_table.php   (migración anónima)
    │   ├── Seeders/
    │   │   └── Central/
    │   │       └── User/
    │   │           └── CentralUserSeeder.php
    │   └── Factories/
    │       └── Central/
    │           └── User/
    │               └── CentralUserFactory.php
    ├── Tests/
    │   └── Feature/
    │       └── Central/
    │           └── User/
    │               ├── CentralUserContract.php
    │               ├── CentralUserTestCase.php
    │               ├── CentralUserScaffoldTest.php
    │               ├── CentralUserSchemaTest.php
    │               ├── CentralUserPermissionsTest.php
    │               ├── CentralUserDeploymentTest.php
    │               └── CentralUserHttpTest.php
    ├── Resources/
    │   └── js/
    │       └── Pages/
    │           └── Central/
    │               ├── CentralUserIndex.vue       (lista paginada, axios.get)
    │               ├── CentralUserCreate.vue      (formulario, axios.post)
    │               ├── CentralUserEdit.vue        (formulario, axios.get + axios.put)
    │               └── CentralUserShow.vue        (detalle, axios.get)
    ├── Jobs/
    │   └── Central/
    │       └── CentralUserExportJob.php
    ├── Notifications/
    │   └── Central/
    │       └── CentralUserWelcomeNotification.php
    ├── Console/
    │   └── Commands/
    │       └── Central/
    │           └── CentralUserCleanupCommand.php
    ├── Exceptions/
    │   └── Central/
    │       └── CentralUserNotFoundException.php
    └── Routes/
        └── web.php                                (rutas CRUD — referencia local)
```

Con `innodite:add-entity User Role --context=central`, se añade dentro de `Modules/User/` una subcarpeta `Role/` paralela a `User/` en cada tipo de componente.

---

## 📐 Convenciones de nomenclatura

| Contexto | Prefijo de clase | Ejemplo Vue | Ejemplo PHP |
|---|---|---|---|
| `central` | `Central` | `CentralUserIndex.vue` | `CentralUserController.php` |
| `shared` | `Shared` | `SharedInvoiceIndex.vue` | `SharedInvoiceService.php` |
| `tenant_shared` | `TenantShared` | `TenantSharedRoleIndex.vue` | `TenantSharedRoleRepository.php` |
| `tenant` (INNODITE) | `TenantINNODITE` | `TenantINNODITEUserIndex.vue` | `TenantINNODITEUserController.php` |

**Reglas adicionales:**
- El nombre del módulo siempre va en PascalCase (ej: `User`, `InvoiceItem`, `TaxReport`)
- Las migraciones son anónimas (`return new class extends Migration`) para evitar colisiones de nombres
- Los ServiceProviders llevan el nombre del módulo sin prefijo de contexto (`UserServiceProvider`, no `CentralUserServiceProvider`)
- Los Seeders, Jobs, Notifications y Commands **sí llevan prefijo de contexto** a partir de v3.1.0

---

## 🔀 Flujo de inyección de rutas

### Marcadores en `routes/web.php`

```php
// Al final del archivo, por contexto central y shared-web:
// {{CENTRAL_ROUTES_END}}
```

### Marcadores en `routes/tenant.php`

```php
// Por contexto tenant_shared y shared-tenant:
// {{TENANT_SHARED_ROUTES_END}}

// Por cada tenant específico (uno por tenant, basado en class_prefix):
// {{TENANT_INNODITE_ROUTES_END}}
// {{TENANT_ACME_ROUTES_END}}
```

### Proceso interno de inyección

```
1. resolveMarkerKey()   → contexto + route_file → clave del marcador
                          central + web.php         → CENTRAL_ROUTES_END
                          innodite + tenant.php      → TENANT_INNODITE_ROUTES_END

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

## 📋 Resumen de todos los comandos

| Comando | Descripción |
|---|---|
| `innodite:make-module {Name}` | Genera módulo completo con backend, vistas Vue y rutas |
| `innodite:add-entity {Module} {Entity}` | Agrega una entidad a un módulo existente |
| `innodite:module-setup` | Inicializa configuración del paquete en el proyecto |
| `innodite:module-check` | Diagnóstico de configuración, permisos y conflictos |
| `innodite:check-env` | Verifica integración frontend-backend (bridge Inertia) |
| `innodite:publish-frontend` | Publica composables Vue 3 (`useModuleContext`, `usePermissions`) |
| `innodite:migrate-plan --context=` | Aplica las migraciones del contexto, en el orden de sus traits `MigrationsList` |
| `innodite:migrate-one` | Ejecuta una migración puntual por coordenada |
| `innodite:deploy {stage\|production}` | Levanta el proyecto: esquema, datos, permisos y webmaster |
| `innodite:test` | Ejecuta el contrato de una subfuncionalidad, en cascada y con corte temprano |
| `vendor:publish --tag=module-maker-config` | Publica `make-module.php` |
| `vendor:publish --tag=module-maker-stubs` | Publica stubs contextuales personalizables |
| `vendor:publish --tag=module-maker-contexts` | Publica `contexts.json` de ejemplo |
| `vendor:publish --tag=module-maker-frontend` | Publica composables Vue 3 |

---

## 📊 Auditoría

`storage/logs/module_maker.log` — formato NDJSON (una entrada JSON por línea):

```json
{"timestamp":"2026-04-01T12:00:00+00:00","event":"module.created","package":"innodite/laravel-module-maker","version":"3.1.0","module":"User","context_key":"central","context_name":"App Central","routes":true}
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

## 🧪 Pruebas

```bash
composer test           # todos los tests
composer test:unit      # solo unitarios
composer test:feature   # solo integración
composer test:coverage  # con cobertura HTML en /coverage
```

Los tests generados se ubican todos en `Modules/{Name}/Tests/Feature/{Context}/{SubFuncionalidad}/`,
como un **grupo por subfuncionalidad**: un manifiesto (`{SubFunc}Contract.php`), su base
(`{SubFunc}TestCase.php`) y las piezas de los nueve temas —`ScaffoldTest`, `SchemaTest`,
`PermissionsTest`, `DeploymentTest` y `HttpTest`—. La del tema 6 la ejecuta Vitest y vive con el
JavaScript, en `resources/js/__tests__/{Context}/{SubFuncionalidad}/`.

Los emiten por igual `make-module` y `add-entity`: una subfuncionalidad nace con su grupo entero
venga por donde venga.

---

## 📏 Estándares de código

```bash
composer lint         # verificar PSR-12
composer lint:fix     # corregir automáticamente
composer lint:strict  # verificar declaraciones strict_types
```

El paquete incluye configuración de PHP CS Fixer compatible con PSR-12. Todos los archivos PHP generados incluyen `declare(strict_types=1)` por defecto.

---

## 📦 Publicar en Packagist / repositorio privado

### Repositorio público (Packagist)

```bash
git init && git add . && git commit -m "feat: release v3.1.0"
git tag v3.1.0 && git push origin main --tags
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
composer require innodite/laravel-module-maker:^3.5
```

---

## 📝 Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para el historial completo de versiones.

---

## 📄 Licencia

MIT — [Anthony Filgueira](https://www.innodite.com)
