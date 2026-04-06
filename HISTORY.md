# History Log

---

## [2026-04-06] v3.5.3 — Fix: ModelGenerator y RequestGenerator usan buildPath/buildNamespace

**Tag:** `v3.5.3`

**Problema:** `ModelGenerator` y `RequestGenerator` tenían lógica de construcción de rutas/namespaces hardcodeada que ignoraba `buildPath()` y `buildNamespace()` del `AbstractComponentGenerator`. Como resultado, los archivos generados no seguían el patrón R05 (`{Tipo}/{Contexto}/{Entidad}/`):
- `ModelGenerator` generaba `Models/EntityName.php` (sin contexto ni entidad)
- `RequestGenerator` generaba `Http/Requests/Central/CentralModuleNameStoreRequest.php` (sin subcarpeta de entidad, nombre con moduleName en lugar de entityName)

**Correcciones:**
- `ModelGenerator::generate()`: usa `buildPath('Models')` para el directorio; `$className = $prefix . $modelName`; pasa `$className` al stub como `modelName`
- `ModelGenerator::getNamespace()`: delega a `buildNamespace('Models')`
- `RequestGenerator::generate()`: usa `buildPath('Http/Requests')` para el directorio; lee `entity` de `componentConfig['entity'] ?? moduleName` para construir `$className`; construye `$contextNamespace` del stub como `{ctxNs}\\{entityNs}`

**Resultado verificado en neocenter v3.5.3:**
- `Models/Central/TestR053/CentralTestR053.php` ✅
- `Http/Requests/Central/TestR053/CentralTestR053StoreRequest.php` ✅
- `add-entity TestR053 Role --context=central` → `Models/Central/Role/CentralRole.php` ✅

---

## [2026-04-06] v3.5.0 — R05+R06: Subfolder por entidad + comando `innodite:add-entity`

**Tag:** `v3.5.0`

**Motivación:** Los módulos con múltiples entidades (ej. `UserManagement` con `User`, `Role`, `Permission`, `Module`) mezclaban todos los archivos en la misma carpeta de contexto. Se introduce una subcarpeta por entidad para mantener el módulo limpio y escalable.

**R05 — Refactor estructural: subfolder por entidad en todos los generadores**
- `AbstractComponentGenerator::buildPath()` — añade `/{Entity}` al final de la ruta
- `AbstractComponentGenerator::buildNamespace()` — añade `\{Entity}` al namespace
- `AbstractComponentGenerator::buildContractsPath()` — ídem para Contracts
- `AbstractComponentGenerator::buildContractsNamespace()` — ídem para Contracts
- Nuevo método `getEntityFolder()` que lee `componentConfig['entity']`
- `ModuleGenerator::createCleanModuleWithContext()` — añade `'entity' => $modelName` al componentConfig
- `ModuleGenerator::createDynamicModule()` — añade `'entity' => $modelName` en cada componente
- `ModuleGenerator::createIndividualComponents()` — acepta `?string $entityName = null`, propaga al componentConfig
- `ModuleGenerator::injectRoutes()` — usa `componentConfig['entity']` para el namespace del controlador
- `MakeModuleCommand::buildControllerFqcn()` — acepta `?string $entityName = null`, incluye entity en namespace

**Patrón nuevo:** `{Tipo}/{Contexto}/{Entidad}/{Archivo}` — naming convention intacta.

**R06 — Nuevo comando `innodite:add-entity`**
- Nuevo archivo `src/Commands/AddEntityCommand.php`
- Firma: `innodite:add-entity {module} {entity} {--context=} [-M] [-C] [-S] [-R] [-G] [-Q] [--no-routes]`
- Valida que el módulo exista antes de generar
- Sin flags activos: genera todos los componentes
- Registrado en `LaravelModuleMakerServiceProvider`
- Documentado en `skills/module-maker.md`

**Caso de uso:** `php artisan innodite:add-entity UserManagement Role --context=central` genera `Models/Central/Role/CentralRole.php`, `Http/Controllers/Central/Role/CentralRoleController.php`, etc.

**Retrocompatibilidad:** Si `componentConfig['entity']` no está definido (legacy), `getEntityFolder()` retorna cadena vacía y los paths se comportan exactamente igual que antes.

---

## [2026-04-06] v3.4.6 — Corrección de 6 errores detectados en pruebas manuales

**Tag:** `v3.4.6`

**Errores corregidos:**

- **ERROR-001 (CRÍTICO)** `innodite:module-check` — TypeError en `ModuleCheckCommand::checkContextsJson()`. El loop asumía estructura indexada para todos los contextos. Fix: detectar contextos objeto único (`isset($items['id'])`) y envolver en array antes de iterar. También corregido el conteo de contextos (`$count`).
- **ERROR-002** `innodite:test-sync` — Documentación incorrecta en `skills/module-maker.md`. La firma real es `{module?} {--all}`, no sin argumentos.
- **ERROR-003** `innodite:check-env` — Falso positivo al buscar modelo User. `findUserModel()` ahora busca también en `Modules/*/Models/**/*User*.php`.
- **ERROR-004** `innodite:migrate-modules` — Comando inexistente. Se eliminó `MigrateModulesCommand.php` y sus referencias en `skills/module-maker.md`.
- **ERROR-005** Contaminación de stdout al bootear. `RouteInjectionService::appendTenantSection()` ahora elimina `?>` al final del archivo antes de inyectar el bloque tenant, evitando que PHP salga de modo PHP y emita output.
- **ERROR-006** `-G` repetido generaba migraciones duplicadas. `MigrationGenerator::generate()` ahora hace `glob()` para detectar migración existente y retorna con warning si ya existe.

