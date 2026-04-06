# Roadmap de Desarrollo - Fase: Conectividad y Sincronización

## ✅ Prioridad 1: Core & Identidad (R01) — COMPLETADO [2026-04-05]
- **Actividad:** Refactorizar `ContextResolver.php` y estabilizar `MakeModuleCommand`.
- **Estado:** `ContextResolver` ya implementaba búsqueda híbrida correctamente. Bug real encontrado y corregido en `MakeModuleCommand`: variable `$contextName` indefinida causaba `TypeError` → `Command::FAILURE` en todos los tests de generación. Fix: `$contextName = $contextId` en `handleFullModule()` y `handleComponentMode()`.

## ✅ Prioridad 2: Sincronización de Orden (R02) — COMPLETADO [2026-04-05]
- **Actividad:** Actualizar `MigrationSyncCommand.php`.
- **Estado:** Ya implementado y funcional. `resolveAutoTargets()` lee `$decoded['contexts']['tenant']` (array indexado) e itera correctamente generando `{id}.order.json` por cada tenant. Los 3 tests de `MigrationSyncCommandTest` pasan.

## ✅ Prioridad 3: Validación Preventiva de Seguridad (R03) — COMPLETADO [2026-04-05]
- **Actividad:** Normalización de manifests + Guard Rail de conexiones.
- **Estado:** `central_order.json` renombrado a `central.order.json`. `resolveExecutionConnection()` reemplazado con lookup explícito vía `ContextResolver::find()`. Guard Rail integrado en `MigratePlanCommand`, `MigrateOneCommand`, `SeedOneCommand`. Nueva excepción `ConnectionNotConfiguredException`. 3 tests de regresión añadidos.

## ✅ Prioridad 3.1: Refactor Guard + Fix dry-run (R03.1) — COMPLETADO [2026-04-05]
- **Actividad:** Consolidar Guard Rail en `resolveExecutionConnection()`, eliminar fallback a `database.default`, soportar dry-run correctamente, corregir tests PendingCommand.
- **Estado:** `resolveExecutionConnection()` retorna `?string`; valida formato → contexto → `tenancy_strategy='manual'` → `connection_key` → config/database.php (solo `!$dryRun`). Bug de PendingCommand documentado y corregido en tests. 28 passing, 1 skipped.

## 🟡 Prioridad 4: DX - Generador de Conexiones (R04)
- **Actividad:** Nuevo comando `innodite:make-connections`.
- **Detalle:** - Analizar el JSON y detectar conexiones faltantes en `config/database.php`.
    - Inyectar el bloque de código (Stub) al final del array de conexiones con las variables `env()` correspondientes.
    - Sugerir en consola las líneas para el archivo `.env`.