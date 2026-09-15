**Retirado en la v4.** Ejecutarlo siempre devuelve error (código de salida 1), nunca éxito — un
comando que no ejecuta nada y termina en verde sería peor que no tenerlo.

Usar `innodite:deploy` — ver su ficha en **Los comandos**. Para una migración suelta,
`innodite:migrate-one`.

```bash
php artisan innodite:deploy stage --context=central
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php"
```

### Ejemplo

```bash
php artisan innodite:migrate-plan
```
```
FALLA: innodite:migrate-plan se retiró en la v4.
FIX: despliega con innodite:deploy.
     php artisan innodite:deploy stage --context=central
     Una migración suelta:
     php artisan innodite:migrate-one <Modulo:Contexto/archivo.php>
```

El comando acepta cualquier opción antigua (por ejemplo `--context=`) sin rechazarla por firma
inválida — la ignora y muestra el mismo mensaje de retiro, en vez de un error de consola sobre una
opción que ya no existe.
