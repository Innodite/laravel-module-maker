```bash
php artisan innodite:test {Modulo} {SubFuncionalidad} {--context=} {--filter=} {--continuar} {--reclonar} {--sin-reclonar} {--repetir=}
```

### Qué hace

Ejecuta el contrato de pruebas de una subfuncionalidad — no de un módulo completo. Corre en
cascada y corta en la primera pieza que falla, salvo que se pida lo contrario:

```
Scaffold  →  Schema     →  Permissions  →  Deployment  →  Http
tema 0       temas 1-2     temas 3-5       tema 8         tema 7
```

El tema 6 (la vista) lo ejecuta Vitest, aparte de este comando.

Antes de ejecutar, comprueba que el grupo de pruebas esté completo (manifiesto + base + las cinco
piezas). **Desde la 5.1.0**, también rechaza un grupo que tenga la carpeta `Tests/Feature/Shared/`
o una pieza de `Central`/`Tenant` que incorpore un trait con `use <Trait>` en vez de llevar el
código inline — en ese caso no ejecuta nada y dice la ruta exacta y el arreglo.

### Parámetros

| Parámetro | Efecto |
|---|---|
| `Modulo` | Nombre del módulo (ej. `Invoice`) |
| `SubFuncionalidad` | Nombre de la subfuncionalidad dentro del módulo (ej. `Payment`) |
| `--context=` | En `multitenant`: contexto sobre el que corre el grupo |
| `--filter=` | Acota a las pruebas cuyo nombre coincida |
| `--continuar` | No corta en la primera pieza que falla — ejecuta el grupo entero |
| `--reclonar` | Reconstruye la base `_test` antes de correr, para descartar que venía sucia |
| `--sin-reclonar` | Conserva el estado de la base tras un fallo, para poder inspeccionarlo |
| `--repetir=N` | Repite N veces la pieza que falló, para distinguir un intermitente de un defecto |

### Qué genera

No genera archivos. Ejecuta las pruebas ya generadas contra la base `_test` y escribe su resultado
en consola.

### Ejemplos

**Caso: correr el contrato completo de una subfuncionalidad.**
```bash
php artisan innodite:test Invoice Payment
```

**Caso: multiinquilino, especificando el contexto.**
```bash
php artisan innodite:test Invoice Payment --context=central
```

**Caso: acotar a una sola prueba dentro del grupo.**
```bash
php artisan innodite:test Invoice Payment --filter=test_no_se_ve_sin_permiso
```

**Caso: ver el grupo entero de una pasada, sin corte temprano.**
```bash
php artisan innodite:test Invoice Payment --continuar
```

**Caso: un rojo que podría deberse a una base `_test` desactualizada.**
```bash
php artisan innodite:test Invoice Payment --reclonar
```

**Caso: confirmar si un rojo es intermitente antes de investigarlo como defecto.**
```bash
php artisan innodite:test Invoice Payment --repetir=3
```
