```bash
php artisan innodite:publish-stubs [stubs...]
```

### Qué hace

Exporta al proyecto las plantillas (stubs) que se vayan a personalizar. Sin argumentos, lista las
disponibles sin copiar ninguna. Una vez copiada una plantilla, gana sobre la del paquete en
cualquier generación posterior.

### Parámetros

| Parámetro | Efecto |
|---|---|
| `stubs...` | Nombres de los stubs a publicar. Sin argumentos: solo lista, no copia nada |

### Qué genera

Copia los archivos indicados a:

```
module-maker-config/stubs/contextual/<nombre>.stub
```

Una plantilla publicada **no se actualiza** con nuevas versiones del paquete — sigue generando con
la forma que tenía al momento de publicarla.

### Ejemplos

**Caso: ver qué plantillas existen, antes de decidir cuál personalizar.**
```bash
php artisan innodite:publish-stubs
```

**Caso: personalizar solo la plantilla del controlador.**
```bash
php artisan innodite:publish-stubs controller
```

**Caso: personalizar el controlador y el modelo.**
```bash
php artisan innodite:publish-stubs controller model
```
