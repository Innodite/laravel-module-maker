```bash
php artisan innodite:make-module Invoice
```

Escribe el módulo entero —modelo, repositorio, servicio, controlador, form requests, migración,
seeders, rutas, pantallas y pruebas— y lo **declara en el orden de despliegue**, que es lo que hace
que un despliegue posterior lo levante.

En multiinquilino hay que decir dónde vive:

```bash
php artisan innodite:make-module Invoice --context=central
php artisan innodite:make-module Invoice --context=tenant
```

⛔ **Sin contexto, en un proyecto multiinquilino, no se genera nada** y el comando dice por qué. Lo
que escribiría sin saberlo sería plausible y equivocado: rutas en el archivo que no toca, protegidas
por un permiso que no corresponde.

Y lo que sale es **la misma estructura en los dos modos**: 37 archivos, con la subfuncionalidad por
delante y el contexto como hoja. La forma completa, con el árbol de un módulo real, está en
[la ficha del árbol](la-forma-del-arbol.md).

## Añadir una entidad a un módulo que ya existe

```bash
php artisan innodite:add-entity Invoice Payment
```

Respeta lo que ya hay: inyecta lo nuevo en el proveedor del módulo sin sobrescribir sus enlaces, y
las pantallas que ya existan no se tocan.

## Generar solo una pieza

Las banderas cortas añaden una sola capa, y sirven para reparar algo que falta:

```bash
php artisan innodite:make-module Invoice -S        # solo el servicio y su interfaz
php artisan innodite:make-module Invoice -R -G     # repositorio y migración
```

## Lo que el comando te dice al terminar

Además de lo generado, avisa de **dos cosas que harían inútil todo lo anterior**:

- Que el módulo **no vaya a cargar**, porque falta declarar el namespace en tu autoload o falta
  recargarlo. Sin eso la aplicación arranca igual y sus rutas sencillamente no existen.
- Que su **pantalla no vaya a abrir**, porque falta el generador de rutas del navegador o su
  directiva en el layout.

⭐ Los dos fallan sin dejar rastro en el servidor: la petición responde 200. Por eso se avisan en el
momento, y no se descubren horas después.
