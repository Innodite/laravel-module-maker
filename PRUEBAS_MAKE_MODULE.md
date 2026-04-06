# Reporte de Pruebas — Comandos `innodite:*`

> Fecha: 2026-04-06
> Rama: `refactor/full-project-migration`
> Ejecutado con: `./vendor/bin/sail artisan`
> Módulos de prueba usados: `TestModule` (central), `TestShared` (shared), `TestTenantShared` (tenant_shared), `TestEnergySpain` (energy-spain)

---

## 1. `innodite:make-module` — Resultados por contexto

| Contexto | Módulo completo | -M | -C | -S | -R | -G | -Q | Flags combinados | --no-routes | --json |
|---|---|---|---|---|---|---|---|---|---|---|
| `central` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `shared` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | — |
| `tenant_shared` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | — |
| `energy-spain` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | — |

---

## 2. Resto de comandos — Resultados

| Comando | Estado | Notas |
|---|---|---|
| `innodite:module-setup` | — | No probado (modificaría archivos del proyecto) |
| `innodite:migrate-plan` | ✅ | Funciona con `--dry-run` en `central` y `energy-spain` |
| `innodite:migrate-one` | ✅ | Resuelve coordenadas correctamente con `--dry-run` |
| `innodite:seed-one` | ✅ | Funciona con `--dry-run` |
| `innodite:migration-sync` | ✅ | Detecta módulos nuevos correctamente con `--dry-run` y `--all-manifests` |
| `innodite:check-env` | ⚠️ | Funciona pero falla falso positivo en User model (ver ERROR-003) |
| `innodite:module-check` | ❌ | Crash con `TypeError` en todos los contextos (ver ERROR-001) |
| `innodite:test-module` | ✅ | Funciona en `central`, `shared`, `tenant_shared` y `energy-spain` |
| `innodite:test-sync` | ⚠️ | Sin args falla — requiere módulo o `--all` (ver ERROR-002) |
| `innodite:migrate-modules` | ❌ | Comando no existe en la versión instalada (ver ERROR-004) |
| `innodite:publish-frontend` | ✅ | Publica composables correctamente |

---

## 3. Errores confirmados

### ERROR-001 — `innodite:module-check` crashea con `TypeError` ❌

**Severidad:** Crítica — el comando no funciona en absoluto
**Reproducción:** `innodite:module-check` (cualquier contexto)

**Error:**
```
TypeError
array_key_exists(): Argument #2 ($array) must be of type array, string given

at vendor/innodite/laravel-module-maker/src/Commands/ModuleCheckCommand.php:106
    foreach ($items as $i => $item) {
        foreach ($requiredItemKeys as $k) {
➜          if (!array_key_exists($k, $item)) {   // $item es string, no array
```

**Causa:** El comando itera los contextos del `contexts.json` esperando que todos sean arrays de arrays. Los contextos `central`, `shared` y `tenant_shared` son objetos directos (estructura híbrida), no arrays de items. Al iterar con `foreach ($items as $i => $item)`, `$item` es el valor de cada clave del objeto (un string), no un sub-array.

**Fix:** En `ModuleCheckCommand.php:104`, envolver los contextos objeto en un array antes de iterar, igual que hace `ContextResolver::allItems()`.

---

### ERROR-002 — `innodite:test-sync` requiere argumento no documentado ⚠️

**Severidad:** Media — discrepancia entre documentación y comportamiento real
**Reproducción:** `innodite:test-sync` (sin argumentos)

**Error:**
```
ERROR  Debes indicar un módulo o usar --all.
```

**Causa:** La documentación del skill dice que el comando no toma argumentos. En realidad requiere un nombre de módulo o `--all`. Con `--all` o con nombre de módulo funciona correctamente y sincroniza los `test-config.json`.

**Fix:** Actualizar la firma del comando en `SKILL.md`:
```bash
php artisan innodite:test-sync {module?} {--all}
```

---

### ERROR-003 — `innodite:check-env` no encuentra el modelo User ⚠️

**Severidad:** Media — falso positivo, el modelo existe pero en ruta no estándar
**Reproducción:** `innodite:check-env`

