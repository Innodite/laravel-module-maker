```bash
php artisan innodite:deploy {entorno} {--context=} {--module=} {--tenant=} {--all} {--force} {--dry-run}
```

### Qué hace

Ejecuta los seeders del proyecto en el orden declarado en `config/make-module.php` (`deploy`):
esquema, datos canónicos, permisos, y el usuario administrador al cerrar.

En `multitenant`, cada `--context=tenant` tiene que decir a qué inquilino entra (`--tenant=` o
`--all`): en consola no hay middleware que identifique el tenant por dominio, así que el comando lo
recibe explícito.

### Parámetros

| Parámetro | Valores | Efecto |
|---|---|---|
| `entorno` | `stage` \| `production` | Obligatorio. `stage` puede reconstruir desde cero; `production` no |
| `--context=` | `central` \| `tenant` \| el que declare el proyecto | Obligatorio en `multitenant` |
| `--tenant=` | id del inquilino | Con `--context=tenant`: entra a ese inquilino |
| `--all` | — | Con `--context=tenant`: entra a todos los inquilinos, uno por uno |
| `--module=` | nombre del módulo | Levanta solo ese módulo, por sus maestros, en vez del proyecto entero |
| `--force` | — | No pide confirmación aunque `SEEDER_DESTRUCTIVE` esté activo |
| `--dry-run` | — | Muestra qué ejecutaría y contra qué conexión, sin tocar la base |

### Qué genera

No genera archivos. Escribe en la base de datos: aplica el esquema pendiente, inserta o actualiza
los datos canónicos (los seeders son no destructivos por defecto) y crea los permisos y el usuario
administrador si faltan.

### Ejemplos

**Caso: aplicación única, ambiente de stage.**
```bash
php artisan innodite:deploy stage
```

**Caso: multiinquilino, la aplicación central.**
```bash
php artisan innodite:deploy stage --context=central
```

**Caso: multiinquilino, un solo cliente identificado por su id.**
```bash
php artisan innodite:deploy stage --context=tenant --tenant=acme
```

**Caso: multiinquilino, todos los clientes de una sola vez.**
```bash
php artisan innodite:deploy stage --context=tenant --all
```

**Caso: solo un módulo, sin levantar el resto del proyecto.**
```bash
php artisan innodite:deploy stage --module=Invoice
```

**Caso: antes de tocar producción, ver qué haría.**
```bash
php artisan innodite:deploy production --dry-run
```
