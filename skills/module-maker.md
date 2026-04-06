# ADN Técnico — innodite/laravel-module-maker

> **Documento vivo — Fuente de verdad técnica.**
> NO re-leas archivos fuente si la info está aquí. Actualiza este doc tras cada cambio.
> Última actualización: 2026-04-06 (reescritura completa)

---

## @ESTRUCTURA — Árbol de Archivos Críticos

```
src/
  Commands/
    MakeModuleCommand.php           — innodite:make-module
    SetupModuleMakerCommand.php     — innodite:module-setup
    MigratePlanCommand.php          — innodite:migrate-plan
    MigrateOneCommand.php           — innodite:migrate-one
    SeedOneCommand.php              — innodite:seed-one
    MigrationSyncCommand.php        — innodite:migration-sync
    CheckEnvCommand.php             — innodite:check-env
    ModuleCheckCommand.php          — innodite:module-check
    TestModuleCommand.php           — innodite:test-module
    TestSyncCommand.php             — innodite:test-sync
    MigrateModulesCommand.php       — innodite:migrate-modules (legacy v2→v3)
    PublishFrontendCommand.php      — innodite:publish-frontend

  Contracts/
    InnoditeUserPermissions.php     — interface: getInnoditePermissions(): array

  Exceptions/
    ContextNotFoundException.php          — extends InvalidArgumentException
    ConnectionNotConfiguredException.php  — extends \RuntimeException

  Generators/Components/
    AbstractComponentGenerator.php  — base: getContext(), buildNamespace(), buildPath()
    ModuleGenerator.php             — orquestador principal de generación
    ModelGenerator.php
    ControllerGenerator.php
    ServiceGenerator.php
    RepositoryGenerator.php
    MigrationGenerator.php
    SeederGenerator.php
    RequestGenerator.php
    FactoryGenerator.php            — strategies: Boolean/Date/Enum/ForeignId/Foreign/Integer/Text
    JobGenerator.php
    NotificationGenerator.php
    ExceptionGenerator.php
    ConsoleCommandGenerator.php
    ProviderGenerator.php
    RouteGenerator.php
    VueGenerator.php                — genera 4 vistas CRUD (index/create/edit/show)
    TestGenerator.php
    SupportTestGenerator.php

  Middleware/
    InnoditeContextBridge.php       — sincroniza contexto+permisos a Inertia

  Services/
    MigrationPlanResolver.php       — resolveManifestPath(), loadPlan(), resolveMigrationCoordinate(), resolveSeederCoordinate()
    MigrationTargetService.php      — resolveExecutionConnection(), resolveTargetsForCoordinate(), ensureManifestPath()
    RouteInjectionService.php       — inject() idempotente con marcadores
    ModuleAuditor.php               — NDJSON log en storage/logs/module_maker.log
    TestContextConfigService.php    — sync config tests por contexto

  Support/
    ContextResolver.php             — find(), all(), allItems(), allTenants(), validateConnection(), flush()

  Traits/
    RendersInertiaModule.php        — renderModule(), resolveView(), resolveBaseFolder()

  LaravelModuleMakerServiceProvider.php  — register + boot (rutas, migraciones, discovery)

config/
  make-module.php                   — module_path, config_path, contexts_path, stubs.path

stubs/contextual/
  Central/ Shared/ TenantName/ TenantShared/
    controller.stub, model.stub, service.stub, service-interface.stub,
    repository.stub, repository-interface.stub, migration.stub, seeder.stub,
    request.stub, request-store.stub, request-update.stub, factory.stub,
    job.stub, notification.stub, exception.stub, console-command.stub,
    provider.stub, route-web.stub, route-tenant.stub, route-api.stub,
    vue-index.stub, vue-create.stub, vue-edit.stub, vue-show.stub,
    test.stub, test-unit.stub, test-support.stub
  (mismos archivos en raíz como fallback genérico)

stubs/
  contexts.json                     — template híbrido (ver @CONTEXTOS)

tests/
  TestCase.php                      — base: tempDir + contexts.json + ContextResolver::flush()
  Feature/
    ConnectionValidationCommandTest.php
    MakeModuleCommandTest.php
    MigratePlanCommandTest.php
    MigrationSyncCommandTest.php
    MigrateOneCommandTest.php
    SeedOneCommandTest.php
```

---

## @CONTEXTOS — Estructura contexts.json

