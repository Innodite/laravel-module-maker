Lo que el paquete escribe tiene una sola forma, y conviene leerla una vez:

```
Modules/<Módulo>/<SubFuncionalidad>/<Capa>/<Contexto>/<Archivo>
```

**Manda la subfuncionalidad, la capa va dentro de ella y el contexto es la hoja.** En aplicación
única el último tramo no existe y el resto es idéntico, así que los dos modos **comparten**
estructura en vez de parecerse.

## Un módulo generado, de verdad

Esto es lo que deja `innodite:make-module Invoice --context=central`, medido sobre la ejecución real:

```
Modules/Invoice/
├── Docs/                                    ← architecture · history · schema
├── Routes/web.php
├── Providers/Central/CentralInvoiceServiceProvider.php
├── Database/Seeders/Application/Central/    ← los tres maestros del módulo
│
└── Invoice/                                 ← la subfuncionalidad
    ├── Models/Central/CentralInvoice.php
    ├── Http/Controllers/Central/CentralInvoiceController.php
    ├── Http/Requests/Central/CentralInvoiceStoreRequest.php
    ├── Http/Requests/Central/CentralInvoiceUpdateRequest.php
    ├── Services/Central/CentralInvoiceService.php
    ├── Services/Contracts/Central/CentralInvoiceServiceInterface.php
    ├── Repositories/Central/CentralInvoiceRepository.php
    ├── Repositories/Contracts/Central/CentralInvoiceRepositoryInterface.php
    ├── Database/Migrations/Central/…_create_invoices_table_final.php
    ├── Database/Seeders/Central/            ← las seis piezas de datos
    ├── Database/Factories/Central/CentralInvoiceFactory.php
    ├── resources/js/Pages/Central/          ← Index · Show · Create · Edit
    ├── resources/js/__tests__/Central/
    └── Tests/Feature/Central/               ← las siete piezas del contrato
```

**37 archivos.** Y en aplicación única salen **los mismos 37**, sin el tramo `Central/` y sin el
prefijo en los nombres: `Invoice/Services/InvoiceService.php`.

## Qué queda al nivel del módulo, y por qué

Cuatro cosas no pertenecen a ninguna subfuncionalidad concreta:

| | |
|---|---|
| `Docs/` | Documenta el módulo entero |
| `Routes/` | Un archivo por contexto — `web.php` para el central, `tenant.php` para el inquilino |
| `Providers/<Contexto>/` | **Uno por contexto.** Con uno solo, ese archivo era el único sitio del módulo donde los contextos se mezclaban, justo el que decide qué implementación se inyecta |
| `Database/Seeders/Application/<Contexto>/` | Los tres maestros que levantan el módulo entero |

## Por qué la subfuncionalidad va delante

Con la capa por delante —`Http/Controllers/Central/User/…`—, ver qué tiene una subfuncionalidad
obligaba a abrir doce carpetas; y con el contexto por delante, **cada capa duplicaba su rama entera
por contexto**.

Con este orden, una subfuncionalidad es **autocontenida**: se lee, se mueve y se borra de una pieza.
Y añadir un contexto no duplica el árbol, solo añade una hoja.

## Los contratos no llevan prefijo de contexto en el nombre

```
Invoice/Services/Contracts/Central/CentralInvoiceServiceInterface.php
```

La carpeta del contexto está —es la hoja, aquí como en todas las capas—, y `Contracts/` se intercala
antes. Cada implementación tiene su contrato en su contexto, que es lo que permite inyectar una u
otra según dónde corra el código.
