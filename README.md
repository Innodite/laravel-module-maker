# 🏗️ Innodite Laravel Module Maker

**v3.1.0** — Generador de módulos Laravel con arquitectura de contextos dinámicos (Central, Shared, Tenant) para proyectos multi-tenant. Genera backend completo, inyecta rutas y crea vistas Vue 3 listas para usar — todo con un solo comando.

---

## 📋 Tabla de Contenidos

- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Tabla comparativa de contextos](#-tabla-comparativa-de-contextos)
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

### `innodite:migrate-plan` — Orquestador de Migraciones por Manifiesto

Ejecuta migraciones en el orden exacto definido en un manifiesto JSON. Es ideal cuando hay dependencias entre módulos y contextos.
Antes de ejecutar, valida la conexión objetivo y verifica que la base de datos exista para evitar procesos parciales o lanzados contra una BD incorrecta.

```bash
# Usar manifiesto por defecto (module-maker-config/migrations/central_order.json)
php artisan innodite:migrate-plan

# Usar manifiesto específico
php artisan innodite:migrate-plan --manifest=tenant_innodite_order.json

# Simular sin tocar BD
php artisan innodite:migrate-plan --manifest=tenant_innodite_order.json --dry-run

# Ejecutar también seeders después de migraciones
php artisan innodite:migrate-plan --manifest=tenant_innodite_order.json --seed
```

**Formato de coordenadas soportado:**

- Migraciones: `Modulo:Contexto/Archivo.php`
- Seeders: `Modulo:Contexto/ClaseSeeder`

**Ejemplo real de manifiesto (`module-maker-config/migrations/tenant_innodite_order.json`):**

```json
{
    "migrations": [
        "User:Shared/2026_01_01_000001_create_users_table.php",
        "Roles:Tenant/Shared/2026_02_01_000001_create_tenant_roles_table.php",
        "Custom:Tenant/INNODITE/2026_03_01_000001_innodite_extra_table.php"
    ],
    "seeders": [
        "User:Shared/SharedUserSeeder",
        "Roles:Tenant/Shared/TenantSharedRoleSeeder",
        "Custom:Tenant/INNODITE/TenantINNODITECustomSeeder"
    ]
}
```

**Cómo resuelve rutas internas:**

- `User:Shared/2026_...php` → `Modules/User/Database/Migrations/Shared/2026_...php`
- `Roles:Tenant/Shared/TenantSharedRoleSeeder` → `Modules\Roles\Database\Seeders\Tenant\Shared\TenantSharedRoleSeeder`

**Qué valida el comando:**

- Que el manifiesto exista y sea JSON válido
- Que `migrations` y `seeders` sean arrays
- Que cada coordenada de migración apunte a un archivo real
- Que el formato de coordenada sea correcto

**Mensajes de error claros:**

Si una coordenada no existe, el comando responde con la ruta esperada para corregirla rápidamente.
Si la base de datos objetivo no existe, corta el proceso antes de ejecutar migraciones o seeders.

---

### `innodite:migrate-one` — Ejecutar una Migración Específica

Permite ejecutar una coordenada de migración puntual sin correr el manifiesto completo. Está pensado para casos quirúrgicos donde necesitas lanzar una sola migración y mantener sincronizado el manifiesto correspondiente.

```bash
# Ejecutar una migración específica
php artisan innodite:migrate-one "Products:Tenant/EnergySpain/2026_01_01_000001_create_products_table.php"

# Forzar un manifiesto concreto
php artisan innodite:migrate-one "Forms:Shared/2026_01_01_000001_create_forms_table.php" --manifest=central_order.json

# Simular sin escribir ni ejecutar
php artisan innodite:migrate-one "Forms:Shared/2026_01_01_000001_create_forms_table.php" --dry-run

# Omitir confirmaciones interactivas
php artisan innodite:migrate-one "Products:Tenant/EnergySpain/2026_01_01_000001_create_products_table.php" --yes
```

**Qué hace internamente:**

1. Resuelve la ruta exacta del archivo de migración desde la coordenada.
2. Detecta automáticamente el manifiesto objetivo según el contexto.
3. Si la coordenada aplica a múltiples manifiestos, muestra los destinos y pide confirmación.
4. Muestra antes de ejecutar:
    - Tipo: migración
    - Coordenada
    - Conexión
    - Base de datos
    - Manifiesto destino
    - Ruta real del archivo
5. Si la coordenada no está registrada en el manifiesto, la agrega primero.
6. Ejecuta solo la migración indicada.

**Reglas de resolución:**

- `Central` => `central_order.json`
- `Shared` => puede aplicar a `central_order.json` y a los manifiestos tenant
- `Tenant/Shared` => aplica a todos los manifiestos tenant
- `Tenant/X` => aplica al manifiesto `tenant_x_order.json` correspondiente

**Importante:**

- Requiere confirmación interactiva antes de ejecutar, salvo que uses `--yes`.
- En `--dry-run` no modifica el manifiesto ni ejecuta nada.
- Si la base de datos objetivo no existe, falla antes de iniciar el proceso.

---

### `innodite:seed-one` — Ejecutar un Seeder Específico

Permite ejecutar un seeder puntual sin correr el manifiesto completo. Está pensado para casos quirúrgicos donde necesitas lanzar un solo seeder y mantener sincronizado el manifiesto correspondiente.

```bash
# Ejecutar un seeder específico
php artisan innodite:seed-one "UserManagement:Tenant/Shared/TenantSharedPermissionSeeder"

# Forzar un manifiesto concreto
php artisan innodite:seed-one "Forms:Shared/SharedFormsSeeder" --manifest=central_order.json

# Simular sin escribir ni ejecutar
php artisan innodite:seed-one "Forms:Shared/SharedFormsSeeder" --dry-run

# Omitir confirmaciones interactivas
php artisan innodite:seed-one "UserManagement:Tenant/Shared/TenantSharedPermissionSeeder" --yes
```

**Qué hace internamente:**

1. Resuelve el FQCN (clase completa) del seeder desde la coordenada.
2. Detecta automáticamente el manifiesto objetivo según el contexto.
3. Si la coordenada aplica a múltiples manifiestos, muestra los destinos y pide confirmación.
4. Muestra antes de ejecutar:
    - Tipo: seeder
    - Coordenada
    - Conexión
    - Base de datos
    - Manifiesto destino
    - Clase real que va a ejecutar
5. Si la coordenada no está registrada en el manifiesto, la agrega primero.
6. Ejecuta solo el seeder indicado.

**Reglas de resolución:**

- `Central` => `central_order.json`
- `Shared` => puede aplicar a `central_order.json` y a los manifiestos tenant
- `Tenant/Shared` => aplica a todos los manifiestos tenant
- `Tenant/X` => aplica al manifiesto `tenant_x_order.json` correspondiente

**Importante:**

- Requiere confirmación interactiva antes de ejecutar, salvo que uses `--yes`.
- En `--dry-run` no modifica el manifiesto ni ejecuta nada.
- Si la base de datos objetivo no existe, falla antes de iniciar el proceso.

---

### `innodite:migration-sync` — Sincronización Automática de Manifiestos

Escanea los módulos y agrega al manifiesto las migraciones y seeders que aún no están registradas.

```bash
# Sincronizar automaticamente por contextos (central + tenants detectados)
php artisan innodite:migration-sync

# Sincronizar un manifiesto concreto
php artisan innodite:migration-sync --manifest=tenant_innodite_order.json

# Sincronizacion automatica sin prompt de confirmacion
php artisan innodite:migration-sync --yes

# Ver faltantes sin escribir cambios
php artisan innodite:migration-sync --manifest=tenant_innodite_order.json --dry-run
```

**Comportamiento de sync:**

1. Si no envías `--manifest`, lee `module-maker-config/contexts.json` y propone:
    - `central_order.json`
    - `tenant_{permission_prefix}_order.json` por cada tenant configurado.
2. Pide confirmación en consola antes de generar/sincronizar múltiples manifiestos (omite prompt con `--yes`).
3. Crea `module-maker-config/migrations/` si no existe.
4. Crea cada manifiesto faltante (estructura vacía).
5. Escanea:
     - `Modules/*/Database/Migrations/**`
     - `Modules/*/Database/Seeders/**`
6. Convierte hallazgos a coordenadas.
7. Filtra por alcance de manifiesto:
    - `central_order.json` => contextos `Central` y `Shared`.
    - `tenant_*.json` => `Shared` + `Tenant/Shared` + contexto `Tenant/{X}` del tenant objetivo.
8. Hace append solo de faltantes (sin duplicar).

**Importante:**

- Solo sincroniza archivos en subcarpetas de contexto (`Shared`, `Central`, `Tenant/...`).
- Esto mantiene consistencia con el modelo contextual del paquete.

**Cuándo usarlo en la práctica:**

- Después de generar nuevos módulos/entidades y querer actualizar manifiestos automáticamente.
- Antes de un deploy, para verificar que no quedaron migraciones fuera del plan.
- En CI/CD para detectar drift entre código y manifiesto.

---

### `innodite:test-module` — Ejecutar Tests con Coverage

```bash
# 1) Sincronizar configuración por contexto (crea Tests/test-config.json)
php artisan innodite:test-sync User

# 2) Ejecutar tests de un módulo (modo default sin contexto)
php artisan innodite:test-module User

# 3) Ejecutar un contexto específico definido en test-config.json
php artisan innodite:test-module User --context=central

# 4) Ejecutar todos los contextos habilitados del módulo
php artisan innodite:test-module User --all-contexts

# 5) Coverage por módulo/contexto
php artisan innodite:test-module User --context=central --coverage --format=html --format=clover
```

**Características:**

- ✅ Ejecuta PHPUnit en uno o todos los módulos
- ✅ Usa configuración contextual en `Modules/{Modulo}/Tests/test-config.json`
- ✅ Permite correr un contexto (`--context`) o todos los contextos habilitados (`--all-contexts`)
- ✅ Escanea recursivamente toda la carpeta `Tests/` sin asumir estructura fija
- ✅ Crea/usa archivo de configuración PHPUnit editable en `Modules/{Modulo}/Tests/phpunit-{contexto}.xml`
- ✅ Genera variables de entorno de DB con `force="true"` para evitar herencia accidental de valores del proceso
- ✅ Cuando `db_database` está definido, también exporta `DB_MYSQL_DATABASE` para compatibilidad con configuraciones MySQL personalizadas
- ✅ Puede ejecutar un `seeder` previo por contexto antes de PHPUnit
- ✅ Genera reportes de coverage en múltiples formatos:
    - **HTML** → `docs/test-reports/{Module}/{contexto}/html/index.html` (navegable)
  - **Text** → Salida en consola con porcentajes
    - **Clover XML** → `docs/test-reports/{Module}/{contexto}/clover.xml` (CI/CD)
- ✅ Valida que Xdebug o PCOV estén activos para coverage
- ✅ Muestra tabla resumen con resultados y porcentaje de cobertura
- ✅ Soporta flag `--filter` de PHPUnit para tests específicos
- ✅ Detección automática de módulos sin tests (warning + continuar)

### `innodite:test-sync` — Sincronizar `Tests/test-config.json`

Genera o actualiza el archivo `test-config.json` dentro de la carpeta `Tests/` de cada módulo, leyendo los contextos desde `module-maker-config/contexts.json`.

Para testing, el sync genera y sincroniza los contextos definidos en `contexts.json`.

- Incluye contextos de negocio (`central`, `shared`, `tenant_shared`, tenants específicos, etc.)
- Respeta el control por contexto mediante `enabled` en `test-config.json`

Esto mantiene el paquete flexible: el usuario decide qué contextos ejecutar y cómo mapear `db_connection`/`db_database`.

```bash
# Sincronizar un módulo
php artisan innodite:test-sync User

# Sincronizar todos los módulos
php artisan innodite:test-sync --all
```

**Reglas del sync:**

- ✅ Crea `Modules/{Modulo}/Tests/test-config.json` si no existe
- ✅ Agrega contextos faltantes sin duplicar
- ✅ Conserva overrides manuales de `db_connection`, `db_database`, `seeder`, `enabled` y `env`
- ✅ No asume ninguna base de datos por defecto: tú defines `db_connection` y `db_database`

Ejemplo de `Modules/User/Tests/test-config.json`:

```json
{
    "_readme": "Configuración de tests por contexto. Generado por innodite:test-sync.",
    "contexts": {
        "central": {
            "db_connection": "mysql",
            "db_database": "neocenter_test",
            "enabled": true,
            "seeder": null,
            "env": {}
        },
        "telephony_spain": {
            "db_connection": "tenant",
            "db_database": "telephony_spain_test",
            "enabled": true,
            "seeder": "Modules\\UserManagement\\Database\\Seeders\\TelefoniaEspana\\TenantTelefoniaEspanaSeeder",
            "env": {
                "CACHE_DRIVER": "array"
            }
        }
    }
}
```

**Requisitos para Coverage:**

```bash
# Opción 1: Xdebug (desarrollo)
pecl install xdebug
# Añadir a php.ini: zend_extension=xdebug.so

# Opción 2: PCOV (más rápido, CI/CD)
pecl install pcov
# Añadir a php.ini: extension=pcov.so
```

**Ejemplo de Salida:**

```
🧪 Innodite Module Maker - Test Runner

✅ PHPUnit encontrado
✅ Xdebug activo - Coverage disponible

📦 Módulos a testear: User, Product, Invoice

🔍 Ejecutando tests del módulo: User
  📄 Archivos de test encontrados: 12
  ✓ Tests passed (15 tests, 45 assertions)
  
═══════════════════════════════════════════════════════
📊 RESUMEN DE EJECUCIÓN
═══════════════════════════════════════════════════════

┌─────────┬─────────┬──────────┐
│ Módulo  │ Estado  │ Coverage │
├─────────┼─────────┼──────────┤
│ User    │ ✓ PASSED│ 87.5%    │
│ Product │ ✓ PASSED│ 92.3%    │
│ Invoice │ ✗ FAILED│ 65.2%    │
└─────────┴─────────┴──────────┘

Total: 3 | Passed: 2 | Failed: 1 | Skipped: 0

📁 Reportes de coverage guardados en:
   docs/test-reports/
   • User: docs/test-reports/User/html/index.html
   • Product: docs/test-reports/Product/html/index.html
```

---

## 📁 Archivos generados por contexto

Esta sección muestra la lista exacta de archivos que el paquete genera para el módulo `User` en cada uno de los 4 contextos.

---

### Contexto `central` — 24 archivos

```
Modules/User/
├── Http/Controllers/Central/CentralUserController.php
├── Http/Requests/Central/CentralUserStoreRequest.php
├── Http/Requests/Central/CentralUserUpdateRequest.php
├── Services/Central/CentralUserService.php
├── Services/Contracts/Central/CentralUserServiceInterface.php
├── Repositories/Central/CentralUserRepository.php
├── Repositories/Contracts/Central/CentralUserRepositoryInterface.php
├── Models/Central/CentralUser.php
├── Database/Migrations/Central/XXXX_create_central_users_table.php
├── Database/Seeders/Central/CentralUserSeeder.php
├── Database/Factories/Central/CentralUserFactory.php
├── Tests/Feature/Central/CentralUserTest.php
├── Tests/Unit/Central/CentralUserServiceTest.php
├── Tests/Support/Central/CentralUserSupport.php
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

---

### Contexto `shared` — 16 archivos

```
Modules/User/
├── Http/Controllers/Shared/SharedUserController.php
├── Http/Requests/Shared/SharedUserRequest.php
├── Services/Shared/SharedUserService.php
├── Services/Contracts/Shared/SharedUserServiceInterface.php
├── Repositories/Shared/SharedUserRepository.php
├── Repositories/Contracts/Shared/SharedUserRepositoryInterface.php
├── Models/Shared/SharedUser.php
├── Database/Migrations/Shared/XXXX_create_shared_users_table.php
├── Database/Seeders/Shared/SharedUserSeeder.php
├── Database/Factories/Shared/SharedUserFactory.php
├── Tests/Feature/Shared/SharedUserTest.php
├── Tests/Unit/Shared/SharedUserServiceTest.php
├── Resources/js/Pages/Shared/SharedUserIndex.vue
├── Resources/js/Pages/Shared/SharedUserCreate.vue
├── Resources/js/Pages/Shared/SharedUserEdit.vue
└── Resources/js/Pages/Shared/SharedUserShow.vue
```

---

### Contexto `tenant_shared` — 17 archivos

```
Modules/User/
├── Http/Controllers/Tenant/Shared/TenantSharedUserController.php
├── Http/Requests/Tenant/Shared/TenantSharedUserRequest.php
├── Services/Tenant/Shared/TenantSharedUserService.php
├── Services/Contracts/Tenant/Shared/TenantSharedUserServiceInterface.php
├── Repositories/Tenant/Shared/TenantSharedUserRepository.php
├── Repositories/Contracts/Tenant/Shared/TenantSharedUserRepositoryInterface.php
├── Models/Tenant/Shared/TenantSharedUser.php
├── Database/Migrations/Tenant/Shared/XXXX_create_tenant_users_table.php
├── Database/Seeders/Tenant/Shared/TenantSharedUserSeeder.php
├── Database/Factories/Tenant/Shared/TenantSharedUserFactory.php
├── Tests/Feature/Tenant/Shared/TenantSharedUserTest.php
├── Tests/Unit/Tenant/Shared/TenantSharedUserServiceTest.php
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
├── Http/Controllers/Tenant/INNODITE/TenantINNODITEUserController.php
├── Http/Requests/Tenant/INNODITE/TenantINNODITEUserStoreRequest.php
├── Http/Requests/Tenant/INNODITE/TenantINNODITEUserUpdateRequest.php
├── Services/Tenant/INNODITE/TenantINNODITEUserService.php
├── Services/Contracts/Tenant/INNODITE/TenantINNODITEUserServiceInterface.php
├── Repositories/Tenant/INNODITE/TenantINNODITEUserRepository.php
├── Repositories/Contracts/Tenant/INNODITE/TenantINNODITEUserRepositoryInterface.php
├── Models/Tenant/INNODITE/TenantINNODITEUser.php
├── Database/Migrations/Tenant/INNODITE/XXXX_add_fields_to_innodite_table.php
├── Database/Seeders/Tenant/INNODITE/TenantINNODITEUserSeeder.php
├── Database/Factories/Tenant/INNODITE/TenantINNODITEUserFactory.php
├── Tests/Feature/Tenant/INNODITE/TenantINNODITEUserTest.php
├── Tests/Unit/Tenant/INNODITE/TenantINNODITEUserServiceTest.php
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

El siguiente árbol corresponde a `innodite:make-module User --context=central` (módulo completo, 24 archivos):

```
Modules/
└── User/
    ├── Http/
    │   ├── Controllers/
    │   │   └── Central/
    │   │       └── CentralUserController.php      (RendersInertiaModule + JSON)
    │   └── Requests/
    │       └── Central/
    │           ├── CentralUserStoreRequest.php
    │           └── CentralUserUpdateRequest.php
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
    │   │   └── Central/
    │   │       └── CentralUserSeeder.php
    │   └── Factories/
    │       └── Central/
    │           └── CentralUserFactory.php
    ├── Tests/
    │   ├── Feature/
    │   │   └── Central/
    │   │       └── CentralUserTest.php
    │   ├── Unit/
    │   │   └── Central/
    │   │       └── CentralUserServiceTest.php
    │   └── Support/
    │       └── Central/
    │           └── CentralUserSupport.php
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
| `innodite:module-setup` | Inicializa configuración del paquete en el proyecto |
| `innodite:module-check` | Diagnóstico de configuración, permisos y conflictos |
| `innodite:check-env` | Verifica integración frontend-backend (bridge Inertia) |
| `innodite:publish-frontend` | Publica composables Vue 3 (`useModuleContext`, `usePermissions`) |
| `innodite:migrate-plan` | Ejecuta migraciones/seeders por manifiesto y orden explícito |
| `innodite:migration-sync` | Escanea módulos y sincroniza faltantes en manifiestos |
| `innodite:test-module` | Ejecuta tests de módulos con contexto y coverage (HTML, Text, Clover) |
| `innodite:test-sync` | Sincroniza `Modules/{Modulo}/Tests/test-config.json` desde `contexts.json` |
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

Los tests generados por `make-module` se ubican en:
- `Modules/{Name}/Tests/Feature/{Context}/` — tests de integración HTTP
- `Modules/{Name}/Tests/Unit/{Context}/` — tests unitarios del servicio
- `Modules/{Name}/Tests/Support/{Context}/` — helpers y factories de test

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
composer require innodite/laravel-module-maker:^3.1
```

---

## 📝 Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para el historial completo de versiones.

---

## 📄 Licencia

MIT — [Anthony Filgueira](https://www.innodite.com)
