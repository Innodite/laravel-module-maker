Nueve comandos, y el orden en que se usan es casi siempre el mismo.

| Comando | Para qué |
|---|---|
| `innodite:doctor` | **Empieza por aquí.** Diagnóstico en cascada, con la línea de arreglo de cada fallo |
| `innodite:module-setup` | Elige el modo del proyecto y deja escrita la configuración |
| `innodite:make-module` | Genera un módulo completo y lo declara en el orden de despliegue |
| `innodite:add-entity` | Añade una entidad a un módulo que ya existe |
| `innodite:deploy` | Levanta el proyecto: esquema, datos canónicos y permisos, en el orden declarado |
| `innodite:migrate-one` | Aplica **una** migración concreta, contra la base de su contexto |
| `innodite:crear-bd-test` | Clona el esquema real en la base de pruebas, sin una sola fila |
| `innodite:test` | Ejecuta el contrato de pruebas de una subfuncionalidad |
| `innodite:publish-frontend` | Publica los componentes y composables que usan las pantallas generadas |

## El ensayo, en casi todos

```bash
php artisan innodite:make-module Invoice --dry-run
php artisan innodite:deploy stage --dry-run
```

`--dry-run` enseña lo que haría **sin escribir nada**. En `deploy` dice además **contra qué conexión**
iría, que es la pregunta que más caro sale equivocar.

## ⛔ `innodite:migrate-plan` está retirado

Sigue registrado, pero solo para decirlo: el esquema lo aplica `innodite:deploy`, a través de los
seeders. Si tienes un guion que lo llama, cámbialo.

## Un módulo no está listo porque sus archivos existan

Está listo cuando **se despliega** y su contrato de pruebas pasa:

```bash
php artisan innodite:doctor
php artisan innodite:deploy stage
php artisan innodite:test Invoice Payment
```

⭐ Es la diferencia entre comprobar que se escribieron los archivos y comprobar que hacen efecto —
y es donde aparecen los fallos que no se ven leyendo el código generado.
