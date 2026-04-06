# History Log

---

## [2026-04-05] R03.1 — Refactor `resolveExecutionConnection` + Fix dry-run + Fix Tests PendingCommand

**Problema 1 — Fallback silencioso:** `resolveExecutionConnection()` en `MigratePlanCommand` usaba `config('database.default', 'mysql')` como fallback cuando no encontraba `connection_key`. Esto enmascaraba errores de configuración y permitía ejecutar migraciones contra la BD equivocada.

**Problema 2 — Guard Rail duplicado y fuera de lugar:** El Guard Rail R03 existía como bloque separado en `handle()` dentro de `if (!$dryRun)`, mientras `resolveExecutionConnection()` hacía su propia búsqueda de contexto. Lógica partida.

**Problema 3 — dry-run fallaba con conexiones no registradas:** Al mover la validación de `config/database.php` dentro de `resolveExecutionConnection()`, el check se ejecutaba siempre. Los tests de dry-run fallaban porque en el entorno de test no hay conexiones configuradas.

**Problema 4 — Bug de PendingCommand en tests:** Dos `expectsOutputToContain` consecutivos sobre la misma línea de output: PendingCommand consume la línea completa al hacer match con el primer substring, dejando sin datos para el segundo assertion. Detectado con: orden inverso también falla (el primero pasa, el segundo falla siempre).

**Flujo post-R03.1 en `resolveExecutionConnection()`:**
```
formato inválido → null
contexto no encontrado → null
tenancy_strategy ≠ 'manual' → null
connection_key vacío → null
!dryRun && conexión no en config/database.php → null
→ return $connectionKey
```

**Cambios aplicados:**
- `src/Commands/MigratePlanCommand.php`:
  - `resolveExecutionConnection()`: retorno `string` → `?string`; añadidas validaciones de formato, existencia de contexto, `tenancy_strategy === 'manual'`, `connection_key` vacío, y check de `config/database.php` condicionado a `!$dryRun`; eliminado fallback a `config('database.default')`
  - Bloque Guard Rail R03 en `handle()` eliminado (consolidado en el método)
  - Añadido: `if ($connectionName === null) return self::FAILURE;`
  - Firma: `resolveExecutionConnection(string, array, array, bool $dryRun = false): ?string`
- `tests/Feature/ConnectionValidationCommandTest.php`:
  - Assertion dual `expectsOutputToContain('tenant_one')` + `expectsOutputToContain('tenant-one')` → una sola: `expectsOutputToContain("'tenant_one' del contexto 'tenant-one'")`

**Invariante arquitectónico:** El check de `config/database.php` no aplica en dry-run. El dry-run sí valida formato, contexto, tenancy_strategy y connection_key (no necesitas conexión real pero sí configuración coherente).

---

## [2026-04-05] R03 — Normalización de Manifests + Guard Rail de Conexiones

**Problema 1 — Naming inconsistente:** `central_order.json` (guión bajo) era el nombre legacy del manifest central, mientras que los tenants ya usaban `{id}.order.json`. Se normalizó a `{id}.order.json` para todos los contextos: `central` → `central.order.json`.

**Problema 2 — Resolución de conexión heurística:** `resolveExecutionConnection()` usaba heurísticas frágiles (`str_starts_with('tenant_')`, detección de `:tenant/` en coordenadas) en lugar de leer `connection_key` directamente de `contexts.json`.

**Problema 3 — Guard Rail faltante:** No había validación de que `connection_key` existiera en `config/database.php` antes de ejecutar migraciones. El error llegaba como excepción PDO genérica.

**Flujo post-R03:**
```
{id}.order.json → ContextResolver::find($id) → connection_key → validar → ejecutar
```

**Cambios aplicados:**
- `src/Exceptions/ConnectionNotConfiguredException.php`: nueva excepción con `forContext(string $contextId, string $connectionKey): self`
- `src/Support/ContextResolver.php`: nuevo método `validateConnection(string $id): void`
- `src/Services/MigrationPlanResolver.php`: default manifest `central_order.json` → `central.order.json`
- `src/Services/MigrationTargetService.php`: renombre + reemplazo de `resolveExecutionConnection()` con lookup explícito vía `ContextResolver::find()`
- `src/Commands/MigrationSyncCommand.php`: `central_order.json` → `central.order.json`
- `src/Commands/MigratePlanCommand.php`: guard rail R03 + `resolveExecutionConnection()` explícito
- `src/Commands/MigrateOneCommand.php`: guard rail R03
- `src/Commands/SeedOneCommand.php`: guard rail R03
- Tests actualizados: `central_order.json` → `central.order.json` en todos los test files; `MigratePlanCommandTest` test 3 actualizado a `tenant-one.order.json` + conexión `tenant_one`; test 4 actualizado a conexión `central`
- `tests/Feature/ConnectionValidationCommandTest.php`: 3 nuevos tests de regresión R03

**Invariante arquitectónico:** El patrón regex `/^([a-z0-9][a-z0-9-]*)\.order\.json$/` identifica manifests explícitos. Custom manifests con nombre arbitrario (ej: `--manifest legacy.json`) ignoran el guard y usan `config('database.default')`.

---

## [2026-04-05] R01 — Fix: migración `name` → `id` y variables indefinidas

**Síntoma:** 5 tests en `MakeModuleCommandTest` fallaban con exit code 1 al usar `--context=central --no-routes`.

**Causa raíz (compuesta):**

1. **`ModuleGenerator::createCleanModuleWithContext()`**: El parámetro se llamaba `$contextName` pero el cuerpo usaba `$contextId` (indefinido). En el entorno de tests, la variable indefinida genera un `Warning` que es promovido a `ErrorException`, propagándose como `Throwable` y causando `Command::FAILURE`.

2. **`MakeModuleCommand`**: Las references a `contextName:` en la llamada `inject()` de `RouteInjectionService` eran incorrectas (el parámetro real es `contextId:`). El key `'context_name'` en `ModuleAuditor::log()` y en `componentConfig` debía ser `'context_id'`.

3. **`RouteGenerator`**: Usaba `$context['name']` (campo eliminado) y `context_name` en componentConfig — debía usar `$context['id']` y `context_id`.

4. **`tests/TestCase.php`**: Faltaba `ContextResolver::flush()` en `tearDown()`, permitiendo contaminación del caché estático entre tests.

**Correcciones aplicadas:**
- `src/Generators/Components/ModuleGenerator.php`: renombrado parámetro `$contextName` → `$contextId`, clave `context_name` → `context_id` en `$componentConfig`
- `src/Commands/MakeModuleCommand.php`: eliminado alias temporal `$contextName`, corregido `contextName:` → `contextId:` en llamada `inject()`, corregidas firmas de `displayConfigTable()` y `displaySuccess()`, corregido key de `ModuleAuditor::log()`
- `src/Generators/Components/RouteGenerator.php`: `$context['name']` → `$context['id']`, `context_name` → `context_id`
- `src/Generators/Components/AbstractComponentGenerator.php`: actualizado comentario
- `tests/TestCase.php`: añadido `ContextResolver::flush()` en `tearDown()`

**Invariante arquitectónico preservado:** `class_prefix` (ej: `'Central'`, `'TenantOne'`) sigue rigiendo nombres de clases PHP. `id` (ej: `'central'`, `'tenant-one'`) rige nombres de archivos `.json` y argumentos CLI.
