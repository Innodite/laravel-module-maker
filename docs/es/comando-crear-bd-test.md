```bash
php artisan innodite:crear-bd-test {--context=} {--reclonar}
```

### Qué hace

Copia el esquema de la base de datos real a la base de datos de pruebas (`_test`), sin copiar
ningún dato — solo la estructura.

### Parámetros

| Parámetro | Efecto |
|---|---|
| `--context=` | En `multitenant`: sobre qué base de contexto clonar |
| `--reclonar` | Rehace la base `_test` desde cero, aunque ya exista |

### Qué genera

Crea o reemplaza la base de datos `_test` con el esquema actual de la base real, sin filas.

### Ejemplos

**Caso: primera vez que se corre el contrato de pruebas en el proyecto.**
```bash
php artisan innodite:crear-bd-test
```

**Caso: multiinquilino, la base del contexto central.**
```bash
php artisan innodite:crear-bd-test --context=central
```

**Caso: hay una sospecha de que la base `_test` quedó con un esquema viejo.**
```bash
php artisan innodite:crear-bd-test --reclonar
```
