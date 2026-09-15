```bash
php artisan innodite:publish-stubs [stub...] {--all} {--force}
```

### Qué hace

Exporta al proyecto las plantillas (stubs) que se vayan a personalizar, desde `stubs/contextual/`
del paquete instalado hacia `module-maker-config/stubs/contextual/` del proyecto.

- **Sin argumentos y sin `--all`**: no copia nada — solo lista el catálogo completo del paquete, en
  columnas, con la advertencia de que una plantilla exportada deja de actualizarse con el paquete.
- **Con nombres concretos** (con o sin el sufijo `.stub`): copia solo esas. Si **alguno** de los
  nombres pedidos no existe, el comando falla completo y no copia nada — es todo o nada.
- **Con `--all`**: copia el catálogo entero, sin necesidad de nombrarlas.

Existe además la vía nativa de Laravel `php artisan vendor:publish --tag=module-maker-stubs`, que
copia todas de golpe sin las advertencias ni el chequeo granular de este comando — son caminos
distintos, no uno sustituye al otro.

### Parámetros

| Parámetro | Efecto |
|---|---|
| `stub...` | Nombres de los stubs a publicar. Sin argumentos: solo lista |
| `--all` | Exporta el catálogo completo |
| `--force` | Sobrescribe las que ya estén exportadas. Sin ella, una plantilla ya exportada se conserva tal cual, sin tocarla |

### Qué genera

Copia archivos a `module-maker-config/stubs/contextual/{nombre}.stub`. No modifica ningún otro
archivo del proyecto.

### Ejemplos

**Caso: ver qué plantillas existen, antes de decidir cuál personalizar.**
```bash
php artisan innodite:publish-stubs
```
```
34 plantillas disponibles:
controller.stub        service.stub            vue-index.stub
repository.stub         model.stub              vue-create.stub
… (en columnas)
Exportar deja de actualizarse con el paquete. Exporta solo lo que vayas a tocar.
```

**Caso: personalizar solo la plantilla del controlador.**
```bash
php artisan innodite:publish-stubs controller
```
```
✔ Exportado controller.stub → module-maker-config/stubs/contextual/controller.stub
```

**Caso: personalizar el controlador y el modelo.**
```bash
php artisan innodite:publish-stubs controller model
```

**Caso: exportar todo el catálogo.**
```bash
php artisan innodite:publish-stubs --all
```

**Caso: ya se exportó `controller` y se quiere reemplazarlo por la versión actual del paquete.**
```bash
php artisan innodite:publish-stubs controller --force
```