**REGLA CRÍTICA: Estructura HÍBRIDA OBLIGATORIA. Nunca normalizar todo a array.**

- **central, shared, tenant_shared** → objetos únicos (acceso O(1) por clave)
- **tenant** → array de objetos (múltiples instancias para multitenancy)

### Template completo (stubs/contexts.json)

```json
{
    "_readme": "Plantilla de contextos v3.5.0. Estructura híbrida: central/shared/tenant_shared son objetos, tenant es array.",
    "contexts": {
        "central": {
            "id": "central",
            "tenancy_strategy": "manual",
            "connection_key": "central",
            "class_prefix": "Central",
            "folder": "Central",
            "namespace_path": "Central",
            "route_file": "web.php",
            "route_prefix": "central",
            "route_name": "central.",
            "permission_prefix": "",
            "permission_middleware": "",
            "route_middleware": []
        },
        "shared": {
            "id": "shared",
            "class_prefix": "Shared",
            "folder": "Shared",
            "namespace_path": "Shared",
            "route_prefix": "shared",
            "route_name": "shared."
        },
        "tenant_shared": {
            "id": "tenant-shared",
            "class_prefix": "TenantShared",
            "folder": "Tenant/Shared",
            "namespace_path": "Tenant\\Shared",
            "route_file": "tenant.php",
            "route_prefix": "",
            "route_name": ""
        },
        "tenant": [
            {
                "id": "tenant-one",
                "tenancy_strategy": "manual",
                "connection_key": "tenant_one",
                "class_prefix": "TenantOne",
                "folder": "Tenant/TenantOne",
                "namespace_path": "Tenant\\TenantOne",
                "route_file": "tenant.php",
                "route_prefix": "tenant-one",
                "route_name": "tenant-one.",
                "permission_prefix": "tenant_one",
                "permission_middleware": "",
                "route_middleware": []
            }
        ]
    }
}
```

### Campos por contexto

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | string (kebab-case) | Identificador único. Usado en manifest names, CLI args, búsquedas |
| `tenancy_strategy` | `"manual"` \| ausente | Solo "manual" activa la resolución de conexión explícita |
| `connection_key` | string | Nombre de la conexión en config/database.php. Ausente en shared y tenant_shared |
| `class_prefix` | PascalCase | Prefijo de clases PHP (Central, TenantOne) |
| `folder` | string | Subcarpeta dentro de cada tipo de componente |
| `namespace_path` | string | Fragmento de namespace PHP (usa `\\` como separador) |
| `route_file` | string \| array | Archivo(s) de rutas: web.php, tenant.php, api.php |
| `route_prefix` | string \| null | Prefijo URL |
| `route_name` | string \| null | Prefijo de nombre de ruta (incluye punto final) |
| `permission_prefix` | string | Prefijo para permisos Spatie |
| `permission_middleware` | string | Middleware de permisos adicional |
| `route_middleware` | array | Middlewares extra para el grupo de rutas |

### Archivo en proyecto: `module-maker-config/contexts.json`

El paquete siempre busca primero `base_path('module-maker-config/contexts.json')`.
Si no existe, cae al stub interno del paquete.

---

## @MANIFESTS — Sistema de Manifests

**Ubicación:** `module-maker-config/migrations/`

### Naming convention

| Contexto | Manifest |
|----------|----------|
| central + shared + tenant_shared | `central.order.json` |
| tenant con id `clinic-one` | `clinic-one.order.json` |
| cualquier tenant con id `energy-spain` | `energy-spain.order.json` |

**Patrón regex válido:** `/^([a-z0-9][a-z0-9-]*)\.order\.json$/`

Si el basename no coincide → `resolveExecutionConnection()` lanza error, NO hace fallback.

### Estructura JSON del manifest

```json
{
    "migrations": [
        "User:Central/2024_01_01_create_users_table.php",
        "Order:Tenant/TenantOne/2024_01_02_create_orders_table.php"
    ],
    "seeders": [
        "User:Central/UserDatabaseSeeder",
        "Order:Tenant/TenantOne/OrderDatabaseSeeder"
    ]
}
```

### Formato de coordenadas

- **Migración:** `ModuleName:ContextPath/filename.php`
- **Seeder:** `ModuleName:ContextPath/ClassName`
- **ContextPath:** `Central`, `Shared`, `Tenant/Shared`, `Tenant/TenantOne`, etc.

---

## @CONEXION — Resolución de Conexión (Guard Rail)

