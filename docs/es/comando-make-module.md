```bash
php artisan innodite:make-module {name} {--context=} {--json} [-M] [-C] [-S] [-R] [-G] [-Q] {--dry-run}
```

### Qué hace

Genera un módulo completo, o solo algunas de sus capas sobre uno que ya existe. El comando decide
el modo por este orden de prioridad:

- **`--json`** — gana sobre cualquier otro flag. Lee la configuración de un archivo.
- **Algún flag suelto** (`-M -C -S -R -G -Q`) — genera solo esas capas.
- **Sin flags y el módulo no existe** — genera el módulo completo.
- **Sin flags y el módulo ya existe** — el comando se niega y sugiere `add-entity` o pasar flags
  sueltos. Nunca sobrescribe un módulo completo por accidente.

**Con flags sueltos combinados**, hay una regla no evidente: si las **seis** están activas a la vez
(sea porque se pasaron las seis explícitas, o porque no se pasó ninguna en `add-entity`), se agrega
además un paso de cierre que genera tests, factory, vistas, rutas y el `ServiceProvider`. Con un
subconjunto (por ejemplo, solo `-M -Q`), se generan **exclusivamente** esas capas — sin rutas, sin
vistas, sin tests.

`-G` (migración) nunca genera solo la migración: las seis piezas de seeder (`MigrationsList`,
`InlineAlters`, `StageSeeder`, `ProductionSeeder`, `PermissionsSeeder`, `Data`) van siempre juntas,
más el registro del módulo en el orden de despliegue.

### Parámetros

| Parámetro | Descripción |
|---|---|
| `name` | Nombre del módulo, PascalCase (ej. `Invoice`). Rechaza nombres que no sean PascalCase y palabras reservadas de PHP/Laravel |
| `--context=` | `central` \| `tenant` \| el que declare el proyecto. Obligatorio en `multitenant`; no se pasa en `single-app` |
| `--json` | Lee `module-maker-config/{módulo}.json` en vez de generar por flags. No genera vistas Vue |
| `-M` / `--model` | Solo el modelo |
| `-C` / `--controller` | Solo el controlador |
| `-S` / `--service` | Solo el servicio + su interfaz |
| `-R` / `--repository` | Solo el repositorio + su interfaz |
| `-G` / `--migration` | La migración + las seis piezas de seeder juntas (nunca solo la migración) |
| `-Q` / `--request` | Solo los form requests (alta y edición) |
| `--dry-run` | Registra qué escribiría, sin escribir nada. La validación de cada archivo (placeholders resueltos, PHP válido, namespace correcto) se ejecuta igual |

Sin ningún flag de componente y con el módulo ya existente, ver punto 4 de arriba.

### Personalizar stubs

Orden real de resolución, de más específico a más genérico:

| # | Ruta | Gana |
|---|---|---|
| 1 | `module-maker-config/stubs/contextual/{Contexto}/` | El proyecto, para ese contexto |
| 2 | `module-maker-config/stubs/contextual/` | El proyecto |
| 3 | `vendor/*/*/stubs/module-maker/contextual/` | Un paquete instalado (empate: gana el primero por orden alfabético) |
| 4 | `stubs/contextual/` del propio paquete | Fuente de última instancia |

Cada placeholder se escribe con **triple llave** (`{{{ modelName }}}`); antes de escribir, el
comando rechaza el archivo si queda algún placeholder sin resolver, si el PHP no parsea, o si el
namespace declarado no coincide con la ruta destino.

`provider-boot.stub` es opcional: si ningún nivel lo aporta, el `boot()` del `ServiceProvider` sale
vacío — es el punto de enganche al menú de la aplicación, y el paquete no lo trae de fábrica.

### Aplicación única

`php artisan innodite:make-module Invoice` genera, bajo `Modules/Invoice/`:

