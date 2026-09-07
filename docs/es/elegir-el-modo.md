El modo decide la **forma** de todo lo que se genera, y se elige una vez, al instalar.

| Modo | Cuándo | Qué cambia en lo generado |
|---|---|---|
| `single-app` | Una aplicación, una base de datos | Sin contextos: `--context` no se pasa |
| `multitenant` | Una aplicación central y varios inquilinos | Cada archivo vive bajo la carpeta de su contexto y lleva su prefijo |

```bash
php artisan innodite:module-setup --mode=single-app
php artisan innodite:module-setup --mode=multitenant --tenancy=stancl
```

## ⛔ Ningún comando adivina el modo

Y ninguno asume uno por defecto: sin modo elegido se niegan a generar y dicen cómo elegirlo.

Es la decisión de diseño más importante del paquete. Un valor por defecto puesto «para que nadie
note nada» no produce un error: produce una **estructura equivocada, multiplicada por cada módulo**
que se genere a partir de ahí, y descubierta mucho después.

## En multiinquilino hay dos contextos, y solo dos

| Contexto | Dónde vive | Archivo de rutas | Permiso | ¿Declara conexión el modelo? |
|---|---|---|---|---|
| `central` | La aplicación que administra a los demás | `web.php` | `central-permission` | **Sí** |
| `tenant` | Lo que corre dentro de cada inquilino | `tenant.php` | `tenant-permission` | **No** |

⭐ **Y esa última columna es la que más cara sale equivocar.** El modelo del inquilino **no** nombra
su conexión, porque quien la conmuta es el paquete de tenencia al identificar la petición.
Escribirla ahí ata el modelo a una base concreta y rompe exactamente lo que protege.

## Si tu proyecto necesita otro contexto, lo declara

El paquete trae dos de fábrica y no inventa más. Un proyecto que necesite uno propio —un eje de
informes, una consola de soporte— lo añade a su `module-maker-config/contexts.json` con lo que ese
contexto es:

```json
"reporting": {
    "id": "reporting",
    "is_tenant": false,
    "folder": "Reporting",
    "route_file": "web.php",
    "connection_key": "reporting",
    "permission_prefix": "reporting_",
    "permission_middleware": "reporting-permission"
}
```

Y a partir de ahí `--context=reporting` genera como cualquier otro: la carpeta, el prefijo de las
clases, el archivo de rutas, el permiso de cada una y la conexión salen **de lo que declaraste**, no
de una lista escrita dentro del paquete.

⚠️ Lo que no puedes es **quitar** `central` ni `tenant`: el diagnóstico los sigue reclamando. Un
contexto propio se añade, no sustituye.

## El paquete de tenencia también se declara

```bash
php artisan innodite:module-setup --mode=multitenant --tenancy=stancl
```

Los archivos de rutas de un proyecto multiinquilino necesitan una envoltura, y esa envoltura está
escrita en el vocabulario del paquete de tenencia concreto. Si el tuyo no está soportado, declara
`--tenancy=none`: el generador **no inventa** la envoltura y deja el archivo con una nota que dice
dónde va y qué haría, en vez de escribir algo que parece correcto y no lo es.