**Implementado en: `MigrationTargetService::resolveExecutionConnection()`**
**También disponible en: `ContextResolver::validateConnection()`**

### Firma

```php
// MigrationTargetService
public function resolveExecutionConnection(string $manifestPath, bool $dryRun = false): string

// ContextResolver
public function validateConnection(string $id): void  // lanza ConnectionNotConfiguredException
```

### Cadena de validación (orden exacto)

```
1. basename($manifestPath) → regex /^([a-z0-9][a-z0-9-]*)\.order\.json$/
   [FAIL] → \InvalidArgumentException

2. ContextResolver::find($contextId)
   [FAIL] → \InvalidArgumentException (ContextNotFoundException)

3. $context['tenancy_strategy'] === 'manual'
   [FAIL] → \InvalidArgumentException

4. !empty($context['connection_key'])
   [FAIL] → \InvalidArgumentException

5. if (!$dryRun): is_array(config("database.connections.{$connectionKey}"))
   [FAIL] → ConnectionNotConfiguredException
```

**CRÍTICO:** Usa `!is_array(config(...))` NO `=== null`. El check de config/database.php se **salta en dry-run**.

### Retorno

- `string` → nombre de la conexión (ej: `'central'`, `'tenant_one'`)
- Lanza excepción en cualquier fallo — **nunca retorna fallback**

---

## @API_CONTEXTRESOLVER — ContextResolver API

```
Namespace: Innodite\LaravelModuleMaker\Support
Cache estático: private static ?array $data = null
Flush OBLIGATORIO en tearDown de tests: ContextResolver::flush()
```

| Método | Signatura | Comportamiento |
|--------|-----------|----------------|
| `find` | `(string $id): array` | Búsqueda híbrida en toda la estructura. Prioridad: objeto único → array tenant. Lanza `ContextNotFoundException` si no existe |
| `resolve` | `(string $contextKey): array` | Solo contextos de objeto único (central, shared, tenant_shared). Lanza si no existe o si es array |
| `resolveById` | `(string $contextKey, string $id): array` | Acceso por clave de contexto + id. Para tenant: Collection->firstWhere('id', $id) |
| `resolveTenant` | `(string $id): array` | Alias de `resolveById('tenant', $id)` |
| `all` | `(): array` | Todos los contextos sin procesar tal como están en el JSON. Tipo: `array<string, array\|array<int, array>>` |
| `allItems` | `(): array` | Array PLANO de todos los contextos (central + shared + tenant_shared + cada tenant). Tipo: `array<int, array>`. **Usar este para iterar todos los contextos** |
| `allTenants` | `(): array` | Solo el array de tenants. Tipo: `array<int, array>` |
| `getSpecificTenants` | `(): array` | Alias de `allTenants()` |
| `validateConnection` | `(string $id): void` | Lanza `ConnectionNotConfiguredException` si `connection_key` no está en config/database.php. Solo actúa para contextos con `tenancy_strategy='manual'` |
| `validateConnections` | `(): array<string,string>` | Valida todas las conexiones. Retorna `[context_id => error_message]` para los que fallan |
| `getRouteFile` | `(string $contextKey): string\|array` | Retorna `route_file` del contexto. Fallback: `'web.php'` |
| `flush` | `(): void` | Limpia el cache estático. Usar en tearDown |

### Diferencia crítica: `all()` vs `allItems()`

```php
// all() — retorna estructura híbrida cruda:
[
  'central' => ['id' => 'central', ...],        // objeto
  'shared' => ['id' => 'shared', ...],           // objeto
  'tenant_shared' => ['id' => 'tenant-shared',...], // objeto
  'tenant' => [                                  // array
    ['id' => 'tenant-one', ...],
    ['id' => 'tenant-two', ...],
  ]
]

// allItems() — retorna array plano:
[
  ['id' => 'central', ...],
  ['id' => 'shared', ...],
  ['id' => 'tenant-shared', ...],
  ['id' => 'tenant-one', ...],
  ['id' => 'tenant-two', ...],
]
```

**Bug histórico (InnoditeContextBridge pre-v3.4.3):** Usar `all()` con doble `foreach` descomponía los objetos únicos en strings. La corrección fue usar `allItems()` con un solo `foreach`.

---

## @API_TARGETSERVICE — MigrationTargetService API

```
Namespace: Innodite\LaravelModuleMaker\Services
```

