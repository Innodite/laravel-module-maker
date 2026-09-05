Desplegar es lo que convierte los archivos generados en tablas, datos y permisos que existen.

```bash
php artisan innodite:deploy stage
php artisan innodite:deploy production
```

Ejecuta los seeders **en el orden que declara tu configuración**: primero el esquema, después los
datos canónicos, después los permisos y el usuario administrador.

## En multiinquilino hay que decir a qué cliente

```bash
php artisan innodite:deploy stage --context=central
php artisan innodite:deploy stage --context=tenant --tenant=acme
php artisan innodite:deploy stage --context=tenant --all
```

⛔ **Sin `--tenant` ni `--all` el comando no arranca.** Con clientes que comparten funcionalidad, el
despliegue tiene que **entrar en el contexto de cada uno**. En una petición web eso lo hace el
middleware de identificación; en consola no hay middleware que lo haga.

⚠️ Un despliegue que no entra escribe en la base **central** creyendo que escribe en la del cliente,
y lo hace sin un solo aviso.

## Un módulo suelto

```bash
php artisan innodite:deploy stage --module=Invoice
```

Levanta solo ese módulo, por sus maestros, en vez del proyecto entero.

## Una migración concreta

```bash
php artisan innodite:migrate-one Invoice:Central/2026_01_01_crear_invoices.php
```

La coordenada dice el módulo, el contexto y el archivo, y de ahí sale contra qué base se aplica.

## Antes de tocar nada

```bash
php artisan innodite:deploy production --dry-run
```

⭐ En producción, el ensayo es obligatorio en la práctica: dice qué se ejecutaría y **contra qué
conexión**. Es la comprobación que separa un despliegue de un incidente.

## Los datos canónicos no se destruyen

Los seeders generados son **no destructivos por defecto**: reponen lo que falta y respetan lo que
hay. El modo que sí borra existe, pero hay que pedirlo a propósito y el comando avisa antes.
