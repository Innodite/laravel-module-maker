# ADN Técnico — innodite/laravel-module-maker

> **Documento vivo.** Se actualiza tras cada modificación de archivo importante.
> NO re-leas los archivos fuente si la información está aquí — actualiza este doc después de cada cambio.
> Última actualización: 2026-04-05 (R03.1)

---

## @ESTRUCTURA — Árbol de Archivos Críticos

```
src/Commands/
  MakeModuleCommand.php         — CLI: genera módulo con contexto
  MigrationSyncCommand.php      — CLI: innodite:migration-sync, crea/actualiza manifests
  MigratePlanCommand.php        — CLI: innodite:migrate-plan, ejecuta manifest completo
  MigrateOneCommand.php         — CLI: innodite:migrate-one, ejecuta una migración
  SeedOneCommand.php            — CLI: innodite:seed-one, ejecuta un seeder

src/Exceptions/
  ContextNotFoundException.php          — extends InvalidArgumentException
  ConnectionNotConfiguredException.php  — extends \RuntimeException (nuevo R03)

src/Generators/Components/
  AbstractComponentGenerator.php  — getContext(), buildNamespace(), buildPath(), prefixClass()
  ModuleGenerator.php             — orquesta generación (usa createCleanModuleWithContext)
  ProviderGenerator.php
  RouteGenerator.php

src/Services/
  MigrationPlanResolver.php    — resolveManifestPath(), loadPlan(), resolveMigrationCoordinate()
  MigrationTargetService.php   — resolveExecutionConnection(), validateDatabaseExists(), resolveTargetsForCoordinate()
  RouteInjectionService.php    — inject(contextKey, entityName, contextId, ...)
  TestContextConfigService.php

src/Support/
  ContextResolver.php          — find(), resolve(), resolveById(), validateConnection(), flush()

stubs/
  contexts.json                — Template estructura híbrida (ver @CONTEXTOS)

tests/
  TestCase.php                 — Base: tempDir + contexts.json copy + ContextResolver::flush()
  Feature/
    ConnectionValidationCommandTest.php  — R03: 3 tests guard rail
    MakeModuleCommandTest.php            — 7 tests generación módulo
    MigratePlanCommandTest.php           — 4 tests (1 skipped pdo_sqlite)
    MigrationSyncCommandTest.php         — 3 tests sync
    MigrateOneCommandTest.php            — 1 test dry-run
    SeedOneCommandTest.php               — 2 tests dry-run
```

---

## @CONTEXTOS — Estructura contexts.json

**REGLA: Estructura HÍBRIDA. NUNCA normalizar a array puro.**

```json
{
  "contexts": {
    "central":       { "id": "central",       "connection_key": "central",    "class_prefix": "Central",     "folder": "Central",        ... },
    "shared":        { "id": "shared",                                         "class_prefix": "Shared",      "folder": "Shared",         ... },
    "tenant_shared": { "id": "tenant-shared",                                  "class_prefix": "TenantShared","folder": "Tenant/Shared",  ... },
    "tenant": [
      { "id": "tenant-one", "connection_key": "tenant_one", "class_prefix": "TenantOne", "folder": "Tenant/TenantOne", ... },
      { "id": "tenant-two", "connection_key": "tenant_two", "class_prefix": "TenantTwo", "folder": "Tenant/TenantTwo", ... }
    ]
  }
}
```

**Campos clave:**
- `id` (kebab-case): identifica el contexto. Usado en: manifest names, CLI args, búsquedas
- `class_prefix` (PascalCase): prefijo de clases PHP (Central, TenantOne)
- `connection_key`: nombre de conexión en config/database.php. AUSENTE en shared y tenant_shared
- `folder`: subcarpeta dentro de cada tipo de componente (Central, Shared, Tenant/TenantOne)
- `namespace_path`: fragmento de namespace PHP (Central, Shared, Tenant\\TenantOne)

---

## @MANIFESTS — Naming de Manifests (post-R03)

**REGLA: TODOS los manifests usan `{id}.order.json`. OBSOLETO: `central_order.json` (guión bajo)**

