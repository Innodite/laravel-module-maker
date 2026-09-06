El paquete se instala con Composer y necesita **un solo paso más** antes de poder generar nada.

```bash
composer require innodite/laravel-module-maker
php artisan innodite:module-setup
```

`module-setup` es lo que elige el **modo del proyecto** y deja escrita la configuración. Sin modo
elegido los comandos se niegan a generar y dicen cómo elegirlo — eso es deliberado, y el porqué está
en la ficha del modo.

## Comprobar que va a funcionar

```bash
php artisan innodite:doctor
```

⭐ **Empieza siempre por aquí cuando algo no salga como esperabas.** Es un diagnóstico en cascada:
primero el entorno del generador —si puede escribir, si su configuración está publicada—, después el
contrato de tu proyecto: que el modelo de usuario sepa resolver permisos, que el puente de contexto
esté registrado y que estén las piezas de las que dependen las pantallas generadas.

Cada fallo trae su línea de arreglo. Si el entorno falla, la segunda etapa no se ejecuta y el
comando lo dice; para verlo todo de una vez, `--continuar`.

## Lo que tu proyecto tiene que traer

El paquete se escribe **contra** las convenciones de tu proyecto, no las instala:

| Necesitas | Para qué |
|---|---|
| Las tablas de permisos y roles | El generador escribe los permisos de cada ruta; las tablas son tuyas |
| Ziggy, con `@routes` en el layout | Las pantallas generadas piden sus rutas **por su nombre** |
| El namespace `Modules\` en tu `autoload.psr-4` | Sin él, nada de lo generado resuelve |

⚠️ **Las tres fallan en silencio si faltan**, y por eso el `doctor` las mira. La más traicionera es
la segunda: sin ella la pantalla se genera perfecta y muere al abrirse, con un error de JavaScript
que no menciona ni al módulo ni al paquete.
