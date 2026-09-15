```bash
php artisan innodite:make-module {Name} {--context=} [-M] [-C] [-S] [-R] [-G] [-Q] [--json] [--dry-run]
```

### Qué hace

Genera un módulo completo: modelo, repositorio, servicio, controlador, form requests, migración,
seeders, factory, rutas (con el permiso de cada una inyectado en el archivo del contexto), pantallas
Vue y el grupo de pruebas. Declara el módulo en el orden de despliegue.

En `multitenant`, exige `--context`; sin él, no genera nada. En `single-app`, no se pasa contexto.

Con flags de componente (`-M`, `-C`, `-S`, `-R`, `-G`, `-Q`), genera solo esas capas sobre un módulo
que ya existe, sin tocar las demás.

### Parámetros

| Parámetro | Descripción |
|---|---|
| `Name` | Nombre del módulo, PascalCase (ej. `Invoice`). Rechaza nombres que no sean PascalCase y palabras reservadas de PHP/Laravel |
| `--context=` | `central` \| `tenant` \| el que declare el proyecto. Obligatorio en `multitenant` |
| `-M` / `--model` | Solo el modelo Eloquent |
| `-C` / `--controller` | Solo el controlador + inyección de rutas CRUD |
| `-S` / `--service` | Solo el servicio + su interfaz |
| `-R` / `--repository` | Solo el repositorio + su interfaz |
| `-G` / `--migration` | Solo la migración |
| `-Q` / `--request` | Solo los form requests (alta y edición) |
| `--json` | Configuración dinámica por JSON en vez de flags |
| `--dry-run` | Muestra qué escribiría, sin escribir nada |

Sin ningún flag de componente, genera todos.

### Qué genera

```
Modules/Invoice/
├── Routes/web.php
├── Providers/Central/CentralInvoiceServiceProvider.php
├── Database/Seeders/Application/Central/
│
└── Invoice/
    ├── Models/Central/CentralInvoice.php
    ├── Http/Controllers/Central/CentralInvoiceController.php
    ├── Http/Requests/Central/CentralInvoiceStoreRequest.php
    ├── Http/Requests/Central/CentralInvoiceUpdateRequest.php
    ├── Services/Central/CentralInvoiceService.php
    ├── Services/Contracts/Central/CentralInvoiceServiceInterface.php
    ├── Repositories/Central/CentralInvoiceRepository.php
    ├── Repositories/Contracts/Central/CentralInvoiceRepositoryInterface.php
    ├── Database/Migrations/Central/…_create_invoices_table_final.php
    ├── Database/Seeders/Central/          (las seis piezas)
    ├── Database/Factories/Central/CentralInvoiceFactory.php
    ├── resources/js/Pages/Central/        (Index · Show · Create · Edit)
    ├── resources/js/__tests__/Central/
    └── Tests/Feature/Central/             (las seis piezas del contrato + TestCase)
```

37 archivos por módulo con una subfuncionalidad en un contexto. En `single-app`, los mismos 37, sin
el tramo `Central/` y sin el prefijo de contexto en los nombres de clase.

Al terminar, reporta si el módulo no va a cargar (namespace sin declarar o autoload sin recargar) y
si su pantalla no va a abrir (Ziggy sin configurar).

### Personalizar stubs

El comando lee las plantillas en este orden, de más específico a más genérico:

| # | Ruta | Gana |
|---|---|---|
| 1 | `module-maker-config/stubs/contextual/{Contexto}/` | El proyecto, para ese contexto |
| 2 | `module-maker-config/stubs/contextual/` | El proyecto |
| 3 | `vendor/*/*/stubs/module-maker/contextual/` | Un paquete instalado |
| 4 | Las del paquete | Por defecto |

Publicar plantillas para editarlas: ver la ficha de `innodite:publish-stubs`.

### Ejemplos

**Caso: primer módulo del proyecto, contexto central.**
```bash
php artisan innodite:make-module Invoice --context=central
```

**Caso: el mismo módulo, para el contexto del inquilino.**
```bash
php artisan innodite:make-module Invoice --context=tenant
```

**Caso: aplicación de un solo tenant — sin eje de contexto que elegir.**
```bash
php artisan innodite:make-module Invoice
```

**Caso: al módulo `Invoice` ya generado le falta el repositorio y la migración.**
```bash
php artisan innodite:make-module Invoice --context=central -R -G
```

**Caso: ver qué archivos escribiría, antes de generar de verdad.**
```bash
php artisan innodite:make-module Invoice --context=central --dry-run
```