| Método | Retorno | Descripción |
|--------|---------|-------------|
| `ensureManifestPath(string $name)` | `string` | Resuelve y crea si no existe la ruta del manifiesto. Crea `module-maker-config/migrations/`. Inicializa con `{"migrations":[],"seeders":[]}`. Normaliza nombre. Default: `central.order.json` |
| `resolveExecutionConnection(string $path, bool $dryRun = false)` | `string` | Valida y retorna connection_key (ver @CONEXION). Lanza en error |
| `resolveDatabaseName(string $conn)` | `string` | Extrae `database` de la config de conexión. Retorna `''` si no existe |
| `validateDatabaseExists(string $conn)` | `?string` | `null`=OK, `string`=mensaje de error. Soporta: sqlite, mysql, mariadb, pgsql, sqlsrv |
| `resolveTargetsForCoordinate(string $coord, ?string $manifest)` | `array` | Targets de manifiestos para una coordenada. Auto-detecta: `central.order.json` para central/shared, `{id}.order.json` para cada tenant |
| `addCoordinateIfMissing(array &$plan, string $section, string $coord)` | `bool` | Añade coord al plan si no existe. `true`=añadida, `false`=ya existía |

### `resolveTargetsForCoordinate` — Retorno

```php
array<int, array{
    manifest: string,    // ruta absoluta al manifest
    type: string,        // 'central' | 'tenant'
    id: string|null,     // id del contexto
    label: string        // etiqueta para mostrar
}>
```

---

## @API_PLANRESOLVER — MigrationPlanResolver API

```
Namespace: Innodite\LaravelModuleMaker\Services
```

| Método | Retorno | Descripción |
|--------|---------|-------------|
| `resolveManifestPath(?string $opt)` | `string` | Resuelve ruta del manifest. Fallback: `central.order.json` |
| `loadPlan(string $path)` | `array` | Carga y valida JSON. Retorna `{migrations: [], seeders: []}` |
| `resolveMigrationCoordinate(string $coord)` | `array` | Resuelve ruta física de una coordenada de migración |
| `resolveSeederCoordinate(string $coord)` | `array` | Resuelve FQCN de un seeder |

### `resolveMigrationCoordinate` — Retorno

```php
[
    'module' => 'User',
    'contextPath' => 'Central',
    'file' => '2024_01_01_create_users_table.php',
    'path' => '/absolute/path/to/migration'
]
```

### `resolveSeederCoordinate` — Retorno

```php
[
    'module' => 'User',
    'contextPath' => 'Central',
    'className' => 'UserDatabaseSeeder',
    'fqcn' => 'Modules\\User\\Database\\Seeders\\Central\\UserDatabaseSeeder'
]
```

---

## @MIDDLEWARE — InnoditeContextBridge

```
Namespace: Innodite\LaravelModuleMaker\Middleware
Alias: innodite.bridge
```

### Propósito

Sincroniza contexto activo y permisos del usuario al frontend vía `Inertia::share()`.

### Datos compartidos con Inertia

```php
Inertia::share([
    'auth' => [
        'context' => [
            'route_prefix' => 'energy-spain',      // string|null
            'permission_prefix' => 'energy_spain'  // string|null
        ],
        'permissions' => ['energy_spain_roles_index', 'energy_spain_users_store']
    ]
]);
```

### `resolveContext(Request $request): array`

Detecta el contexto activo comparando la request actual contra TODOS los contextos (vía `ContextResolver::allItems()`).

**Estrategia de coincidencia (por prioridad):**
1. Nombre de ruta con `route_name` del contexto
2. Path de URL con `route_prefix` del contexto

**CRÍTICO:** Debe usar `allItems()` (array plano), NO `all()` con doble foreach.

### `resolvePermissions(Request $request): array`

Cadena de resolución de permisos:
1. Spatie Permission: `$user->getAllPermissions()->pluck('name')`
2. Interface propia: `$user->getInnoditePermissions()` (si implementa `InnoditeUserPermissions`)
3. Fail-safe: `[]` + warning en log

### Registro en Bootstrap (Laravel 11+)

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', [
        \Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge::class,
    ]);
})
```

---

## @COMANDOS — Todos los Comandos Artisan

### 1. `innodite:make-module`

```bash
php artisan innodite:make-module {name} {--context=} {--json} {--no-routes}
                                        {--M|model} {--C|controller} {--S|service}
                                        {--R|repository} {--G|migration} {--Q|request}
