```bash
php artisan innodite:migrate-one {coordenada} {--context=} {--yes} {--dry-run}
```

### Qué hace

Ejecuta una sola migración, nombrada por su coordenada `Modulo:Contexto/archivo.php`. La base de
datos destino sale de la propia coordenada.

### Parámetros

| Parámetro | Descripción |
|---|---|
| `coordenada` | `Modulo:Contexto/archivo.php` — ej. `Invoice:Central/2026_08_01_120000_crea_facturas.php` |
| `--context=` | Fuerza el contexto de ejecución en vez de derivarlo de la coordenada |
| `--yes` | Omite la confirmación interactiva |
| `--dry-run` | Muestra qué haría, sin ejecutar |

### Qué genera

No genera archivos. Aplica esa única migración contra la base de datos de su contexto.

### Ejemplos

**Caso: aplicar una migración puntual que quedó pendiente.**
```bash
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php"
```

**Caso: lo mismo, sin que pida confirmación (para un script).**
```bash
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php" --yes
```

**Caso: ver qué haría antes de aplicarla.**
```bash
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php" --dry-run
```
