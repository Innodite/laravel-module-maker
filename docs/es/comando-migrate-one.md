```bash
php artisan innodite:migrate-one {coordinate} {--context=} {--force} {--dry-run}
```

### Qué hace

Ejecuta una sola migración, nombrada por su coordenada `Modulo:Contexto/archivo.php`.

**Parseo de la coordenada:** se corta en los **dos puntos** (módulo / resto); dentro del resto, en
la **última barra** (`/`) — todo lo anterior es la carpeta de contexto, lo posterior es el archivo.
El módulo se normaliza a PascalCase. Con eso arma la ruta
`Modules/{Módulo}/Database/Migrations/{Contexto}/{archivo}` y comprueba que exista.

El contexto de **ejecución** (contra qué base corre) se deriva de la coordenada, salvo que se pase
`--context=`, que lo fuerza.

### Parámetros

| Parámetro | Descripción |
|---|---|
| `coordinate` | `Modulo:Contexto/archivo.php` — ej. `Invoice:Central/2026_08_01_120000_crea_facturas.php` |
| `--context=` | Fuerza el contexto de ejecución en vez de derivarlo de la coordenada |
| `--force` | Omite la confirmación interactiva. Sin consola interactiva y sin `--force`, el comando falla en vez de preguntar |
| `--dry-run` | Imprime el resumen (migración, módulo, contexto, conexión, base) y termina — no llega a comprobar siquiera que la base exista |

### Qué genera

No escribe ningún archivo del paquete. Aplica exactamente esa migración contra la conexión
resuelta, vía el comando `migrate` nativo de Laravel (con `--realpath` y `--force` internos, para
que no vuelva a preguntar).

### Aplicación única

Sin `--context`: la coordenada no lleva carpeta de contexto (o la lleva vacía, según cómo se
estructuraron las migraciones del módulo). La conexión resuelta es la de la aplicación.

### Multiinquilino

Si el contexto es de inquilino (`tenant`, o cualquiera con `is_tenant: true`), la conexión de
ejecución es siempre `config('database.default')` — la conmuta el paquete de tenencia, sin exigir
`connection_key` ni `tenancy_strategy`. Si es un contexto central (`tenancy_strategy: manual`),
exige `connection_key` y que esa conexión exista en `config/database.php`.

### Ejemplos

**Caso: aplicar una migración puntual que quedó pendiente.**
```bash
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php"
```
```
Migración: 2026_08_01_120000_crea_facturas.php
Módulo: Invoice · Contexto: Central · Conexión: central · Base: laravel
¿Continuar? (yes/no) [no]:
```

**Caso: lo mismo, sin pedir confirmación (para un script).**
```bash
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php" --force
```

**Caso: ver qué haría, sin tocar la base ni comprobar que exista.**
```bash
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php" --dry-run
```
```
Migración: 2026_08_01_120000_crea_facturas.php
Módulo: Invoice · Contexto: Central · Conexión: central · Base: laravel
Dry-run completado. No se aplicó nada.
```
