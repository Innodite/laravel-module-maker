Lo que el generador escribe sale de unas plantillas, y hay tres formas de cambiarlas.

## 1 · Los stubs de tu proyecto

```bash
php artisan vendor:publish --tag=module-maker-stubs
```

Copia las plantillas a `module-maker-config/stubs/contextual/` y a partir de ahí ganan a las del
paquete. Puedes cambiar solo las que te interesen: lo que no esté ahí se sigue leyendo del paquete.

⚠️ **Publicarlas tiene un coste que conviene saber**: una plantilla copiada **no se actualiza** con
el paquete. Si no vas a personalizarla, no la publiques — y si publicaste todas «por si acaso»,
borra las que no tocaste.

## 2 · Los stubs que aporta otro paquete instalado

Cualquier paquete instalado que traiga

```
stubs/module-maker/contextual/<nombre>.stub
```

participa automáticamente. Sirve para generar contra una biblioteca de interfaz concreta —su tabla,
sus formularios— en vez de contra las plantillas genéricas.

**Quién gana a quién**, de lo más específico a lo más genérico:

| # | Dónde | Manda |
|---|---|---|
| 1 | `module-maker-config/stubs/contextual/{Contexto}/` | Tu proyecto, para un contexto |
| 2 | `module-maker-config/stubs/contextual/` | Tu proyecto |
| 3 | `stubs/module-maker/contextual/` de un paquete instalado | Ese paquete |
| 4 | Las del generador | El paquete |

⭐ **Tu proyecto siempre gana.** Es el único que puede tener la última palabra sobre su propio
código. Y si dos paquetes aportan la misma plantilla, gana el primero por orden alfabético y
`make-module` lo dice al terminar, junto con quién aportó qué.

## 3 · El punto de enganche del módulo

`provider-boot.stub` es una plantilla **opcional**: no existe en el paquete y solo se usa si tu
proyecto o un paquete instalado la aportan. Su contenido se escribe dentro del `boot()` del
proveedor del módulo generado; si no la aporta nadie, ese `boot()` sale vacío.

Está pensada para enganchar el módulo recién creado en el **menú** de tu aplicación, que es lo que
el generador no puede escribir por su cuenta: esa línea depende de cómo declare su menú cada
proyecto. Recibe dos variables:

| Variable | Qué trae | Ejemplo |
|---|---|---|
| `{{{ moduleName }}}` | El nombre del módulo | `Invoice` |
| `{{{ functionality }}}` | La funcionalidad, como la nombran sus rutas | `invoices` |

⛔ **Por qué no viene hecho.** Escribir ese enganche «por si acaso» llamaría a una clase que puede
no existir, y eso no deja un módulo invisible: deja la aplicación entera sin arrancar, incluido el
`artisan` con el que se arreglaría.