```
Docs/history.md
Docs/architecture.md
Docs/schema.md
Providers/InvoiceServiceProvider.php
Routes/web.php
Database/Seeders/Application/InvoiceApplicationStageSeeder.php
Database/Seeders/Application/InvoiceApplicationProductionSeeder.php
Database/Seeders/Application/InvoiceApplicationPermissionsSeeder.php

Invoice/Models/Invoice.php
Invoice/Http/Controllers/InvoiceController.php
Invoice/Http/Requests/InvoiceStoreRequest.php
Invoice/Http/Requests/InvoiceUpdateRequest.php
Invoice/Services/InvoiceService.php
Invoice/Services/Contracts/InvoiceServiceInterface.php
Invoice/Repositories/InvoiceRepository.php
Invoice/Repositories/Contracts/InvoiceRepositoryInterface.php
Invoice/Database/Migrations/{timestamp}_create_invoices_table_final.php
Invoice/Database/Seeders/InvoiceInvoiceMigrationsList.php
Invoice/Database/Seeders/InvoiceInvoiceInlineAlters.php
Invoice/Database/Seeders/InvoiceInvoiceStageSeeder.php
Invoice/Database/Seeders/InvoiceInvoiceProductionSeeder.php
Invoice/Database/Seeders/InvoiceInvoicePermissionsSeeder.php
Invoice/Database/Seeders/InvoiceInvoiceData.php
Invoice/Database/Factories/InvoiceFactory.php
Invoice/Tests/Feature/InvoiceContract.php
Invoice/Tests/Feature/InvoiceTestCase.php
Invoice/Tests/Feature/InvoiceScaffoldTest.php
Invoice/Tests/Feature/InvoiceSchemaTest.php
Invoice/Tests/Feature/InvoicePermissionsTest.php
Invoice/Tests/Feature/InvoiceDeploymentTest.php
Invoice/Tests/Feature/InvoiceHttpTest.php
Invoice/resources/js/__tests__/InvoiceContract.js
Invoice/resources/js/__tests__/InvoiceIndex.test.js
Invoice/resources/js/Pages/InvoiceIndex.vue
Invoice/resources/js/Pages/InvoiceCreate.vue
Invoice/resources/js/Pages/InvoiceEdit.vue
Invoice/resources/js/Pages/InvoiceShow.vue
Invoice/Jobs/.gitkeep
Invoice/Notifications/.gitkeep
Invoice/Console/Commands/.gitkeep
Invoice/Exceptions/.gitkeep
```

29 archivos. Además, `config/make-module.php` recibe la línea `'Invoice/Invoice',` dentro del array
`deploy`.

⚠️ **La primera subfuncionalidad de un módulo se llama igual que el módulo.** Por eso los nombres de
las piezas de seeder salen con el nombre repetido (`InvoiceInvoiceStageSeeder.php`) — no es un
error, es la convención real de nombrado (`{Módulo}{Subfuncionalidad}`, y aquí ambos son `Invoice`).
El modelo, el controlador, el servicio, el repositorio, los form requests, la factory y las vistas
**no** duplican el nombre.

### Multiinquilino

Mismo árbol, con `Central/` (o `Tenant/`) insertado como hoja de cada capa y el prefijo de clase
correspondiente. `--context=central`:

```
Docs/history.md · architecture.md · schema.md          (sin contexto, igual en los dos modos)
Providers/Central/CentralInvoiceServiceProvider.php
Routes/web.php
Database/Seeders/Application/Central/CentralInvoiceApplicationStageSeeder.php
Database/Seeders/Application/Central/CentralInvoiceApplicationProductionSeeder.php
Database/Seeders/Application/Central/CentralInvoiceApplicationPermissionsSeeder.php

Invoice/Models/Central/CentralInvoice.php
Invoice/Http/Controllers/Central/CentralInvoiceController.php
Invoice/Http/Requests/Central/CentralInvoiceStoreRequest.php
Invoice/Http/Requests/Central/CentralInvoiceUpdateRequest.php
Invoice/Services/Central/CentralInvoiceService.php
Invoice/Services/Contracts/Central/CentralInvoiceServiceInterface.php
Invoice/Repositories/Central/CentralInvoiceRepository.php
Invoice/Repositories/Contracts/Central/CentralInvoiceRepositoryInterface.php
Invoice/Database/Migrations/Central/{timestamp}_create_invoices_table_final.php
Invoice/Database/Seeders/Central/CentralInvoiceInvoiceMigrationsList.php
Invoice/Database/Seeders/Central/CentralInvoiceInvoiceInlineAlters.php
Invoice/Database/Seeders/Central/CentralInvoiceInvoiceStageSeeder.php
Invoice/Database/Seeders/Central/CentralInvoiceInvoiceProductionSeeder.php
Invoice/Database/Seeders/Central/CentralInvoiceInvoicePermissionsSeeder.php
Invoice/Database/Seeders/Central/CentralInvoiceInvoiceData.php
Invoice/Database/Factories/Central/CentralInvoiceFactory.php
Invoice/Tests/Feature/Central/CentralInvoiceContract.php
Invoice/Tests/Feature/Central/CentralInvoiceTestCase.php
Invoice/Tests/Feature/Central/CentralInvoiceScaffoldTest.php
Invoice/Tests/Feature/Central/CentralInvoiceSchemaTest.php
Invoice/Tests/Feature/Central/CentralInvoicePermissionsTest.php
Invoice/Tests/Feature/Central/CentralInvoiceDeploymentTest.php
Invoice/Tests/Feature/Central/CentralInvoiceHttpTest.php
Invoice/resources/js/__tests__/Central/CentralInvoiceContract.js
Invoice/resources/js/__tests__/Central/CentralInvoiceIndex.test.js
Invoice/resources/js/Pages/Central/CentralInvoiceIndex.vue
Invoice/resources/js/Pages/Central/CentralInvoiceCreate.vue
Invoice/resources/js/Pages/Central/CentralInvoiceEdit.vue
Invoice/resources/js/Pages/Central/CentralInvoiceShow.vue
Invoice/Jobs/Central/.gitkeep
Invoice/Notifications/Central/.gitkeep
Invoice/Console/Commands/Central/.gitkeep
Invoice/Exceptions/Central/.gitkeep
```

El modelo lleva `protected $connection = 'central';`. `config/make-module.php` recibe
`'Invoice/Central/Invoice',` dentro de `deploy['central']`.

**Con `--context=tenant`, no es solo cambiar el prefijo.** Además:
- Las rutas van a `Routes/tenant.php`, no `web.php`.
- La envoltura de middleware es la del paquete de tenencia (identificación por dominio), en vez del
  recorrido de dominios centrales.
- El modelo **no** declara `$connection` — la conmuta el paquete de tenencia; sin uno configurado,
  el modelo lleva una nota explicando quién debe conmutarla.
- El prefijo de permiso y middleware son `tenant`/`tenant-permission`, no `central`/`central-permission`.

Fuera de eso, la estructura es idéntica: mismas 29 piezas.

### Ejemplos

**Caso: primer módulo del proyecto, contexto central.**
```bash
php artisan innodite:make-module Invoice --context=central
```
```
✔ Generado Modules/Invoice/ (29 archivos)
✔ Registrado en el orden de despliegue: Invoice/Central/Invoice
```

**Caso: aplicación de un solo tenant.**
```bash
php artisan innodite:make-module Invoice
```

**Caso: al módulo `Invoice` ya generado le falta el repositorio y la migración.**
```bash
php artisan innodite:make-module Invoice --context=central -R -G
```
```
✔ Repositorio + interfaz generados
✔ Migración + seis piezas de seeder generadas
✔ Registrado en el orden de despliegue
```
No genera rutas, vistas ni tests — solo lo pedido.

**Caso: ver qué escribiría, antes de generar de verdad.**
```bash
php artisan innodite:make-module Invoice --context=central --dry-run
```
```
ENSAYO (--dry-run): no se va a escribir ni a ejecutar nada.
(ensayo) escribiría Modules/Invoice/Models/Central/CentralInvoice.php
(ensayo) escribiría Modules/Invoice/Http/Controllers/Central/CentralInvoiceController.php
… (29 líneas)
Esto es lo que haría (29). No se escribió nada.
```
