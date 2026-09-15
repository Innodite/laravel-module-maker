```bash
php artisan innodite:module-setup {--mode=} {--tenancy=} {--frontend=} {--dry-run}
```

### Qué hace

Configura el proyecto para poder generar. Sin modo elegido, ningún otro comando del paquete genera
nada. Orden real de ejecución:

- Resuelve el modo (`--mode` o pregunta interactiva) y lo persiste en `.env` como
  `MODULE_MAKER_MODE`. Sin modo resuelto, el comando **termina aquí**.
- Resuelve la tenencia — **solo si el modo es `multitenant`**; en `single-app` este paso se salta
  por completo, sin preguntar nada. La persiste como `MODULE_MAKER_TENANCY_PACKAGE`.
- Crea la carpeta de módulos (`Modules/` por defecto).
- Resuelve el frontend (`--frontend` o pregunta; `default` si no hay terminal). Nunca detiene el
  comando, a diferencia del modo y la tenencia. La persiste como `MODULE_MAKER_FRONTEND`.
- Crea `module-maker-config/`.
- Publica `config/make-module.php` — **solo si no existe ya**, nunca sobrescribe.
- Publica `contexts.json` — **solo en `multitenant`**, y solo si no existe ya.
- Escribe los seeders de despliegue del proyecto y engancha el primero en `DatabaseSeeder::run()`.

### Parámetros

| Parámetro | Valores | Efecto |
|---|---|---|
| `--mode=` | `single-app` \| `multitenant` | Obligatorio para poder generar. Sin valor válido y sin terminal interactiva, el comando falla |
| `--tenancy=` | `stancl` \| `none` | Solo se usa en `multitenant` — ignorado por completo en `single-app`. `stancl` envuelve las rutas del contexto `tenant` con los middleware de identificación por dominio; `none` deja el archivo con un aviso de qué envoltura falta, sin adivinarla |
| `--frontend=` | `default` \| `innodite` | Contra qué se generan las vistas. Un valor inválido no detiene la instalación: avisa y sigue con `default` |
| `--dry-run` | — | Ninguna escritura ocurre, tampoco en `.env`. Ver más abajo |

### Qué genera

```
Modules/                              (carpeta vacía)
module-maker-config/
config/make-module.php                (si no existía)
module-maker-config/contexts.json     (solo multitenant, si no existía)
database/seeders/*.php                (seeders de despliegue del proyecto)
```

Y modifica `.env` (tres líneas: `MODULE_MAKER_MODE`, `MODULE_MAKER_TENANCY_PACKAGE` en multitenant,
`MODULE_MAKER_FRONTEND`) y `database/seeders/DatabaseSeeder.php` (agrega una llamada dentro de
`run()`).

**Los stubs NO se publican con este comando** — solo `innodite:publish-stubs` los publica.

### Aplicación única

`contexts.json` no se publica — no hay eje de contexto que declarar. La tenencia no se pregunta.

`--mode=single-app`:
```
✔ Modo configurado: single-app
✔ Frontend: default
✔ config/make-module.php publicada
✔ Seeders de despliegue escritos
Configuración completa.
```

### Multiinquilino

`contexts.json` se publica con **dos** contextos de fábrica:

```json
{
    "contexts": {
        "central": {
            "id": "central", "is_tenant": false, "tenancy_strategy": "manual",
            "connection_key": "central", "class_prefix": "Central", "folder": "Central",
            "namespace_path": "Central", "route_file": "web.php",
            "route_prefix": "central", "route_name": "central.",
            "permission_prefix": "", "permission_middleware": "", "route_middleware": []
        },
        "tenant": {
            "id": "tenant", "is_tenant": true,
            "class_prefix": "Tenant", "folder": "Tenant", "namespace_path": "Tenant",
            "route_file": "tenant.php", "route_prefix": "tenant", "route_name": "tenant.",
            "permission_prefix": "", "permission_middleware": "", "route_middleware": []
        }
    }
}
```

El contexto `tenant` no declara `connection_key` ni `tenancy_strategy` — la conmuta el paquete de
tenencia elegido.

`--mode=multitenant --tenancy=stancl`:
```
✔ Modo configurado: multitenant
✔ Tenencia: stancl
✔ Frontend: default
✔ contexts.json publicado con central y tenant
✔ Seeders de despliegue escritos
Configuración completa.
```

Con `--tenancy=none`, el archivo de rutas del contexto `tenant` queda con un aviso en vez de la
envoltura de middleware:
```
// ⛔ FALTA LA ENVOLTURA DE TENENCIA — este proyecto declaró --tenancy=none.
// Aquí va el middleware que identifica al inquilino antes de resolver estas rutas.
```

### Ejemplos

**Caso: proyecto nuevo, una sola aplicación.**
```bash
php artisan innodite:module-setup --mode=single-app
```

**Caso: multiinquilino con `stancl/tenancy` ya instalado.**
```bash
php artisan innodite:module-setup --mode=multitenant --tenancy=stancl
```

**Caso: multiinquilino con un paquete de tenencia que el generador no soporta.**
```bash
php artisan innodite:module-setup --mode=multitenant --tenancy=none
```

**Caso: ver qué haría, sin tocar nada.**
```bash
php artisan innodite:module-setup --mode=multitenant --tenancy=stancl --dry-run
```
```
ENSAYO (--dry-run): no se va a escribir ni a ejecutar nada.
(ensayo) crearía Modules/
(ensayo) escribiría MODULE_MAKER_MODE=multitenant en .env
(ensayo) escribiría MODULE_MAKER_TENANCY_PACKAGE=stancl en .env
(ensayo) copiaría config/make-module.php
(ensayo) copiaría module-maker-config/contexts.json
Esto es lo que haría (5). No se escribió nada.
```
