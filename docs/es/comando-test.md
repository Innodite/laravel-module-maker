```bash
php artisan innodite:test {module} {subfeature} {--context=} {--filter=} {--continuar} {--reclonar} {--sin-reclonar} {--repetir=1}
```

### Qué hace

Ejecuta el contrato de pruebas de una subfuncionalidad — no de un módulo completo. Corre en
cascada, en este orden exacto, y corta en la primera que falla salvo `--continuar`:

| Orden | Clase | Cubre |
|---|---|---|
| 1 | `ScaffoldTest` | El andamiaje existe y su contenido cumple |
| 2 | `SchemaTest` | Tablas y columnas |
| 3 | `PermissionsTest` | Permiso único por ruta, sembrado, y la puerta |
| 4 | `DeploymentTest` | El despliegue, ejecutado de verdad |
| 5 | `HttpTest` | El comportamiento del negocio |

El tema 6 (la vista) nunca lo corre este comando — lo ejecuta Vitest, aparte. El comando lo nombra
siempre al terminar, pase lo que pase, para que nadie lo dé por corrido en silencio.

Antes de ejecutar, comprueba: que el grupo esté completo (manifiesto + base + las cinco piezas — sin
eso ninguna puede derivar rutas ni permisos), y que **no** tenga la estructura de pruebas retirada
desde v5.0.0 (`Tests/Feature/Shared/`, o un trait incorporado con `use <Trait>` en vez de inline —
en aplicación única esta comprobación se salta, porque no puede existir un trait compartido entre
contextos que no existen). Si encuentra cualquiera de las dos cosas, no ejecuta nada.

### Preparación de la base de pruebas

Antes de la cascada, decide si reclonar la base `_test`:
- Con `--reclonar`, siempre.
- Sin `--reclonar` pero con la base desfasada (no existe, o sus tablas no coinciden con las de la
  base real), también.
- Si ninguna de las dos aplica, no reclona nada al empezar.

Reclonar es una llamada interna a `innodite:crear-bd-test --connection=<la real> --force`.

### Clasificación de un rojo

Tras un fallo, en este orden fijo:
- **¿Base sucia?** — reclona y reejecuta la pieza (salvo `--sin-reclonar`, que se salta este paso).
- **¿Intermitente?** — solo si `--repetir=N` con `N > 1`: repite la pieza N veces; si pasa al
  menos una vez, se clasifica como intermitente.
- Si ninguna de las dos lo explica, se declara defecto.

Un verde que solo aparece **tras** reclonar no se reporta como verde limpio — el comando lo marca
como "pasó tras re-clonar" y termina en fallo igual.

### Parámetros

| Parámetro | Efecto |
|---|---|
| `module` | Nombre del módulo (ej. `Invoice`) |
| `subfeature` | Subfuncionalidad dentro del módulo (ej. `Payment`) |
| `--context=` | En `multitenant`: contexto sobre el que corre el grupo |
| `--filter=` | Patrón de PHPUnit — acota qué test, dentro de cada pieza, se ejecuta |
| `--continuar` | No corta en la primera pieza que falla — acumula y reporta todas al final |
| `--reclonar` | Reconstruye la base `_test` antes de empezar, sin mirar en qué estado está |
| `--sin-reclonar` | En la clasificación de un rojo, no reclona — para investigar el fallo tal cual |
| `--repetir=N` | (default `1`) Repite la pieza fallida N veces, en la clasificación, para distinguir intermitente de defecto |

### Qué genera

No genera ni modifica ningún archivo de código. Puede invocar `innodite:crear-bd-test --force`
internamente (al preparar la base o durante la clasificación), lo que sí recrea la base `_test`.

### Aplicación única

```bash
php artisan innodite:test Invoice Invoice
```
Sin `--context`. Las clases del grupo no llevan prefijo (`InvoiceContract`, `InvoicePermissionsTest`).

### Multiinquilino

```bash
php artisan innodite:test Invoice Payment --context=central
```
Las clases llevan el prefijo del contexto (`CentralInvoicePayment...`). La ruta del grupo es
`Modules/{Modulo}/{SubFuncion}/Tests/Feature/{Contexto}`.

### Ejemplos

**Caso: correr el contrato completo de una subfuncionalidad.**
```bash
php artisan innodite:test Invoice Payment --context=central
```
```
Scaffold ✔  Schema ✔  Permissions ✔  Deployment ✔  Http ✔
Tema 6 (vista): no ejecutado aquí — correr Vitest en resources/js/__tests__/Central/Payment/
Contrato completo: 5/5 piezas en verde.
```

**Caso: acotar a una sola prueba dentro del grupo.**
```bash
php artisan innodite:test Invoice Payment --filter=test_no_se_ve_sin_permiso
```

**Caso: ver el grupo entero de una pasada, sin corte temprano.**
```bash
php artisan innodite:test Invoice Payment --continuar
```
```
Scaffold ✔  Schema ✔  Permissions ✘  Deployment ✘  Http ✘
3 de 5 piezas fallaron. Detalle de cada una arriba.
```

**Caso: un rojo que podría deberse a una base `_test` desactualizada.**
```bash
php artisan innodite:test Invoice Payment --reclonar
```

**Caso: confirmar si un rojo es intermitente antes de investigarlo como defecto.**
```bash
php artisan innodite:test Invoice Payment --sin-reclonar --repetir=3
```
```
Permissions ✘ (intento 1) ✔ (intento 2) ✔ (intento 3)
Clasificado como INTERMITENTE — no es base sucia ni defecto confirmado.
```