**Error reportado:**
```
ERROR  No se encontró el modelo User en app/Models/User.php ni app/User.php.
```

**Causa:** El comando solo busca en `app/Models/User.php` y `app/User.php`. El modelo en este proyecto está en:
- `Modules/UserManagement/Models/Central/CentralUser.php`
- `Modules/UserManagement/Models/Tenant/Shared/TenantSharedUser.php`

**Fix:** Ampliar la búsqueda del comando a `Modules/*/Models/**/*User*.php`.

---

### ERROR-004 — `innodite:migrate-modules` no existe ❌

**Severidad:** Media — comando documentado pero no registrado en el paquete instalado
**Reproducción:** `innodite:migrate-modules --dry-run`

**Error:**
```
ERROR  Command "innodite:migrate-modules" is not defined.
```

**Fix:** Eliminar de la documentación del skill o volver a registrar el comando en el `ServiceProvider`.

---

### ERROR-005 — Contaminación progresiva de stdout en todos los comandos ⚠️

**Severidad:** Alta — afecta todos los comandos artisan, empeora con cada módulo creado

**Descripción:**
Al bootear la aplicación, se imprimen en stdout los bloques de rutas de los módulos que están en `routes/tenant.php`. Cada módulo nuevo creado en `energy-spain` o `tenant_shared` agrega un bloque adicional al ruido. Con 2 módulos de prueba ya se imprimían **2 bloques**; con los 4 módulos de prueba se imprimían **4 bloques**.

**Ejemplo:**
```
// ────────────────────────────────────────────────────────────
// energy-spain
// ────────────────────────────────────────────────────────────
Route::middleware([])->group(function () {
    ...
    // {{TENANT_ENERGY_SPAIN_ROUTES_END}}
});
```

**Causa probable:** El archivo `routes/tenant.php` probablemente no tiene `<?php` al inicio en alguna sección, o el stub genera el bloque con un `echo`/`print` implícito. El `ServiceProvider` del módulo podría estar incluyendo el archivo de rutas de forma que ejecuta `echo` accidentalmente.

**Fix:** Revisar `routes/tenant.php` — verificar que todos los bloques inyectados estén dentro de `<?php` y que no haya salida de texto antes del tag de apertura.

---

### ERROR-006 — `-G` repetido genera migraciones duplicadas ⚠️

**Severidad:** Media — genera archivos duplicados que romperían la DB al migrar
**Reproducción:** Ejecutar `-G` más de una vez sobre el mismo módulo

**Descripción:**
Cada llamada con `-G` genera una nueva migración con timestamp diferente:
```
2026_04_06_130355128796_create_test_modules_table.php
2026_04_06_130438507450_create_test_modules_table.php
```
Ambas crean la misma tabla. No hay validación de idempotencia para `-G`.

**Fix:** Antes de generar, verificar si ya existe un archivo de migración para ese módulo+contexto. Si existe, advertir y no generar.

---

## 4. Observaciones

### OBS-001 — `tenant_shared` no genera `test-config.json` automáticamente

Al crear un módulo con `--context=tenant_shared`, el archivo `test-config.json` no se genera. Esto causa que `innodite:test-module` marque el módulo como `SKIPPED`. La solución es correr `innodite:test-sync {module}` después de crear el módulo. Los demás contextos sí lo generan automáticamente.

### OBS-002 — `--no-routes` muestra warning de marcador en fase "Creando estructura"

Aunque "Inyectar rutas: No" aparece en la tabla resumen, el warning del marcador faltante igualmente se muestra durante la fase de creación. No afecta el resultado.

### OBS-003 — Flag `--json` requiere archivo previo

Si `module-maker-config/{module}.json` no existe, el comando falla con error claro. Comportamiento correcto y documentado.

---

## 5. Resumen ejecutivo

| Categoría | Cantidad |
|---|---|
| Comandos que funcionan correctamente | 7 |
| Comandos con errores/advertencias | 4 |
| Errores críticos (comando no funciona) | 2 |
| Errores medios (funcionalidad parcial) | 4 |
| Observaciones menores | 3 |
