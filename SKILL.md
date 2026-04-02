# 🎯 SKILL.md — Conocimiento Experto en Innodite Laravel Module Maker v3.0.0

> **Rol:** Arquitecto de Software Senior especializado en PHP/Laravel
> **Propósito:** Dominio completo de la arquitectura, patrones y funcionamiento del paquete
> **Versión:** 3.0.0 (Arquitectura Contextualizada)

---

## 📐 ARQUITECTURA GENERAL

### Concepto Central: Arquitectura Multi-Contexto

El paquete genera módulos Laravel siguiendo una arquitectura **contextualizada** que permite:
- **Central**: Lógica administrativa central del sistema
- **Shared**: Código compartido entre central y tenants
- **TenantShared**: Funcionalidad común a todos los tenants
- **Tenant Específico**: Código aislado por cada tenant del sistema

**Filosofía de diseño:**
- **DRY (Don't Repeat Yourself)**: Un generador para todos los contextos
- **Convention over Configuration**: Prefijos y carpetas se derivan automáticamente
- **Explicit is better than implicit**: El contexto se declara explícitamente en `contexts.json`

---

## 🗂️ ESTRUCTURA DE CAPAS (Onion Architecture Adaptada)

```
┌─────────────────────────────────────────────────────┐
│      CAPA DE COMANDOS (Entry Points)                │
│   ┌────────────────────────────────────────┐        │
│   │ MakeModuleCommand (Orquestador)        │        │
│   │ ModuleCheckCommand (Diagnóstico)       │        │
│   │ SetupModuleMakerCommand (Instalación)  │        │
│   │ CheckEnvCommand (Validación Entorno)   │        │
│   │ PublishFrontendCommand (Composables)   │        │
│   └────────────────────────────────────────┘        │
└─────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────┐
│      CAPA DE GENERADORES (Domain Logic)             │
│   ┌────────────────────────────────────────┐        │
│   │ ModuleGenerator (Orquestador)          │        │
│   │   ├── ControllerGenerator              │        │
│   │   ├── ServiceGenerator                 │        │
│   │   ├── RepositoryGenerator              │        │
│   │   ├── ModelGenerator                   │        │
│   │   ├── RouteGenerator                   │        │
│   │   ├── VueGenerator                     │        │
│   │   ├── MigrationGenerator               │        │
│   │   ├── RequestGenerator                 │        │
│   │   ├── ProviderGenerator                │        │
│   │   ├── SeederGenerator                  │        │
│   │   ├── TestGenerator                    │        │
│   │   ├── JobGenerator                     │        │
│   │   ├── NotificationGenerator            │        │
│   │   ├── ConsoleCommandGenerator          │        │
│   │   ├── ExceptionGenerator               │        │
│   │   └── FactoryGenerator                 │        │
│   │       └── Strategies (Value Generators)│        │
│   └────────────────────────────────────────┘        │
│   ┌────────────────────────────────────────┐        │
│   │ AbstractComponentGenerator (Base)      │        │
│   │   - buildNamespace()                   │        │
│   │   - buildPath()                        │        │
│   │   - prefixClass()                      │        │
│   │   - getContextFolder()                 │        │
│   │   - getClassPrefix()                   │        │
│   └────────────────────────────────────────┘        │
└─────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────┐
│      CAPA DE SERVICIOS (Business Logic)             │
│   ┌────────────────────────────────────────┐        │
│   │ RouteInjectionService                  │        │
│   │   - inject()                           │        │
│   │   - injectIntoFile()                   │        │
│   │   - resolveMarkerKey()                 │        │
│   │   - appendTenantSection()              │        │
│   │   - detectIndentation()                │        │
│   │   - buildBlock()                       │        │
│   └────────────────────────────────────────┘        │
│   ┌────────────────────────────────────────┐        │
│   │ ModuleAuditor (NDJSON Logging)         │        │
│   │   - log()                              │        │
│   │   - readLog()                          │        │
│   └────────────────────────────────────────┘        │
└─────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────┐
│      CAPA DE SOPORTE (Infrastructure)               │
│   ┌────────────────────────────────────────┐        │
│   │ ContextResolver (contexts.json)        │        │
│   │   - resolve()                          │        │
│   │   - resolveItem()                      │        │
│   │   - resolveTenant()                    │        │
│   │   - allTenants()                       │        │
│   │   - allContextItems()                  │        │
│   └────────────────────────────────────────┘        │
└─────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────┐
│      RUNTIME (Laravel Application)                  │
│   ┌────────────────────────────────────────┐        │
│   │ LaravelModuleMakerServiceProvider      │        │
│   │   - boot() → loadModuleRoutes()        │        │
│   │   - register() → middleware/seeder     │        │
│   └────────────────────────────────────────┘        │
│   ┌────────────────────────────────────────┐        │
│   │ InnoditeContextBridge (Middleware)     │        │
│   │   - resolveContext()                   │        │
│   │   - resolvePermissions()               │        │
│   │   - Inertia::share()                   │        │
│   └────────────────────────────────────────┘        │
│   ┌────────────────────────────────────────┐        │
│   │ RendersInertiaModule (Trait)           │        │
│   │   - renderModule()                     │        │
│   │   - resolveView()                      │        │
│   │   - resolveBaseFolder()                │        │
│   └────────────────────────────────────────┘        │
└─────────────────────────────────────────────────────┘
```

---

## 🧬 PATRONES DE DISEÑO IMPLEMENTADOS

### 1. **Template Method Pattern**
- **Ubicación**: `AbstractComponentGenerator`
- **Propósito**: Define el esqueleto común para todos los generadores
- **Método Template**: `generate()` (abstracto)
- **Operaciones Comunes**: `buildNamespace()`, `buildPath()`, `prefixClass()`

### 2. **Strategy Pattern**
- **Ubicación**: `Factory/Strategies/`
- **Propósito**: Generación dinámica de valores para Factory seeders
- **Estrategias**:
  - `BooleanStrategy`: Valores bool aleatorios
  - `DateStrategy`: Fechas faker
  - `EnumStrategy`: Valores de enum
  - `ForeignIdStrategy`: IDs de relaciones
  - `IntegerStrategy`: Enteros faker
  - `TextStrategy`: Strings faker

### 3. **Facade Pattern**
- **Ubicación**: `ContextResolver`
- **Propósito**: Interfaz simple para acceso complejo a `contexts.json`
- **Ocultación**: Parsing JSON, cache, fallback a template

### 4. **Service Locator Pattern**
- **Ubicación**: `LaravelModuleMakerServiceProvider`
- **Propósito**: Registro de servicios y middleware en el contenedor de Laravel

### 5. **Chain of Responsibility Pattern**
- **Ubicación**: `InnoditeContextBridge::resolvePermissions()`
- **Cadena**:
  1. Spatie\Permission (`getAllPermissions()`)
  2. InnoditeUserPermissions (`getInnoditePermissions()`)
  3. Fail-safe (retorna `[]` + Warning)

### 6. **Builder Pattern** (implícito)
- **Ubicación**: `RouteInjectionService::buildBlock()`
- **Propósito**: Construcción incremental de bloques de rutas CRUD

---

## 🔑 CONCEPTOS FUNDAMENTALES

### A. Sistema de Contextos (contexts.json)

**Estructura:**
```json
{
  "contexts": {
    "central": [
      {
        "name": "App Central",
        "class_prefix": "Central",
        "folder": "Central",
        "namespace_path": "Central",
        "route_file": "web.php",
        "route_prefix": "central",
        "route_name": "central.",
        "permission_prefix": "central",
        "permission_middleware": "central-permission",
        "route_middleware": ["web", "auth"],
        "wrap_central_domains": true
      }
    ],
    "tenant": [
      {
        "name": "INNODITE",
        "class_prefix": "TenantInnodite",
        "folder": "Tenant/INNODITE",
        "namespace_path": "Tenant\\INNODITE",
        "route_file": "tenant.php",
        "route_prefix": "innodite",
        "route_name": "innodite.",
        "permission_prefix": "innodite",
        "permission_middleware": "tenant-permission"
      }
    ]
  }
}
```

**Claves críticas:**
- `class_prefix`: Se antepone al nombre de todas las clases (ej: `CentralUserController`)
- `folder`: Carpeta física dentro de cada capa (ej: `Services/Central/`)
- `namespace_path`: Segmento de namespace (ej: `Modules\User\Services\Central`)
- `route_file`: Determina si escribe en `web.php` o `tenant.php`

**Resolución de contextos:**
- `ContextResolver::resolve('central')` → Primer item del contexto central
- `ContextResolver::resolveItem('tenant', 'INNODITE')` → Tenant específico por nombre
- `ContextResolver::allTenants()` → Todos los tenants del proyecto

---

### B. Sistema de Stubs (Template Engine)

**Trait: `HasStubs`**

**Orden de Prioridad (Cascada):**
1. `{project}/module-maker-config/stubs/contextual/{ContextFolder}/{stub}`
2. `{project}/module-maker-config/stubs/contextual/{stub}`
3. `{package}/stubs/contextual/{ContextFolder}/{stub}`
4. `{package}/stubs/contextual/{stub}` ← **Fallback genérico**

**Método de normalización:**
```php
normalizeContextFolder(?string $context): ?string
```
- Convierte: `"central"` → `"Central"`
- Convierte: `"Tenant/Shared"` → `"TenantShared"`
- Convierte: `"Tenant/INNODITE"` → `"TenantName"`

**Reemplazo de placeholders:**
```php
replacePlaceholders(string $stub, array $placeholders): string
```
- Formato esperado: `{{ keyName }}`
- Ejemplo: `{{ namespace }}` → `Modules\User\Http\Controllers\Central`

---

### C. Sistema de Rutas (Carga Automática)

**ServiceProvider: `loadModuleRoutes()`**

```php
private function loadModuleRoutes(string $modulePath): void
{
    $routesPath = "{$modulePath}/Routes";
    
    // web.php → middleware 'web'
    if (File::exists("{$routesPath}/web.php")) {
        Route::middleware('web')->group(function () use ($routesPath) {
            require "{$routesPath}/web.php";
        });
    }
    
    // tenant.php → sin wrapper (self-contained)
    if (File::exists("{$routesPath}/tenant.php")) {
        require "{$routesPath}/tenant.php";
    }
    
    // api.php → middleware 'api'
    if (File::exists("{$routesPath}/api.php")) {
        Route::middleware('api')->group(function () use ($routesPath) {
            require "{$routesPath}/api.php";
        });
    }
}
```

**RouteGenerator: Generación de Archivos**

- **Central**: Envuelve en `foreach (config('tenancy.central_domains'))`
- **Shared**: Escribe en AMBOS `web.php` Y `tenant.php` (dual routing)
- **TenantShared**: Genera un bloque POR CADA tenant del proyecto
- **Tenant Específico**: Un solo bloque para ese tenant

**Marcadores de inyección:**
- `// {{CENTRAL_END}}` → Fin del bloque central
- `// {{TENANT_SHARED_ROUTES_END}}` → Fin del bloque tenant shared
- `// {{TENANT_INNODITE_END}}` → Fin del bloque tenant específico

---

### D. Sistema de Prefijos (Naming Convention)

**Lógica en `AbstractComponentGenerator::prefixClass()`**

```php
protected function prefixClass(string $className): string
{
    $prefix = $this->getClassPrefix();  // Ej: "Central", "TenantInnodite"
    return $prefix ? $prefix . $className : $className;
}
```

**Ejemplos de salida:**

| Contexto       | Modelo | Controlador                         | Service                           |
|----------------|--------|-------------------------------------|-----------------------------------|
| central        | User   | `CentralUserController`             | `CentralUserService`              |
| shared         | Role   | `SharedRoleController`              | `SharedRoleService`               |
| tenant_shared  | Plan   | `TenantSharedPlanController`        | `TenantSharedPlanService`         |
| tenant INNODITE| Client | `TenantInnoditeClientController`    | `TenantInnoditeClientService`     |

**Regla de carpetas:**
- Controlador central: `Http/Controllers/Central/CentralUserController.php`
- Service tenant: `Services/Tenant/INNODITE/TenantInnoditeClientService.php`
- Repository shared: `Repositories/Shared/SharedRoleRepository.php`

---

### E. Sistema de Contracts (Interfaces)

**Arquitectura WORKPLAN v3.0.0:**

```
Services/
  Contracts/
    Central/
      CentralUserServiceInterface.php
    Tenant/
      INNODITE/
        TenantInnoditeClientServiceInterface.php
  Central/
    CentralUserService.php  (implementa la interface)
  Tenant/
    INNODITE/
      TenantInnoditeClientService.php  (implementa la interface)
```

**Métodos de construcción:**
```php
buildContractsNamespace(string $componentType): string
// → "Modules\User\Services\Contracts\Central"

buildContractsPath(string $componentType): string
// → "{modulePath}/Services/Contracts/Central"
```

**Binding en ServiceProvider del módulo:**
```php
$this->app->bind(
    CentralUserServiceInterface::class,
    CentralUserService::class
);
```

---

### F. Arquitectura Frontend (Inertia.js + Vue 3 + Axios)

**REGLA OBLIGATORIA:**
- **Inertia.js**: SOLO navegación entre páginas (`router.visit()`, `router.get()`)
- **Axios**: TODOS los datos (`axios.get()`, `axios.post()`, `axios.put()`, `axios.delete()`)
- **Props de Inertia**: NUNCA para datos de negocio, solo para metadatos de la página

**Trait: `RendersInertiaModule`**

```php
protected function renderModule(
    string $module,     // 'UserManagement'
    string $component,  // 'TenantInnoditeUserIndex'
    array $data = []    // Props (SOLO metadatos)
): InertiaResponse
```

**Resolución de vistas:**
- Prefijo del archivo Vue determina la carpeta destino
- Mapa dinámico desde `contexts.json` (prefijos → folders)
- Sin cascadas ni detección runtime

**Ejemplo:**
```php
return $this->renderModule('User', 'CentralUserIndex');
// → Busca: Modules/User/Resources/js/Pages/Central/CentralUserIndex.vue
```

**Estructura generada:**
```
Resources/js/Pages/
  Central/
    CentralUserIndex.vue
    CentralUserCreate.vue
    CentralUserEdit.vue
    CentralUserShow.vue
  Tenant/
    INNODITE/
      TenantInnoditeClientIndex.vue
      TenantInnoditeClientCreate.vue
```

---

### G. Bridge Frontend-Backend (InnoditeContextBridge)

**Middleware de sincronización:**

```php
\Inertia\Inertia::share([
    'auth' => [
        'context' => [
            'route_prefix' => 'innodite',         // Para construir URLs
            'permission_prefix' => 'innodite'     // Para validar permisos
        ],
        'permissions' => ['innodite_users_view', 'innodite_users_edit']
    ]
]);
```

**Resolución de contexto:**
1. Compara `route()->getName()` contra `route_name` de contexts.json
2. Compara `$request->path()` contra `route_prefix` de contexts.json
3. Retorna el primer match o `null`

**Cadena de permisos:**
1. Spatie Permission: `$user->getAllPermissions()->pluck('name')`
2. InnoditeUserPermissions: `$user->getInnoditePermissions()`
3. Fail-safe: `[]` + Warning en log

**Composables Vue 3:**
- `useModuleContext()`: Construye URLs conscientes de contexto
- `usePermissions()`: Valida permisos con doble estrategia

---

## 🔍 FLUJOS DE TRABAJO CRÍTICOS

### Flujo 1: Generación de Módulo Completo

```
Usuario ejecuta:
  php artisan innodite:make-module User --context=central

   ↓
MakeModuleCommand::handle()
   ↓
MakeModuleCommand::handleFullModule()
   ↓
ModuleGenerator::createCleanModuleWithContext('central', 'users')
   ↓
┌────────────────────────────────────────┐
│ Fase 1: Estructura de Carpetas        │
│ - createFolders()                      │
│ - createDocs()                         │
└────────────────────────────────────────┘
   ↓
┌────────────────────────────────────────┐
│ Fase 2: Generación de Componentes     │
│ - ModelGenerator::generate()           │
│ - ControllerGenerator::generate()      │
│ - ServiceGenerator::generate()         │
│ - RepositoryGenerator::generate()      │
│ - RequestGenerator::generate()         │
│ - ProviderGenerator::generate()        │
│ - RouteGenerator::generate()           │
│ - MigrationGenerator::generate()       │
│ - SeederGenerator::generate()          │
│ - FactoryGenerator::generate()         │
│ - TestGenerator::generate()            │
│ - VueGenerator::generate()             │
└────────────────────────────────────────┘
   ↓
┌────────────────────────────────────────┐
│ Fase 3: Inyección de Rutas (opcional) │
│ - RouteInjectionService::inject()      │
│   - Detecta archivo destino (web/tenant)
│   - Busca marcador correspondiente     │
│   - Inyecta bloque de rutas            │
│   - Añade import use del controlador   │
└────────────────────────────────────────┘
   ↓
┌────────────────────────────────────────┐
│ Post-generación: Auditoría             │
│ - ModuleAuditor::log('module.created') │
└────────────────────────────────────────┘
   ↓
ÉXITO → Módulo completo generado
```

---

### Flujo 2: Carga Automática de Rutas en Runtime

```
Aplicación Laravel inicia
   ↓
LaravelModuleMakerServiceProvider::boot()
   ↓
Escanea: base_path('Modules/')
   ↓
Por cada módulo encontrado:
   ↓
┌─────────────────────────────────────────┐
│ 1. Registra ServiceProvider del módulo │
│    {Module}ServiceProvider::class       │
└─────────────────────────────────────────┘
   ↓
┌─────────────────────────────────────────┐
│ 2. Carga rutas del módulo               │
│    loadModuleRoutes("{modulePath}")     │
│    - web.php → Route::middleware('web') │
│    - tenant.php → require (sin wrapper) │
│    - api.php → Route::middleware('api') │
└─────────────────────────────────────────┘
   ↓
┌─────────────────────────────────────────┐
│ 3. Carga vistas (Blade)                 │
│    loadViewsFrom()                      │
└─────────────────────────────────────────┘
   ↓
┌─────────────────────────────────────────┐
│ 4. Carga traducciones                   │
│    loadTranslationsFrom()               │
└─────────────────────────────────────────┘
   ↓
┌─────────────────────────────────────────┐
│ 5. Carga migraciones (discovery)        │
│    - Base: Database/Migrations/         │
│    - Central: Database/Migrations/Central/
│    - Tenant: Database/Migrations/Tenant/*
└─────────────────────────────────────────┘
   ↓
Módulos registrados y activos
```

---

### Flujo 3: Resolución de Stub Contextualizado

```
ControllerGenerator::generate()
   ↓
getStubContent('controller.stub', true, [...])
   ↓
AbstractComponentGenerator::getStubContent()
   ↓
$contextFolder = $this->getContextFolder()  // "Central"
   ↓
getStub($stubFile, $isClean, $contextFolder)
   ↓
HasStubs::getStub()
   ↓
getStubPath($stubFile, $isClean, $contextFolder)
   ↓
┌─────────────────────────────────────────────┐
│ Cascada de resolución:                      │
│                                             │
│ 1. module-maker-config/stubs/contextual/   │
│    Central/controller.stub                  │
│    ❌ No existe                             │
│                                             │
│ 2. module-maker-config/stubs/contextual/   │
│    controller.stub                          │
│    ❌ No existe                             │
│                                             │
│ 3. vendor/.../stubs/contextual/Central/    │
│    controller.stub                          │
│    ✅ ENCONTRADO                            │
└─────────────────────────────────────────────┘
   ↓
File::get($stubPath)
   ↓
replacePlaceholders($stub, [
    '{{ namespace }}' => 'Modules\User\Http\Controllers\Central',
    '{{ controllerName }}' => 'CentralUserController',
    ...
])
   ↓
return $processedStub
```

---

### Flujo 4: Detección de Contexto en Runtime (Middleware)

```
Request entrante: GET /innodite-clients
   ↓
InnoditeContextBridge::handle()
   ↓
resolveContext($request)
   ↓
┌──────────────────────────────────────────┐
│ Estrategia 1: Por nombre de ruta        │
│ $request->route()->getName()             │
│ → "innodite.clients.index"               │
│ Busca: contexts[*][*]['route_name']      │
│ Match: "innodite."                       │
│ ✅ Contexto encontrado                   │
└──────────────────────────────────────────┘
   ↓
return [
    'route_prefix' => 'innodite',
    'permission_prefix' => 'innodite'
]
   ↓
resolvePermissions($request)
   ↓
┌──────────────────────────────────────────┐
│ Cadena de detección:                     │
│ 1. Spatie: $user->getAllPermissions()   │
│ 2. Contract: $user->getInnoditePermissions()
│ 3. Fail-safe: []                         │
└──────────────────────────────────────────┘
   ↓
Inertia::share([
    'auth' => [
        'context' => [...],
        'permissions' => [...]
    ]
])
   ↓
Continuar request
```

---

## 🐛 PATRONES DE BUGS COMUNES

### Bug Pattern 1: Stub No Encontrado

**Síntoma:**
```
Exception: El archivo stub 'controller.stub' no se encuentra.
Buscado en: .../stubs/contextual/controller.stub
```

**Causa raíz:**
- El método `getStubPath()` no encuentra el stub en ninguna ubicación de la cascada
- `contexts.json` puede tener un `folder` inválido que no mapea a ninguna carpeta de stubs

**Solución:**
1. Verificar que `innodite:module-setup` se ha ejecutado
2. Verificar que `stubs/contextual/` existe en el paquete
3. Verificar que `normalizeContextFolder()` está mapeando correctamente

---

### Bug Pattern 2: Namespace Incorrecto

**Síntoma:**
```
PHP Fatal error: Class 'Modules\User\Services\UserService' not found
```

**Causa raíz:**
- El prefijo de contexto NO se está aplicando al namespace
- `AbstractComponentGenerator::buildNamespace()` no está recibiendo el contexto correcto

**Solución:**
1. Verificar que `componentConfig` contiene `'context' => 'central'`
2. Verificar que `getContext()` está resolviendo correctamente
3. Verificar que `getContextNamespacePath()` retorna el valor esperado

---

### Bug Pattern 3: Rutas No Se Cargan Automáticamente

**Síntoma:**
```bash
php artisan route:list
# No aparecen rutas del módulo
```

**Causa raíz:**
- `LaravelModuleMakerServiceProvider::loadModuleRoutes()` no encuentra los archivos
- Composer autoload no está actualizado
- Los archivos `web.php` o `tenant.php` tienen errores de sintaxis

**Solución:**
1. Verificar que `Modules/{Module}/Routes/web.php` existe
2. `composer dump-autoload`
3. `php artisan route:clear`
4. Verificar sintaxis: `php -l Modules/User/Routes/web.php`

---

### Bug Pattern 4: Contexto No Se Detecta en Runtime

**Síntoma:**
```javascript
// En Vue
console.log(page.props.auth.context)
// → { route_prefix: null, permission_prefix: null }
```

**Causa raíz:**
- `InnoditeContextBridge` no está registrado en el middleware stack
- `route_name` o `route_prefix` en `contexts.json` no coinciden con las rutas reales

**Solución:**
1. Registrar middleware en `bootstrap/app.php`:
   ```php
   $middleware->appendToGroup('web', [
       \Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge::class,
   ]);
   ```
2. Verificar coincidencia entre:
   - `contexts.json`: `"route_name": "innodite."`
   - Ruta real: `Route::name('innodite.clients.index')`

---

### Bug Pattern 5: Prefijo Duplicado en Clase

**Síntoma:**
```
Class CentralCentralUserController not found
```

**Causa raíz:**
- `prefixClass()` se está llamando DOS veces en lugar de una
- El stub ya contiene el prefijo hardcodeado

**Solución:**
1. Verificar que el stub usa `{{ controllerName }}` sin prefijo
2. Verificar que `prefixClass()` solo se llama en el generator, no en el stub

---

### Bug Pattern 6: Método getStubPath() con Argumentos Incorrectos

**Síntoma:**
```
Too few arguments to function getStubPath(), 1 passed and at least 2 expected
```

**Causa raíz:**
- Un generator está llamando directamente a `getStubPath()` en lugar de `getStubContent()`
- Version desactualizada del código en `vendor/` vs `src/`

**Solución:**
1. ✅ Usar: `getStubContent($stubFile, $this->isClean, $placeholders)`
2. ❌ NO usar: `getStubPath($stubFile)` directamente
3. `composer dump-autoload` para refrescar autoload

---

## 🔧 HERRAMIENTAS DE DIAGNÓSTICO

### Comando: `innodite:module-check`

**Verificaciones:**
1. ✅ `contexts.json` existe y es JSON válido
2. ✅ Todos los contextos requeridos están definidos
3. ✅ Permisos de escritura en `Modules/`, `routes/`, `storage/logs/`
4. ✅ No hay colisiones de nombres entre módulos

**Uso:**
```bash
php artisan innodite:module-check
```

---

### Comando: `innodite:check-env`

**Verificaciones:**
1. ✅ Modelo `User` tiene `HasRoles` (Spatie) o `InnoditeUserPermissions`
2. ✅ `HandleInertiaRequests` comparte `auth.permissions` y `auth.context`
3. ✅ `InnoditeContextBridge` está en middleware stack

**Uso:**
```bash
php artisan innodite:check-env
```

---

### Comando: `innodite:publish-frontend`

**Publica composables Vue 3:**
- `useModuleContext.js`
- `usePermissions.js`

**Uso:**
```bash
php artisan innodite:publish-frontend
php artisan innodite:publish-frontend --force  # Sobreescribir
```

---

## 📚 CONVENCIONES DE CÓDIGO

### A. Naming Conventions

| Tipo              | Patrón                               | Ejemplo                           |
|-------------------|--------------------------------------|-----------------------------------|
| Controlador       | `{Prefix}{Entity}Controller`         | `CentralUserController`           |
| Service           | `{Prefix}{Entity}Service`            | `TenantInnoditeClientService`     |
| Service Interface | `{Prefix}{Entity}ServiceInterface`   | `SharedRoleServiceInterface`      |
| Repository        | `{Prefix}{Entity}Repository`         | `CentralUserRepository`           |
| Repo Interface    | `{Prefix}{Entity}RepositoryInterface`| `SharedRoleRepositoryInterface`   |
| Model             | `{Entity}` (sin prefijo)             | `User`, `Role`, `Client`          |
| Request           | `{Prefix}{Entity}{Action}Request`    | `CentralUserStoreRequest`         |
| Migración         | `create_{table}_table`               | `create_users_table`              |
| Seeder            | `{Prefix}{Entity}Seeder`             | `TenantInnoditeClientSeeder`      |
| Factory           | `{Entity}Factory` (sin prefijo)      | `UserFactory`, `ClientFactory`    |
| Test              | `{Prefix}{Entity}Test`               | `CentralUserTest`                 |
| Vista Vue         | `{Prefix}{Entity}{Action}.vue`       | `CentralUserIndex.vue`            |

---

### B. Folder Conventions

| Capa           | Patrón                               | Ejemplo                                      |
|----------------|--------------------------------------|----------------------------------------------|
| Controllers    | `Http/Controllers/{ContextFolder}`   | `Http/Controllers/Central/`                  |
| Services       | `Services/{ContextFolder}`           | `Services/Tenant/INNODITE/`                  |
| Contracts      | `Services/Contracts/{ContextFolder}` | `Services/Contracts/Tenant/INNODITE/`        |
| Repositories   | `Repositories/{ContextFolder}`       | `Repositories/Shared/`                       |
| Models         | `Models/{ContextFolder}`             | `Models/Central/`                            |
| Migrations     | `Database/Migrations/{ContextFolder}`| `Database/Migrations/Tenant/INNODITE/`       |
| Tests          | `Tests/Unit/{ContextFolder}`         | `Tests/Unit/Central/`                        |
| Vistas Vue     | `Resources/js/Pages/{ContextFolder}` | `Resources/js/Pages/Tenant/INNODITE/`        |

**Nota:** `{ContextFolder}` se obtiene de `contexts.json` → `folder` (ej: `"Central"`, `"Tenant/INNODITE"`)

---

### C. Route Conventions

| Contexto       | Archivo      | Prefijo      | Nombre              | Middleware                  |
|----------------|--------------|--------------|---------------------|-----------------------------|
| central        | `web.php`    | `central-users` | `central.users.`  | `web,auth,central-permission` |
| shared (web)   | `web.php`    | `central.shared-roles` | `central.shared.roles.` | `web,auth` |
| shared (tenant)| `tenant.php` | `tenant.shared-roles` | `tenant.shared.roles.` | `tenant` |
| tenant_shared  | `tenant.php` | `innodite-clients` | `innodite.clients.` | `tenant,tenant-permission` |
| tenant         | `tenant.php` | `innodite-clients` | `innodite.clients.` | `tenant,tenant-permission` |

---

## 💡 MEJORES PRÁCTICAS

### 1. **Siempre Usar Interfaces (Dependency Injection)**

```php
// ❌ MAL
class CentralUserController
{
    public function __construct(
        private CentralUserService $service
    ) {}
}

// ✅ BIEN
class CentralUserController
{
    public function __construct(
        private CentralUserServiceInterface $service
    ) {}
}
```

---

### 2. **Nunca Acceder al Model Directamente (Solo en Repository)**

```php
// ❌ MAL - Controller accediendo a Model
class CentralUserController
{
    public function index()
    {
        $users = User::all();  // ❌
    }
}

// ✅ BIEN - Controller → Service → Repository → Model
class CentralUserController
{
    public function __construct(
        private CentralUserServiceInterface $service
    ) {}
    
    public function index()
    {
        $users = $this->service->getAllUsers();  // ✅
    }
}
```

---

### 3. **Usar renderModule() en Controladores (Nunca Inertia::render() directo)**

```php
// ❌ MAL
class CentralUserController
{
    public function index()
    {
        return Inertia::render('User::Central/CentralUserIndex');  // ❌
    }
}

// ✅ BIEN
use Innodite\LaravelModuleMaker\Traits\RendersInertiaModule;

class CentralUserController
{
    use RendersInertiaModule;
    
    public function index()
    {
        return $this->renderModule('User', 'CentralUserIndex');  // ✅
    }
}
```

---

### 4. **Axios para Datos, Inertia para Navegación**

```javascript
// ❌ MAL - Pasar datos vía props
public function index()
{
    $users = $this->service->getAllUsers();
    return $this->renderModule('User', 'CentralUserIndex', [
        'users' => $users  // ❌
    ]);
}

// ✅ BIEN - Vista carga sus datos
public function index()
{
    return $this->renderModule('User', 'CentralUserIndex');  // ✅
}

public function list(Request $request)
{
    $users = $this->service->getAllUsers();
    return response()->json($users);  // ✅
}
```

```javascript
// Vue Component
export default {
    mounted() {
        // ✅ BIEN - Cargar datos vía axios
        axios.get('/central-users/list')
            .then(res => {
                this.users = res.data;
            });
    }
}
```

---

### 5. **Propiedad $table en Todos los Models**

```php
// ❌ MAL - Eloquent usa CentralUser → tabla "central_users"
class CentralUser extends Model
{
    // ❌ Tabla incorrecta
}

// ✅ BIEN - Forzar nombre de tabla original
class CentralUser extends Model
{
    protected $table = 'users';  // ✅
}
```

---

### 6. **Validar Contexto en Middleware Stack**

```php
// bootstrap/app.php (Laravel 11+)

->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', [
        \Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge::class,
    ]);
})
```

---

## 🎓 CONOCIMIENTO AVANZADO

### A. Extender un Generator

Para añadir funcionalidad a un generator:

```php
namespace Modules\User\Generators;

use Innodite\LaravelModuleMaker\Generators\Components\ControllerGenerator as BaseGenerator;

class CustomControllerGenerator extends BaseGenerator
{
    public function generate(): void
    {
        // Lógica custom antes
        $this->generateCustom();
        
        // Llamar lógica original
        parent::generate();
        
        // Lógica custom después
        $this->cleanUp();
    }
    
    private function generateCustom(): void
    {
        // Tu lógica aquí
    }
}
```

---

### B. Añadir un Nuevo Contexto

**1. Editar `contexts.json`:**
```json
{
  "contexts": {
    "external": [
      {
        "name": "API Externa",
        "class_prefix": "External",
        "folder": "External",
        "namespace_path": "External",
        "route_file": "api.php",
        "route_prefix": "external",
        "route_name": "external.",
        "permission_prefix": "external",
        "permission_middleware": "api-permission"
      }
    ]
  }
}
```

**2. Crear stubs contextualizados:**
```
module-maker-config/stubs/contextual/External/
  ├── controller.stub
  ├── service.stub
  ├── repository.stub
  └── ...
```

**3. Usar:**
```bash
php artisan innodite:make-module Payment --context=external
```

---

### C. Personalizar un Stub

**1. Copiar stub del paquete:**
```bash
cp vendor/innodite/laravel-module-maker/stubs/contextual/controller.stub \
   module-maker-config/stubs/contextual/Central/controller.stub
```

**2. Editar el stub:**
```php
<?php

namespace {{ namespace }};

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use {{ serviceInterfaceNamespace }};

/**
 * CUSTOM NOTICE: Este controlador fue personalizado
 */
class {{ controllerName }} extends Controller
{
    // ... custom logic
}
```

**3. El paquete usará automáticamente el stub personalizado (prioridad 1 en cascada)**

---

### D. Debug Mode: Ver Qué Stub Se Está Usando

```php
// En HasStubs::getStubPath()
protected function getStubPath(string $stubFile, bool $isClean, ?string $contextFolder = null): string
{
    $customBase  = config('make-module.stubs.path') . '/contextual';
    $packageBase = __DIR__ . '/../../../stubs/contextual';
    $folder = $this->normalizeContextFolder($contextFolder);

    if ($folder) {
        $p = "{$customBase}/{$folder}/{$stubFile}";
        if (File::exists($p)) {
            \Log::debug("Stub usado: {$p}");  // ← ADD THIS
            return $p;
        }
        // ...
    }
    
    // ...
}
```

---

## 🚀 ROADMAP DE CONOCIMIENTO

### Nivel 1: Básico ✅
- [x] Entender la arquitectura multi-contexto
- [x] Conocer los comandos principales
- [x] Comprender el flujo de generación
- [x] Saber leer `contexts.json`

### Nivel 2: Intermedio ✅
- [x] Dominar el sistema de stubs
- [x] Entender la resolución de namespaces
- [x] Conocer los patterns de bugs comunes
- [x] Saber diagnosticar errores

### Nivel 3: Avanzado ✅
- [x] Extender generators existentes
- [x] Crear contextos personalizados
- [x] Personalizar stubs globalmente
- [x] Entender el bridge Frontend-Backend

### Nivel 4: Experto 🎯
- [ ] Contribuir al core del paquete
- [ ] Crear generators completamente nuevos
- [ ] Optimizar performance de generación
- [ ] Implementar hooks de extensibilidad

---

## 📖 REFERENCIAS RÁPIDAS

### Archivos Clave

| Archivo                                  | Propósito                                      |
|------------------------------------------|------------------------------------------------|
| `LaravelModuleMakerServiceProvider.php` | Entry point, registra todo en Laravel         |
| `AbstractComponentGenerator.php`        | Clase base de todos los generators             |
| `HasStubs.php`                          | Trait de resolución de stubs                   |
| `ContextResolver.php`                   | Lectura de contexts.json                       |
| `MakeModuleCommand.php`                 | Comando principal de generación                |
| `RouteInjectionService.php`             | Motor de inyección de rutas                    |
| `InnoditeContextBridge.php`             | Middleware Frontend-Backend                    |
| `RendersInertiaModule.php`              | Trait de controladores para Inertia            |
| `contexts.json`                         | Configuración de contextos del proyecto        |

---

### Métodos Principales

| Método                                  | Clase                        | Propósito                               |
|-----------------------------------------|------------------------------|-----------------------------------------|
| `generate()`                            | AbstractComponentGenerator   | Método template (abstracto)             |
| `buildNamespace()`                      | AbstractComponentGenerator   | Construye namespace completo            |
| `buildPath()`                           | AbstractComponentGenerator   | Construye ruta física                   |
| `prefixClass()`                         | AbstractComponentGenerator   | Añade prefijo de contexto               |
| `getContextFolder()`                    | AbstractComponentGenerator   | Carpeta del contexto ("Central")        |
| `resolve()`                             | ContextResolver              | Primer item de un contexto              |
| `resolveItem()`                         | ContextResolver              | Item específico por nombre              |
| `allTenants()`                          | ContextResolver              | Todos los tenants                       |
| `inject()`                              | RouteInjectionService        | Inyecta rutas en archivo del proyecto   |
| `renderModule()`                        | RendersInertiaModule         | Renderiza vista Inertia contextualizada |
| `log()`                                 | ModuleAuditor                | Registra evento en NDJSON               |

---

## 🎯 CHECKLIST EXPERTO

Cuando trabajes con el paquete, verifica:

- [ ] ✅ `contexts.json` existe y tiene todos los contextos requeridos
- [ ] ✅ Stubs están publicados en `module-maker-config/stubs/contextual/`
- [ ] ✅ `composer dump-autoload` ejecutado después de generar módulos
- [ ] ✅ `InnoditeContextBridge` registrado en middleware stack
- [ ] ✅ Modelo `User` tiene `HasRoles` o `InnoditeUserPermissions`
- [ ] ✅ Todos los controllers usan `RendersInertiaModule`
- [ ] ✅ Ningún controller accede al Model directamente
- [ ] ✅ Todos los services y repositories tienen interfaces
- [ ] ✅ Todos los models tienen `protected $table`
- [ ] ✅ Frontend usa axios para datos, Inertia solo para navegación

---

**Fin del documento SKILL.md — Experto Innodite Laravel Module Maker v3.0.0**

Este documento es el resultado de una auditoría completa del paquete y representa el conocimiento arquitectónico y funcional necesario para ser un experto en el sistema.
