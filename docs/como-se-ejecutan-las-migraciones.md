# Cómo se ejecutan las migraciones, y quién decide su orden

Una página para no tener que volver a deducirlo del código.

---

## La respuesta corta

**Las migraciones las ejecuta el seeder, no un comando de migraciones.** Y el orden se compone de
dos niveles, cada uno con su dueño:

| Quién | Qué decide | Quién lo escribe |
|---|---|---|
| `deploy`, en `config/make-module.php` | El orden **entre** subfuncionalidades | **Tú.** El generador añade cada una al final; moverla a su sitio es tuyo |
| El trait `MigrationsList` de cada subfuncionalidad | El orden **dentro** de esa subfuncionalidad | El paquete, leyendo la carpeta |

Todo lo demás de esta página desarrolla esas dos filas.

---

## 1 · Dentro de una subfuncionalidad: no declaras nada

Al generar una migración, el paquete reescribe el trait `MigrationsList` de esa subfuncionalidad
entero, leyendo la carpeta:

```php
$archivos = glob("{$carpetaDeMigraciones}/*.php");
sort($archivos);   // el orden lo da el nombre, y el nombre empieza por el timestamp
```

El resultado es código, y viaja dentro del módulo:

```php
trait TenantSharedSaleCartMigrationsList
{
    public function getMigrationsList(): array
    {
        return [
            'Modules/Sale/Database/Migrations/Tenant/Shared/Cart/2026_09_05_081231663374_create_carts_table_final.php',
        ];
    }

    public function runMigrations(): void   // migrate --path=… --database=… , una a una
    { /* … */ }
}
```

No añade líneas: **rehace la lista**. Por eso no puede quedar desfasada… mientras genere el paquete.

> ⚠️ **Una migración que escribas a mano no entra sola.** Entra la próxima vez que se genere otra
> migración en esa misma carpeta, porque entonces el `glob` la ve. Hasta entonces, añádela tú a la
> lista, **en su posición**.

**Por qué el trait y no un archivo de configuración:** hasta la v4 esto era un manifiesto JSON. Un
JSON describe lo que la carpeta ya dice, no viaja con el módulo cuando lo copias a otro proyecto, y
se desincroniza sin avisar.

---

## 2 · Entre subfuncionalidades: lo declaras tú, y es lo único que hay que mantener a mano

```php
// config/make-module.php
'deploy' => [
    'tenant_shared' => [
        'Sale/Tenant/Shared/Customer',    // primero los padres
        'Sale/Tenant/Shared/Order',
        'Sale/Tenant/Shared/Cart',        // carts apunta a customers y a orders: va después
        // {{DEPLOY_TENANT_SHARED_END}}
    ],
],
```

Cada línea es la **carpeta** de una subfuncionalidad, no un nombre de clase: así, renombrar una clase
no rompe la lista. De esa carpeta salen sus tres piezas ejecutables — `Stage`, `Production` y
`Permissions` — según lo que se esté desplegando.

**El generador escribe la línea; tú la colocas.** `innodite:make-module` y `innodite:add-entity`
añaden cada subfuncionalidad nueva **al final** de su contexto. Es la única posición que no miente
sobre lo que el paquete sabe: sabe que la subfuncionalidad existe, no si su tabla apunta a otra. Eso
lo sabe el negocio.

> Si la configuración no está publicada o alguien borró un marcador, el generador **no falla**: te
> dice la línea exacta que hay que escribir a mano. El módulo generado es correcto de todos modos.

---

## 3 · Quién ejecuta, en orden

```
innodite:deploy stage --context=tenant --all
  └── el seeder de despliegue del proyecto (database/seeders/)
       └── lee deploy['tenant_shared'] de arriba abajo
            └── por cada módulo, su maestro Application{Stage|Production}Seeder
                 └── por cada subfuncionalidad, en el orden declarado:
                      1. runMigrations()      ← el trait MigrationsList: crea el esquema
                      2. runInlineAlters()    ← los cambios posteriores, con guardia
                      3. validateTables()     ← comprueba que las tablas quedaron en pie
                      4. los datos canónicos  ← upsert, o recarga si SEEDER_DESTRUCTIVE
                      5. el PermissionsSeeder ← hereda el modo destructivo del padre
```

Las cinco cosas pasan **por subfuncionalidad**, antes de pasar a la siguiente. Por eso la posición en
`deploy` decide a la vez cuándo se crean sus tablas y cuándo se siembran sus filas.

En multitenant, `--context=tenant` entra en el contexto de cada cliente antes de sembrar: en consola
no hay middleware que conmute la conexión, y sin eso el seeder escribiría en la base central creyendo
que escribe en la del cliente.

---

## 4 · Los comandos, y para qué sirve cada uno

| Comando | Cuándo |
|---|---|
| `innodite:deploy <stage\|production> --context=…` | **El camino normal.** Esquema, datos y permisos, en el orden declarado. `--dry-run` para ver el plan sin tocar la base |
| `innodite:migrate-one <Modulo:Contexto/archivo.php>` | Una migración suelta, nombrada a mano. Para reparar algo puntual |
| ~~`innodite:migrate-plan`~~ | **Retirado en la v4.** Ver abajo |

### Por qué se retiró el plan de migraciones

Aplicaba las migraciones recorriendo el árbol de archivos y ordenándolas con `ksort` sobre la ruta —
el abecedario de las carpetas—, **sin leer `deploy`**. Con `Cart`, `Customer` y `Order` en un módulo
de ventas, `carts` se migraba antes que las dos tablas a las que apunta, aunque sus timestamps y tu
`deploy` dijeran lo contrario.

Y era el único punto del paquete que ejecutaba `migrate` fuera del seeder. Se retiró en vez de
corregirse: con él, el orden del despliegue tenía dos fuentes que podían contradecirse. Ahora tiene
una.

---

## 5 · Lo que no se hace

| ⛔ | Por qué |
|---|---|
| `php artisan migrate` a secas | Aplica todo lo pendiente de todos los módulos, en el orden de Laravel, y contra la conexión por defecto. En multitenant eso escribe en la base que no era |
| Ejecutar una migración a mano en vez del seeder | El esquema es el paso 1 de cinco. Sin los otros cuatro, la base tiene tablas y ninguna fila, y ningún permiso: la aplicación levanta con pantallas que no abre nadie |
| Confiar en el timestamp para el orden entre módulos | El timestamp ordena dentro de una carpeta. Entre subfuncionalidades manda `deploy`, y solo `deploy` |
| Dejar una subfuncionalidad fuera de `deploy` | No se despliega. Existe en el disco y no ocurre nunca — y no hay error que lo delate |
