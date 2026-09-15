```bash
php artisan innodite:module-setup
```

### Qué hace

Configura el proyecto para poder generar. Sin modo elegido, ningún otro comando del paquete genera
nada: se niegan y dicen cómo elegirlo. Se ejecuta una sola vez, al inicializar el proyecto.

Si se ejecuta sin `--mode`, pregunta de forma interactiva en consola.

### Parámetros

| Parámetro | Valores | Efecto |
|---|---|---|
| `--mode=` | `single-app` \| `multitenant` | El modo del proyecto |
| `--tenancy=` | `stancl` \| `none` | Solo con `--mode=multitenant`. `none` deja el archivo de rutas del contexto `tenant` con una nota en vez de la envoltura de tenencia |
| `--frontend=` | `default` \| `innodite` | Contra qué juego de stubs de vistas se genera |

### Qué genera

```
module-maker-config/
├── contexts.json
└── stubs/
    └── contextual/        (vacía)
```

`contexts.json` en `--mode=multitenant`:

```json
{
  "contexts": {
    "central": {
      "id": "central", "is_tenant": false, "tenancy_strategy": "manual",
      "connection_key": "central", "class_prefix": "Central", "folder": "Central",
      "namespace_path": "Central", "route_file": "web.php",
      "route_prefix": "central", "route_name": "central."
    },
    "tenant": {
      "id": "tenant", "is_tenant": true, "class_prefix": "Tenant", "folder": "Tenant",
      "namespace_path": "Tenant", "route_file": "tenant.php",
      "route_prefix": "tenant", "route_name": "tenant."
    }
  }
}
```

En `--mode=single-app`, `contexts.json` no lleva la clave `contexts`.

### Ejemplos

**Caso: proyecto nuevo, una sola aplicación.**
```bash
php artisan innodite:module-setup --mode=single-app
```

**Caso: proyecto multiinquilino con `stancl/tenancy` ya instalado.**
```bash
php artisan innodite:module-setup --mode=multitenant --tenancy=stancl
```

**Caso: multiinquilino con un paquete de tenencia que el generador no soporta.**
```bash
php artisan innodite:module-setup --mode=multitenant --tenancy=none
```
El archivo de rutas del contexto `tenant` queda con una nota indicando dónde va la envoltura y qué
haría, sin escribirla.
