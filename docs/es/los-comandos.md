Diez comandos, y el orden en que se usan es casi siempre el mismo.

| Comando | Para qué |
|---|---|
| `innodite:doctor` | **Empieza por aquí.** Diagnóstico en cascada, con la línea de arreglo de cada fallo |
| `innodite:module-setup` | Elige el modo del proyecto y deja escrita la configuración |
| `innodite:make-module` | Genera un módulo completo y lo declara en el orden de despliegue |
| `innodite:add-entity` | Añade una subfuncionalidad a un módulo que ya existe |
| `innodite:deploy` | Levanta el proyecto: esquema, datos canónicos y permisos, en el orden declarado |
| `innodite:migrate-one` | Aplica **una** migración concreta, contra la base de su contexto |
| `innodite:crear-bd-test` | Clona el esquema real en la base de pruebas, sin una sola fila |
| `innodite:test` | Ejecuta el contrato de pruebas de una subfuncionalidad |
| `innodite:publish-stubs` | Exporta al proyecto **solo** las plantillas que vayas a personalizar |
| ~~`innodite:migrate-plan`~~ | **Retirado.** Sigue registrado solo para decirlo |

## El contexto, en los que generan

En multiinquilino, `make-module`, `add-entity`, `test`, `deploy` y `migrate-one` piden `--context`.
Se pasa `central`, `tenant` o el que tu proyecto haya declarado en su catálogo.

**En aplicación única no se pasa**: no hay eje que elegir, y el comando lo rechaza diciéndolo.

```bash
php artisan innodite:make-module Invoice --context=central   # multiinquilino
php artisan innodite:make-module Invoice                     # aplicación única
```

⛔ Y ninguno **adivina** el contexto cuando falta. Caer en el primero del catálogo acertaría a veces
y el resto de las veces escribiría las rutas en el archivo que no es, protegidas con el permiso de
otro contexto: un módulo perfectamente escrito y completamente mal.

## El ensayo, en casi todos

```bash
php artisan innodite:make-module Invoice --dry-run
php artisan innodite:deploy stage --dry-run
```

`--dry-run` enseña lo que haría **sin escribir nada**. En `deploy` dice además **contra qué
conexión** iría, que es la pregunta que más caro sale equivocar.

## ⛔ `innodite:migrate-plan` está retirado

Sigue registrado, pero solo para decirlo: el esquema lo aplica `innodite:deploy`, a través de los
seeders. Si tienes un guion que lo llama, cámbialo.

Se retiró porque ordenaba las migraciones por el **nombre de las carpetas** en vez de por qué tabla
depende de cuál, así que una tabla con clave foránea podía aplicarse antes que aquella a la que
apunta. El orden bueno ya vivía en otro sitio —la lista que declara tu proyecto— y tener dos dueños
del mismo orden era el defecto.

## Un módulo no está listo porque sus archivos existan

Está listo cuando **se despliega** y su contrato de pruebas pasa:

```bash
php artisan innodite:doctor
php artisan innodite:deploy stage
php artisan innodite:test Invoice Payment
```

⭐ Es la diferencia entre comprobar que se escribieron los archivos y comprobar que hacen efecto —
y es donde aparecen los fallos que no se ven leyendo el código generado.