| Contexto        | Manifest               | connection_key |
|-----------------|------------------------|----------------|
| central+shared  | `central.order.json`   | `central`      |
| tenant-one      | `tenant-one.order.json`| `tenant_one`   |
| tenant-two      | `tenant-two.order.json`| `tenant_two`   |
| custom          | cualquier nombre via `--manifest` | N/A |

**Patrón regex explícito:** `/^([a-z0-9][a-z0-9-]*)\.order\.json$/`
Si no coincide → usa `config('database.default')`.

---

## @CONEXION — Flujo de Resolución de Conexión (post-R03.1)

**Explícito vía ContextResolver. NO heurístico. NO fallback a `config('database.default')`.**

```
resolveExecutionConnection(manifestPath, migrations, seeders, dryRun)
  → basename(manifestPath) → strtolower → preg_match pattern
  → [FAIL] formato inválido → error + return null
  → ContextResolver::find($contextId)
  → [FAIL] contexto no encontrado → error + return null
  → $context['tenancy_strategy'] === 'manual'
  → [FAIL] strategy ≠ 'manual' → error + return null
  → $context['connection_key'] no vacío
  → [FAIL] vacío o null → error + return null
  → if (!$dryRun): is_array(config("database.connections.{$key}"))
  → [FAIL] no existe → error + return null
  → return $connectionKey  (ej: 'tenant_one')
```

**CRÍTICO:** Retorna `?string`. Retorna `null` en cualquier fallo — nunca `config('database.default')`.
El check de `config/database.php` se **salta en dry-run** (conexión real no necesaria).
**Implementado SOLO en:** `MigratePlanCommand::resolveExecutionConnection(string, array, array, bool): ?string`

---

## @GUARD — Guard Rail R03 (validación preventiva)

**Post-R03.1: El Guard está consolidado DENTRO de `resolveExecutionConnection()`.**
NO existe bloque separado en `handle()` de `MigratePlanCommand`.

**Cadena de validaciones en orden (en `resolveExecutionConnection`):**
1. Formato del manifest → `/^([a-z0-9][a-z0-9-]*)\.order\.json$/`
2. Contexto existe en contexts.json → `ContextResolver::find($contextId)`
3. `tenancy_strategy === 'manual'` — NUEVO en R03.1
4. `connection_key` no vacío
5. `config("database.connections.{$key}")` es array (solo si `!$dryRun`)

**En `handle()`:**
```php
$connectionName = $this->resolveExecutionConnection($manifestPath, $migrations, $seeders, $dryRun);
if ($connectionName === null) {
    return self::FAILURE;
}
```

**NOTA CRÍTICA:** La check de `config/database.php` usa `!is_array(config(...))` NO `=== null`.
Todos los errores son una SOLA LÍNEA (sin `\n` internas). Ver bug de PendingCommand en @BUGS.
**MigrateOneCommand y SeedOneCommand** mantienen su propio bloque Guard R03 con `ContextResolver::validateConnection()`.

---

## @API_CONTEXTRESOLVER — ContextResolver API

```
src/Support/ContextResolver.php
Namespace: Innodite\LaravelModuleMaker\Support
Cache: static ?array $data — flush() OBLIGATORIO en tearDown
```

| Método | Signatura | Notas |
|--------|-----------|-------|
| `find` | `(string $id): array` | Búsqueda híbrida. Lanza ContextNotFoundException si no existe |
| `resolve` | `(string $contextKey): array` | Solo contextos de objeto único (central, shared, tenant_shared) |
| `resolveById` | `(string $contextKey, string $id): array` | Acceso por clave + id |
| `resolveTenant` | `(string $id): array` | Alias: resolveById('tenant', $id) |
| `all` | `(): array` | Array de contextos del JSON |
| `allItems` | `(): array` | Array plano de todos los contextos |
| `allTenants` | `(): array` | Solo el array de tenants |
| `validateConnection` | `(string $id): void` | Lanza ConnectionNotConfiguredException si connection_key no está en config/database.php |
| `validateConnections` | `(): array<string,string>` | Valida todas. Retorna mapa [id => error_message] |
| `flush` | `(): void` | Limpia cache. Usar en tearDown |