```

**Opciones:**
- `name` — Nombre de la entidad en singular (se convierte a PascalCase)
- `--context=` — Contexto explícito (central | shared | tenant_shared | id-del-tenant)
- `--json` — Usa `module-maker-config/{module}.json` como fuente de config dinámica
- `--no-routes` — Omite inyección de rutas
- `-M/--model`, `-C/--controller`, `-S/--service`, `-R/--repository`, `-G/--migration`, `-Q/--request` — Solo genera ese componente

**Modos:**
```bash
# Módulo completo con contexto explícito
php artisan innodite:make-module User --context=central

# Selección interactiva de contexto
php artisan innodite:make-module User

# Solo servicio + repositorio en módulo existente
php artisan innodite:make-module User --context=shared -S -R

# Desde JSON dinámico
php artisan innodite:make-module User --json

# Sin inyección de rutas
php artisan innodite:make-module User --context=central --no-routes
```

**Flujo:**
1. Validar nombre (regex + palabras reservadas PHP/Laravel)
2. Resolver contexto
3. Generar estructura de archivos (ModuleGenerator)
4. Inyectar rutas (RouteInjectionService) — salvo `--no-routes`
5. Auditoría (ModuleAuditor)
6. Rollback opcional si hay error

---

### 2. `innodite:module-setup`

```bash
php artisan innodite:module-setup
```

**Crea:**
- `Modules/` en project root
- `module-maker-config/` con `contexts.json` (template)
- `module-maker-config/stubs/contextual/` con todos los stubs
- Modifica `DatabaseSeeder.php` para incluir InnoditeModuleSeeder

---

### 3. `innodite:migrate-plan`

```bash
php artisan innodite:migrate-plan {--manifest=} {--dry-run} {--seed}
```

**Opciones:**
- `--manifest=` — Manifiesto a ejecutar (ej: `central.order.json`)
- `--dry-run` — Solo muestra el plan sin ejecutar
- `--seed` — Ejecuta seeders después de migraciones

**Flujo:**
1. Resolver manifest → `MigrationPlanResolver::resolveManifestPath()`
2. `resolveExecutionConnection()` → valida conexión
3. Ejecutar migraciones en orden (`migrate` command con `--database`)
4. Ejecutar seeders opcionales (`db:seed`)

---

### 4. `innodite:migrate-one`

```bash
php artisan innodite:migrate-one {coordinate} {--manifest=} {--yes} {--dry-run}
```

**Argumentos:**
- `coordinate` — Coordenada (ej: `User:Central/2024_01_01_create_users_table.php`)

**Flujo:**
1. Resolver coordenada
2. Detectar manifiestos aplicables (selección interactiva si hay múltiples)
3. Validar conexión via `ContextResolver::validateConnection()`
4. Registrar en manifest si falta (`addCoordinateIfMissing`)
5. Ejecutar migración

---

### 5. `innodite:seed-one`

```bash
php artisan innodite:seed-one {coordinate} {--manifest=} {--yes} {--dry-run}
```

Mismo flujo que `migrate-one` pero para seeders. Coordenada: `User:Central/UserDatabaseSeeder`

---

### 6. `innodite:migration-sync`

```bash
php artisan innodite:migration-sync {--manifest=} {--all-manifests} {--yes} {--dry-run}
```

**Funcionalidad:**
- Escanea `Modules/*/Database/Migrations/` y `Seeders/`
- Detecta coordenadas no registradas en manifiestos
- Actualiza manifiestos automáticamente
- `--all-manifests` — sincroniza todos los contextos en paralelo

---

### 7. `innodite:check-env`

```bash
php artisan innodite:check-env
```

**Verificaciones:**
1. Modelo User con soporte de permisos (Spatie o `InnoditeUserPermissions`)
2. `HandleInertiaRequests` con `auth.permissions` compartido
3. `InnoditeContextBridge` registrado en middleware

Proporciona código de ejemplo para cada requerimiento faltante.

---

### 8. `innodite:module-check`

```bash
php artisan innodite:module-check
```

**Diagnósticos:**
1. Integridad de `contexts.json` (JSON válido, claves requeridas)
2. Permisos de escritura (`Modules/`, `routes/`, `storage/logs/`)
3. Colisiones de nombres entre módulos
4. Estado del audit log

---

### 9. `innodite:test-module`

```bash
php artisan innodite:test-module {module?} {--all} {--context=} {--all-contexts}
                                           {--coverage} {--format=*} {--filter=}
                                           {--stop-on-failure} {--no-output}
```

**Características:**
- Ejecuta tests de un módulo específico o todos
- Filtra por contexto (central, tenant, etc.)
- Genera reportes de cobertura: HTML, Text, Clover (requiere Xdebug/PCOV)
- Reportes en `docs/test-reports/`

---

### 10. `innodite:test-sync`

```bash
php artisan innodite:test-sync
```

Sincroniza configuración de tests entre módulos (phpunit configs por contexto).

---

### 11. `innodite:migrate-modules`

```bash
php artisan innodite:migrate-modules {--dry-run}
```

Migra módulos de v2.x (`routes/`) a v3.0.0 (`Routes/`). Solo necesario en proyectos legacy.

---

### 12. `innodite:publish-frontend`

```bash
php artisan innodite:publish-frontend
```

Publica composables Vue 3 y componentes del paquete al proyecto.

---

## @GENERADORES — Sistema de Generación

### AbstractComponentGenerator

**Base class para todos los generadores.**

```php
protected string $moduleName;
protected string $modulePath;
protected bool $isClean;
protected array $componentConfig;
protected ?OutputInterface $output = null;
private ?array $resolvedContext = null;  // cache

abstract public function generate(): void;
public function setOutput(OutputInterface $output): static;
protected function getStubContent(string $stubFile, array $replacements = []): string;
```

### Resolución de stubs (prioridad)

```
1. {config_path}/stubs/contextual/{ContextFolder}/{stub}  ← custom por contexto
2. {config_path}/stubs/contextual/{stub}                  ← custom genérico
3. package/stubs/contextual/{ContextFolder}/{stub}        ← paquete por contexto
4. package/stubs/contextual/{stub}                        ← paquete genérico
```

### Placeholders en stubs

Formato: `{{ placeholder_name }}`

| Placeholder | Valor |
|-------------|-------|
| `{{ namespace }}` | Namespace completo del componente |
| `{{ modelName }}` | Nombre del modelo (PascalCase) |
| `{{ controllerName }}` | Nombre del controlador |
| `{{ serviceInterface }}` | Interface del servicio (FQCN) |
| `{{ serviceInstance }}` | Instancia del servicio (camelCase) |
| `{{ repositoryInterfaceName }}` | Nombre de interface del repositorio |
| `{{ module }}` | Nombre del módulo |
| `{{ viewName }}` | Nombre de la vista Inertia |

---

## @TRAIT_RENDERS — RendersInertiaModule

```
Namespace: Innodite\LaravelModuleMaker\Traits
```

### Métodos

#### `renderModule(string $module, string $component, array $data = []): InertiaResponse`

Renderiza componente Inertia resolviendo la vista automáticamente según prefijo.

```php
// En controller:
use RendersInertiaModule;

return $this->renderModule('UserManagement', 'CentralUserList', ['users' => $users]);
// → Inertia::render('UserManagement::Central/UserList', ['users' => $users])

return $this->renderModule('UserManagement', 'TenantOneUserList', ['users' => $users]);
// → Inertia::render('UserManagement::Tenant/One/UserList', [...])

return $this->renderModule('UserManagement', 'TenantSharedUserList', ['users' => $users]);
// → Inertia::render('UserManagement::Tenant/Shared/UserList', [...])
```

#### `resolveBaseFolder(string $filename): string`

Mapea prefijo del componente a carpeta `Pages/`. Construido dinámicamente desde `contexts.json`:
- Tenants primero (prefijos más específicos, ordenados por longitud desc)
- Fallback a Central/Shared/TenantShared

---

## @CONTRATO — InnoditeUserPermissions

```php
// src/Contracts/InnoditeUserPermissions.php
namespace Innodite\LaravelModuleMaker\Contracts;

interface InnoditeUserPermissions
{
    public function getInnoditePermissions(): array;
}
```

**Uso en modelo User (alternativa a Spatie):**

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

## @EXCEPTIONS — Excepciones

### ContextNotFoundException

```
extends InvalidArgumentException
```

**Factory methods:**

```php
// Contexto no encontrado por ID
ContextNotFoundException::forId(string $contextKey, string $id, array $available = []): self

// Clave de contexto no encontrada
ContextNotFoundException::contextKeyNotFound(string $contextKey, array $available = []): self

// connection_key inválido
ContextNotFoundException::invalidConnectionKey(string $contextId, string $connectionKey): self
```

### ConnectionNotConfiguredException

```
extends \RuntimeException
```

**Factory method:**

```php
ConnectionNotConfiguredException::forContext(string $contextId, string $connectionKey): self
```

**Mensaje:** `"La conexión '{connectionKey}' para el contexto '{contextId}' no está definida en config/database.php. Regístrela en config/database.php antes de ejecutar migraciones."`

---

## @AUDITOR — ModuleAuditor

```
Formato: NDJSON (Newline-Delimited JSON)
Log: storage/logs/module_maker.log
```

**Eventos:**
- `module.created`
- `module.components`
- `routes.injected`
- `module.rollback`

**Entrada de ejemplo:**
```json
{"timestamp":"2025-01-01T12:00:00+00:00","event":"module.created","package":"innodite/laravel-module-maker","version":"3.5.0","module":"User","context_key":"central","context_id":"central","functionality":"users","routes":true}
```

---

## @ROUTES_INJECTION — RouteInjectionService

**Inyecta bloques de rutas idempotentemente** (nunca duplica).

### Marcadores esperados en archivos de rutas

```php
// web.php
// {{CENTRAL_ROUTES_END}}

// tenant.php (shared)
// {{TENANT_SHARED_ROUTES_END}}

// tenant.php (tenant específico, ej: energy-spain)
// {{TENANT_ENERGY-SPAIN_ROUTES_END}}
```

### Garantías

- **Idempotente:** detecta si el bloque ya existe antes de insertar
- **Auto-import:** añade `use ControllerClass` si falta
- **Middleware opcional:** omite `->middleware()` si `route_middleware` es `[]`
- **Indentación:** respeta nivel de sangría del marcador

---

## @SERVICE_PROVIDER — LaravelModuleMakerServiceProvider

### `register()`

- Merge config `make-module.php`
- Alias middleware `innodite.bridge` → `InnoditeContextBridge`
- Singleton `innodite.module_seeder` (descubre seeders de módulos)

### `boot()`

**Discovery de módulos en `Modules/`:**
1. Service Providers → auto-registra
2. Rutas → prioridad: `Routes/` (v3+) sobre `routes/` (v2 legacy)
   - `web.php` → middleware `web`
   - `tenant.php` → sin wrapper de middleware
   - `api.php` → middleware `api`
3. Vistas → registra namespace del módulo
4. Traducciones
5. Migraciones → discovery recursivo de subcarpetas (`scanMigrationDirectories`)
6. Factory resolution para módulos
7. Detecta primera instalación → sugiere `innodite:module-setup`

**Comandos registrados (12):**
MakeModuleCommand, SetupModuleMakerCommand, MigratePlanCommand, MigrateOneCommand, SeedOneCommand, MigrationSyncCommand, CheckEnvCommand, ModuleCheckCommand, TestModuleCommand, TestSyncCommand, MigrateModulesCommand, PublishFrontendCommand

---

## @ESTRUCTURA_MODULO — Estructura Generada v3.0.0

```
Modules/{ModuleName}/
├── Docs/
│   ├── history.md
│   ├── architecture.md
│   └── schema.md
├── Database/
│   ├── Factories/     {Central/, Shared/, Tenant/{Shared/, TenantName/}}
│   ├── Migrations/    {Central/, Shared/, Tenant/{Shared/, TenantName/}}
│   └── Seeders/       {Central/, Shared/, Tenant/{Shared/, TenantName/}}
├── Http/
│   ├── Controllers/   {Central/, Shared/, Tenant/{Shared/, TenantName/}}
│   ├── Middleware/
│   └── Requests/      {Central/, Shared/, Tenant/{Shared/, TenantName/}}
├── Models/            {Central/, Shared/, Tenant/{Shared/, TenantName/}}
├── Providers/
│   └── {ModuleName}ServiceProvider.php
├── Repositories/
│   ├── Contracts/     {Central/, Shared/, Tenant/...}
│   └── {Central/, Shared/, Tenant/...}
├── Resources/
│   ├── js/Pages/      {Central/, Shared/, Tenant/{Shared/, TenantName/}}
│   ├── views/
│   └── lang/
├── Routes/            ← v3.0.0+  (legacy: routes/ para v2.x)
│   ├── web.php
│   ├── tenant.php
│   └── api.php
├── Services/
│   ├── Contracts/     {Central/, Shared/, Tenant/...}
│   └── {Central/, Shared/, Tenant/...}
├── Console/Commands/  {Central/, Shared/, Tenant/...}
├── Jobs/              {Central/, Shared/, Tenant/...}
├── Notifications/     {Central/, Shared/, Tenant/...}
├── Exceptions/
└── Tests/
    ├── Feature/       {Central/, Shared/, Tenant/...}
    ├── Unit/          {Central/, Shared/, Tenant/...}
    └── Support/
```

**Contextos base creados automáticamente en cada carpeta:** `Central/`, `Shared/`, `Tenant/Shared/`
Los contextos de tenant específicos se crean on-demand según el contexto elegido.

---

## @TESTCASE — TestCase Base del Paquete

```
tests/TestCase.php
Extiende: Orchestra\Testbench\TestCase
```

### setUp()

```php
1. $tempBase = sys_get_temp_dir() . '/innodite-tests-{uniqid}'
2. parent::setUp()  → dispara getEnvironmentSetUp() internamente
3. mkdir: {tempBase}/Modules/, module-maker-config/, routes/
4. copy stubs/contexts.json → {tempBase}/module-maker-config/contexts.json
```

### tearDown()

```php
ContextResolver::flush();
File::deleteDirectory($tempBase);
```

### getEnvironmentSetUp($app)

```php
$app['config']->set('make-module.module_path', $tempBase . '/Modules');
$app['config']->set('make-module.config_path', $tempBase . '/module-maker-config');
$app['config']->set('make-module.contexts_path', $tempBase . '/module-maker-config/contexts.json');
```

### Helper

```php
protected function tempPath(string $path = ''): string
// retorna: {tempBase}/{$path}
```

---

## @CONFIGURACION — make-module.php

```php
// config/make-module.php
return [
    'module_path'   => base_path('Modules'),
    'config_path'   => base_path('module-maker-config'),
    'contexts_path' => base_path('module-maker-config/contexts.json'),
    'stubs' => [
        'path' => base_path('module-maker-config/stubs')
    ]
];
```

---

## @BUGS — Bugs Resueltos

| Release | Síntoma | Causa raíz | Fix |
|---------|---------|------------|-----|
| R01 | 5 tests exit code 1 con `--context=central` | `$contextId` indefinido en ModuleGenerator; claves `context_name` vs `context_id` | Renombrar param, corregir claves |
| R01 | Contaminación entre tests | `ContextResolver::$data` estático sin flush entre tests | `flush()` en tearDown |
| R03 | Guard no lanza para tenant | `config("database.connections.X") === null` evalúa `false` en algunos contextos | Cambiar a `!is_array(config(...))` |
| R03.1 | dry-run falla cuando conexión no existe | `resolveExecutionConnection` validaba config/database.php siempre | Pasar `$dryRun` y saltar check con `!$dryRun &&` |
| R03.1 | `expectsOutputToContain` falla en cadena | PendingCommand consume la línea completa. Dos aserciones sobre la misma línea → segunda siempre falla | Usar un solo `expectsOutputToContain` con substring combinado |
| R03.2 | `resolveExecutionConnection` duplicado | Existía en `MigratePlanCommand` Y en `MigrationTargetService` con lógica distinta | Consolidar en `MigrationTargetService`, `MigratePlanCommand` delega |
| v3.4.3 | `InnoditeContextBridge` rompía rutas en proyectos con estructura híbrida | `resolveContext()` usaba `ContextResolver::all()` con doble foreach que descomponía objetos únicos en strings | Cambiar a `ContextResolver::allItems()` con un solo foreach |

---

## @ESTADO — Estado Actual

```
Versión actual: v3.5.0
Tests del paquete: 28 passing, 1 skipped (pdo_sqlite unavailable en CI)
```

**Completado:**
- ✅ R01: Fix name→id migration y variables indefinidas
- ✅ R02: Sync automático de manifests por contexto
- ✅ R03: Normalización manifests (central.order.json) + Guard Rail conexiones explícitas
- ✅ R03.1: Refactor `resolveExecutionConnection` — validación única, soporte dry-run, sin fallback
- ✅ R03.2: Consolidación `resolveExecutionConnection` en `MigrationTargetService`
- ✅ R03.3: Tag v3.4.2 publicado en Packagist
- ✅ v3.4.3: Fix InnoditeContextBridge — `all()` → `allItems()` en `resolveContext()`

**Pendiente:**
- 🟡 R04: Comando `innodite:make-connections` (generador de config de conexiones)