**Archivos modificados:**
- `src/Commands/ModuleCheckCommand.php`
- `src/Commands/CheckEnvCommand.php`
- `src/Commands/MigrateModulesCommand.php` (eliminado)
- `src/Services/RouteInjectionService.php`
- `src/Generators/Components/MigrationGenerator.php`
- `skills/module-maker.md`
- `PRUEBAS_MAKE_MODULE.md` (nuevo — reporte de pruebas)

---

## [2026-04-05] R03.3 — Tag v3.4.2 publicado + Deploy pendiente en Neocenter

**Estado:** En progreso — requiere reinicio de equipo para continuar.

**Completado:**
- Tag `v3.4.2` creado y pusheado a GitHub (`git tag v3.4.2 && git push origin main && git push origin v3.4.2`)
- `composer.json` y `composer.lock` en neocenter actualizados a `v3.4.2` via `composer require innodite/laravel-module-maker:v3.4.2 --ignore-platform-reqs`

**Pendiente (bloqueado por reinicio):**
- Actualizar vendor en neocenter: la extracción falló por archivos con permisos root en `vendor/innodite/laravel-module-maker/`. Solución: usar `./vendor/bin/sail composer install` dentro del contenedor Docker (Sail), que tiene permisos correctos.
- Actualizar `module-maker-config/contexts.json` en neocenter: agregar `tenancy_strategy: "manual"` a los contextos con `connection_key`
- Verificar `config/database.php` vs `contexts.json`
- Verificar naming de manifests (`{id}.order.json`)
- Probar todos los comandos: `migrate-plan`, `migration-sync`, `migrate-one`, `seed-one`

**Nota técnica — permisos vendor:**
Los archivos en `vendor/innodite/laravel-module-maker/` son propiedad de root en WSL. La solución es usar Sail (`./vendor/bin/sail composer install`) que ejecuta dentro del contenedor Docker con el usuario `sail` que tiene acceso correcto. El lock file ya está actualizado a v3.4.2.

**Plan guardado en:** `C:\Users\Anthony Filgueira\.claude\plans\glittery-jingling-hoare.md`

---

## [2026-04-05] R03.2 — Consolidar `resolveExecutionConnection` en `MigrationTargetService`

**Problema:** La lógica de resolución de conexión estaba triplicada:
1. `MigratePlanCommand` tenía un método privado `resolveExecutionConnection()` con la lógica completa (R03.1)
2. `MigrationTargetService::resolveExecutionConnection()` tenía la lógica antigua con fallback silencioso a `config('database.default')`
3. `MigrateOneCommand` y `SeedOneCommand` tenían el Guard Rail R03 como bloque separado en `handle()`, sin validar `tenancy_strategy`

**Decisión:** Un solo método en el servicio. Los comandos no repiten lógica — solo llaman y atrapan excepciones.

**Flujo final en `MigrationTargetService::resolveExecutionConnection(string $manifestPath, bool $dryRun = false): string`:**
```
formato inválido          → throw InvalidArgumentException
contexto no encontrado    → throw InvalidArgumentException
tenancy_strategy ≠ manual → throw InvalidArgumentException
connection_key vacío      → throw InvalidArgumentException
!dryRun && conexión no en config/database.php → throw ConnectionNotConfiguredException
→ return $connectionKey
```

**Cambios aplicados:**
- `src/Services/MigrationTargetService.php`: firma `(string, array, array): string` → `(string, bool): string`; lanza excepciones en lugar de retornar fallback; import de `ConnectionNotConfiguredException`
- `src/Commands/MigratePlanCommand.php`: eliminado método privado `resolveExecutionConnection()`; eliminado import `ContextResolver`; usa `$targetService->resolveExecutionConnection($manifestPath, $dryRun)` en try/catch
- `src/Commands/MigrateOneCommand.php`: eliminados imports `ConnectionNotConfiguredException` y `ContextResolver`; eliminado bloque Guard Rail R03; llamada actualizada a `resolveExecutionConnection($manifestPath, $dryRun)` en try/catch
- `src/Commands/SeedOneCommand.php`: ídem que MigrateOneCommand
- `src/Exceptions/ConnectionNotConfiguredException.php`: mensaje de `forContext()` normalizado al canónico ("del contexto", "no existe", "Créala manualmente o ejecuta innodite:make-connections")
- `tests/Feature/SeedOneCommandTest.php`: manifest legacy `tenant_alpha_order.json` → `tenant-one.order.json`; coordenada y seeder actualizados al naming v3.5.0

**Resultado:** −54 líneas netas. 28 passing, 1 skipped (pdo_sqlite).

**Invariante:** El único lugar donde vive la lógica de validación de conexión es `MigrationTargetService::resolveExecutionConnection()`. Cualquier nuevo comando que acceda a BD debe usar este método.

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
