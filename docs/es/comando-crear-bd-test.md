```bash
php artisan innodite:crear-bd-test {--connection=} {--force} {--dry-run}
```

### Qué hace

Copia el **esquema** de una conexión real a su base de pruebas — sin copiar ni una fila. Soporta
MySQL, MariaDB y SQLite; cualquier otro driver hace fallar el comando.

`--connection=` es la conexión de **origen**; sin ella, usa `config('database.default')`. El
destino se calcula solo: en MySQL/MariaDB, se le agrega el sufijo `_test` si no lo tiene ya; en
SQLite, se agrega `_test` antes de la extensión del archivo (`app.sqlite` → `app_test.sqlite`). Si
la conexión apunta a `:memory:`, falla — no se puede clonar.

Dos guardas antes de tocar nada: el nombre destino tiene que terminar en `_test` (es lo único que
impide borrar la base real por accidente), y si la conexión de origen ya apunta a una base `_test`,
el comando se niega.

### Parámetros

| Parámetro | Efecto |
|---|---|
| `--connection=` | Conexión de la que se clona el esquema. Sin ella, la conexión por defecto de la aplicación |
| `--force` | Sin destino previo, clona igual. Con destino ya existente: sin `--force` no toca nada («ya existe, no se toca»); con `--force`, lo rehace desde cero (`DROP DATABASE`/borra el archivo, y recrea) |
| `--dry-run` | Enumera las tablas que copiaría, sin tocar nada. También responde al ensayo activado por otro comando (p. ej. `innodite:test` invocándolo internamente) |

**No existen `--context=` ni `--reclonar` en este comando** — son parámetros de `innodite:test`.

### Qué genera

No escribe ningún archivo del paquete. Crea o recrea una base de datos completa a nivel de esquema
(estructura real vía `SHOW CREATE TABLE`/`sqlite_master`, no lo que describen las migraciones),
sin una sola fila.

### Ejemplos

**Caso: primera vez, sobre la conexión por defecto.**
```bash
php artisan innodite:crear-bd-test
```
```
Clonando esquema de 'laravel' a 'laravel_test'…
✔ 14 tablas copiadas
```

**Caso: una conexión de multiinquilino en particular.**
```bash
php artisan innodite:crear-bd-test --connection=tenant_one
```

**Caso: la base `_test` ya existe y se sabe que quedó con un esquema viejo.**
```bash
php artisan innodite:crear-bd-test --force
```
```
La base laravel_test existe: se rehace desde cero.
✔ 14 tablas copiadas
```

**Caso: ver qué tablas copiaría, sin tocar nada.**
```bash
php artisan innodite:crear-bd-test --connection=mysql --dry-run
```
```
ENSAYO (--dry-run): no se va a escribir ni a ejecutar nada.
(ensayo) copiaría users
(ensayo) copiaría permissions
… (14 líneas)
Esto es lo que haría (14). No se tocó ninguna base de datos.
```