---

## @API_TARGETSERVICE — MigrationTargetService API

```
src/Services/MigrationTargetService.php
```

| Método | Retorno | Notas |
|--------|---------|-------|
| `ensureManifestPath(string $name)` | `string` | Default: `central.order.json` |
| `resolveExecutionConnection(string $path, array $mig, array $seed)` | `string` | Explícito via find() → connection_key |
| `resolveDatabaseName(string $conn)` | `string` | Nombre de BD de la conexión |
| `validateDatabaseExists(string $conn)` | `?string` | null=OK, string=mensaje de error |
| `resolveTargetsForCoordinate(string $coord, ?string $manifest)` | `array` | Targets aplicables para coordenada |
| `addCoordinateIfMissing(array &$plan, string $section, string $coord)` | `bool` | true si se agregó |

---

## @EXCEPTIONS — Excepciones

| Clase | Extiende | Factory | Cuándo |
|-------|----------|---------|--------|
| `ContextNotFoundException` | `InvalidArgumentException` | `::forId($key,$id,$available)` `::contextKeyNotFound($key,$available)` | find() no encuentra el contexto |
| `ConnectionNotConfiguredException` | `\RuntimeException` | `::forContext($contextId,$connectionKey)` | connection_key no está en config/database.php |

**Mensaje de ConnectionNotConfiguredException:**
`"La conexión '{connectionKey}' para el contexto '{contextId}' no está definida en config/database.php. Regístrela en config/database.php antes de ejecutar migraciones."`

---

## @TESTCASE — TestCase Base

```
tests/TestCase.php — extiende Orchestra\Testbench\TestCase
```

**setUp():**
1. `$tempBase = sys_get_temp_dir() . '/innodite-tests-{uniqid}'`
2. `parent::setUp()` → llama `getEnvironmentSetUp()` internamente
3. Crea: `{tempBase}/Modules/`, `{tempBase}/module-maker-config/`, `{tempBase}/routes/`
4. Copia `stubs/contexts.json` → `{tempBase}/module-maker-config/contexts.json`

**tearDown():** `ContextResolver::flush()` + `File::deleteDirectory($tempBase)`

**Config de test:**
- `make-module.module_path` → `{tempBase}/Modules`
- `make-module.config_path` → `{tempBase}/module-maker-config`
- `make-module.contexts_path` → `{tempBase}/module-maker-config/contexts.json`

---

## @BUGS — Bugs Resueltos

| Release | Síntoma | Causa raíz | Fix |
|---------|---------|------------|-----|
| R01 | 5 tests exit code 1 con --context=central | `$contextId` indefinido en ModuleGenerator, claves `context_name` vs `context_id` | Renombrar param, corregir claves |
| R01 | Contaminación entre tests | `ContextResolver::$data` estático sin flush entre tests | `flush()` en tearDown |
| R03 | Guard no lanza para tenant | `config("database.connections.X") === null` evalúa `false` en algunos contextos | Cambiar a `!is_array(config(...))` |
| R03.1 | dry-run falla cuando conexión no existe | `resolveExecutionConnection` validaba `config/database.php` siempre, sin respetar `$dryRun` | Pasar `$dryRun` y saltar check con `!$dryRun &&` |
| R03.1 | Test: `expectsOutputToContain` falla en cadena | PendingCommand **consume la línea completa** al hacer match. Dos aserciones sobre la misma línea → la segunda siempre falla | Usar un solo `expectsOutputToContain` con substring que incluya ambas cadenas |

---

## @ESTADO — Estado Actual

```
Tests: 28 passing, 1 skipped (pdo_sqlite unavailable)
Última release funcional: post-R03.1
```

**Actividades completadas:**
- ✅ R01: Fix name→id migration y variables indefinidas
- ✅ R02: Sync automático de manifests por contexto
- ✅ R03: Normalización manifests (central.order.json) + Guard Rail conexiones
- ✅ R03.1: Refactor `resolveExecutionConnection` en MigratePlanCommand — validación única, soporte dry-run, sin fallback

**Próxima actividad:**
- 🟡 R04: Comando innodite:make-connections (generador de config de conexiones)
