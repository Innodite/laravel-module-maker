```bash
php artisan innodite:deploy {environment} {--context=} {--module=} {--tenant=} {--all} {--force} {--dry-run}
```

### Qué hace

Ejecuta los seeders del proyecto en el orden declarado en `config('make-module.deploy')`: esquema,
datos canónicos, permisos, y el usuario administrador al cerrar. El orden es un array asociativo
`contexto → lista de rutas Módulo/Contexto/Subfuncionalidad` (o una lista plana en `single-app`).

Antes de desplegar, comprueba que existan `cache`/`jobs` si el proyecto las usa como driver de caché
o colas (pide `php artisan migrate` primero si faltan).

### Parámetros

| Parámetro | Valores | Efecto |
|---|---|---|
| `environment` | `stage` \| `production` | Obligatorio. En el código, ambos ejecutan exactamente el mismo camino — la diferencia real vive en los seeders generados, no en este comando (ver abajo) |
| `--context=` | `central` \| `tenant` \| el que declare el proyecto | Obligatorio en `multitenant`; **prohibido** en `single-app` — si se pasa, falla |
| `--module=` | nombre del módulo | Despliega solo las subfuncionalidades de ese módulo (filtra por módulo y, si se pasó, por contexto) |
| `--tenant=` | id del inquilino | Con `--context=tenant`: despliega solo ese inquilino |
| `--all` | — | Con `--context=tenant`: despliega todos, uno tras otro |
| `--force` | — | Omite la confirmación aunque `SEEDER_DESTRUCTIVE` esté activo |
| `--dry-run` | — | No ejecuta ningún seeder — registra qué haría y contra qué conexión |

**Combinaciones que fallan:**
- `single-app` + `--context=`: "el modo no tiene contextos: no pases --context=…"
- `multitenant` sin `--context=`: "falta --context: aquí hay dos despliegues, contra dos bases distintas"
- `--context=tenant` sin `--tenant=` ni `--all`: "falta elegir el tenant: aquí hay una base por cliente"
- `--tenant=` y `--all` juntos: "--tenant y --all piden cosas distintas"
- `--module=` de un módulo sin ninguna subfuncionalidad en el contexto pedido: falla nombrando
  módulo y contexto

### Qué genera

No genera archivos. Escribe en la base de datos: aplica el esquema pendiente, inserta o actualiza
los datos canónicos, y crea permisos y el usuario administrador si faltan.

### El mecanismo real de `SEEDER_DESTRUCTIVE`

Es una variable de entorno (`env('SEEDER_DESTRUCTIVE')`), leída en dos sitios distintos:

- **En este comando** (`confirmDestructive()`): decide si pide confirmación antes de desplegar —
  sin condicionarlo a si el entorno es `stage` o `production`.
- **En el seeder de Stage generado** (trait `ResolvesSeederDestructiveMode`): sin la variable, se
  comporta igual que producción (`upsertCanonicalData`, no borra nada); con ella, trunca las tablas
  declaradas y siembra desde cero.

⚠️ **El seeder de Production generado nunca incorpora ese trait.** Si se exporta
`SEEDER_DESTRUCTIVE=true` y se corre `innodite:deploy production`, el comando igual muestra la
advertencia y pide confirmación (o exige `--force`) — pero el seeder de producción no trunca nada,
porque no tiene el mecanismo. El aviso del comando es agnóstico de la pieza; el efecto real solo
existe en Stage.

### Aplicación única

```bash
php artisan innodite:deploy stage
```
Sin `--context`. `config('make-module.deploy')` es una lista plana de rutas
(`Módulo/Subfuncionalidad`), sin distinguir contexto.

### Multiinquilino

```bash
php artisan innodite:deploy stage --context=central
php artisan innodite:deploy stage --context=tenant --tenant=acme
php artisan innodite:deploy stage --context=tenant --all
```
`deploy` es un array con una clave por contexto (`central`, `tenant`), cada una con su propia lista
de rutas. En consola no hay middleware que identifique el tenant por dominio — por eso
`--context=tenant` exige decir explícitamente cuál (`--tenant=`) o todos (`--all`).

### Ejemplos

**Caso: aplicación única, ambiente de stage.**
```bash
php artisan innodite:deploy stage
```

**Caso: multiinquilino, la aplicación central.**
```bash
php artisan innodite:deploy stage --context=central
```

**Caso: multiinquilino, un solo cliente.**
```bash
php artisan innodite:deploy stage --context=tenant --tenant=acme
```

**Caso: multiinquilino, todos los clientes de una vez.**
```bash
php artisan innodite:deploy stage --context=tenant --all
```

**Caso: solo un módulo, sin levantar el resto del proyecto.**
```bash
php artisan innodite:deploy stage --module=Invoice --context=central
```

**Caso: antes de tocar producción, ver qué haría.**
```bash
php artisan innodite:deploy production --context=central --dry-run
```
```
ENSAYO (--dry-run): no se va a escribir ni a ejecutar nada.
(ensayo) ejecutaría CentralInvoiceApplicationProductionSeeder contra la conexión 'central'
(ensayo) ejecutaría CentralUserManagementApplicationProductionSeeder contra la conexión 'central'
Esto es lo que haría (2). No se tocó la base de datos.
```
