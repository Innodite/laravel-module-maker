```bash
php artisan innodite:doctor {--continuar}
```

### Qué hace

Diagnóstico en **dos** etapas. Corta en la primera que falla, salvo `--continuar`.

**Etapa 1 · El entorno del generador** — 8 comprobaciones, en este orden:
- El modo del proyecto está configurado (`contexts.json` existe y es válido)
- `contexts.json` declara los contextos que el modo exige (en `single-app`, ninguno; en `multitenant`, `central` y `tenant`), y cada uno tiene las 4 claves mínimas (`id`, `class_prefix`, `folder`, `namespace_path`)
- Permisos de escritura en `Modules/` y `storage/logs/`
- Colisiones: directorios duplicados, `ServiceProvider` con namespace incorrecto o que no carga, migraciones duplicadas por contexto, comandos del proyecto que tapan a los del paquete
- **Estructura de pruebas retirada** — cada módulo, buscando `Tests/Feature/Shared/` o una pieza de contexto que incorpore un trait con `use <Trait>` en vez de llevar el código inline
- `config/make-module.php` publicada
- Stubs publicados en formato de una versión anterior (doble llave `{{ }}` sin ninguna triple `{{{ }}}`)
- Últimas 5 entradas del log de eventos (`storage/logs/module_maker.log`) — informativo, nunca hace fallar el diagnóstico

**Etapa 2 · El contrato del proyecto anfitrión** — 5 comprobaciones:
- Modelo `User` — `HasRoles` (Spatie) o `InnoditeUserPermissions`
- `HandleInertiaRequests` comparte `auth.permissions`
- `InnoditeContextBridge` registrado en el grupo `web` — **solo obligatorio en `multitenant`**; en `single-app` se informa como no aplicable, nunca falla por esto
- Ziggy instalado, con `@routes` en algún layout Blade
- Si el proyecto declara `frontend: innodite`, que algún paquete instalado aporte esas plantillas

### Parámetros

| Parámetro | Efecto |
|---|---|
| `--continuar` | Ejecuta la Etapa 2 aunque la Etapa 1 haya fallado. El resultado final es éxito solo si ambas etapas pasan |

Sin `--continuar`, un fallo en la Etapa 1 corta ahí mismo — la Etapa 2 no llega a ejecutarse.

### Qué genera

No escribe ni modifica nada. Es de solo lectura — imprime en consola cada hallazgo con la línea
exacta para corregirlo.

### Aplicación única

Los dos únicos puntos que cambian: el paso 2 de la Etapa 1 no exige ninguna clave en `contexts.json`
(no hay contextos que declarar), y el paso 3 de la Etapa 2 (`InnoditeContextBridge`) nunca es
obligatorio. El resto de los 11 chequeos son idénticos en ambos modos.

### Multiinquilino

`contexts.json` debe declarar `central` y `tenant`; el `InnoditeContextBridge` es obligatorio y su
ausencia hace fallar la Etapa 2.

### Ejemplos

**Caso: primera verificación tras instalar el paquete.**
```bash
php artisan innodite:doctor
```
Si el modo no está configurado, imprime únicamente el fallo de la Etapa 1 y corta:
```
FALLA: no hay un modo configurado para este proyecto.
FIX: php artisan innodite:module-setup --mode=single-app|multitenant
```

**Caso: el entorno está mal, y se quiere ver también el contrato del proyecto.**
```bash
php artisan innodite:doctor --continuar
```
Imprime el resultado de las 8 comprobaciones de la Etapa 1 (marcando cuáles fallan) y, a
continuación, las 5 de la Etapa 2, sin detenerse en la primera.
