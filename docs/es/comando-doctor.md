```bash
php artisan innodite:doctor
```

### Qué hace

Diagnóstico en cascada, en tres etapas. Se detiene en la primera etapa que falla, salvo que se pida
lo contrario.

**Etapa 1 · El entorno del generador**
1. El modo del proyecto (`contexts.json` existe y es válido)
2. Permisos de escritura en `Modules/` y `storage/logs/`
3. Colisiones de nombres entre módulos y comandos del proyecto
4. `config/make-module.php` publicada
5. Stubs publicados en formato de una versión anterior
6. Últimas entradas del log de eventos
7. **Desde la 5.1.0:** cada módulo, buscando la carpeta `Tests/Feature/Shared/` o una pieza de
   `Central`/`Tenant` que incorpore un trait con `use <Trait>` en vez de llevar el código inline

**Etapa 2 · El contrato del proyecto anfitrión**
1. Modelo `User` — `HasRoles` (Spatie) o `InnoditeUserPermissions`
2. `HandleInertiaRequests` comparte `auth.permissions`
3. `InnoditeContextBridge` registrado en el grupo `web`

**Etapa 3 · Lo que dice el criterio**
Consulta al proveedor de reglas configurado y lista sus hallazgos.

### Parámetros

| Parámetro | Efecto |
|---|---|
| `--continuar` | No corta en la primera etapa que falla; ejecuta las tres y reporta todo |

### Qué genera

No escribe ni modifica nada. Es de solo lectura — imprime en consola cada hallazgo con la línea
exacta para corregirlo.

### Ejemplos

**Caso: primera verificación tras instalar el paquete.**
```bash
php artisan innodite:doctor
```

**Caso: se sabe que el entorno está mal, y se quiere ver también el resto de las etapas.**
```bash
php artisan innodite:doctor --continuar
```
