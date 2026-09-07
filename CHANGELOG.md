# Changelog

Todo cambio que afecte a quien usa el paquete. El formato sigue
[Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y las versiones,
[SemVer](https://semver.org/lang/es/).

## [5.0.0] — 07/09/2026

**Versión MAYOR**, y el motivo es uno solo: **cambia la forma de todo lo que el paquete escribe**.
Un módulo generado con una 4.x no coincide con lo que genera esta, ni en carpetas ni en namespaces,
así que actualizar sin mover nada deja el proyecto con dos formas dentro. La 4.2.0 sigue instalable
para quien no quiera moverse todavía.

### Cambiado — ⚠️ ROMPE lo generado con una 4.x

- **El árbol cambia de orden: `Modules/<Módulo>/<SubFuncionalidad>/<Capa>/<Contexto>/<Archivo>`.**

  Antes la capa iba delante y el contexto en medio
  (`Http/Controllers/Central/User/CentralUserController.php`). Ahora **la subfuncionalidad manda, la
  capa va dentro de ella y el contexto es la hoja**
  (`User/Http/Controllers/Central/CentralUserController.php`).

  Con la capa por delante, ver qué tiene una subfuncionalidad pedía abrir doce carpetas; y con el
  contexto por delante, **cada capa duplicaba su rama entera por contexto**. En aplicación única el
  último tramo no existe y el resto es idéntico: los dos modos comparten estructura en vez de
  parecerse.

  Suben al **nivel del módulo** los tres seeders maestros (`Database/Seeders/Application/<Ctx>/`) y
  el ServiceProvider, que además pasa a ser **uno por contexto**: con uno solo, ese archivo era el
  único sitio del módulo donde los contextos se mezclaban — justo el que decide qué implementación
  se inyecta.

- **Un solo modo multiinquilino: `multitenant`.** `multitenant-shared` y `multitenant-per-tenant` se
  retiran. Toda su diferencia era que el segundo **nombraba** a cada inquilino y le **declaraba
  conexión**, y las dos mitades resultaron equivocadas: nombrarlo multiplica lógica idéntica por
  cliente, y declarar la conexión ata el modelo a una base cuando quien la conmuta es el middleware
  en cada petición.

  Un proyecto que declare un modo retirado **no se traduce en silencio**: los comandos se niegan y
  dicen qué escribir, por qué, y cómo declarar un contexto propio en su `contexts.json`.

- **Dos contextos de fábrica: `central` y `tenant`.** Se retiran `shared`, `tenant_shared` y la lista
  de inquilinos nombrados. Un proyecto que necesite otro contexto lo declara en el suyo.

- **El modelo del inquilino ya no declara `$connection`.** El central sí. Y si el proyecto declara
  `tenancy.package = none`, el del inquilino sale con una **nota** que dice quién tiene que
  conmutarla — no con una conexión inventada.

- **Las rutas del inquilino** pasan de `tenant-one.invoices.*` a `tenant.users.*`, y el marcador de
  inyección lo decide el **archivo** (`{{CENTRAL_ROUTES_END}}`, `{{TENANT_ROUTES_END}}`) en vez del id
  del cliente.

### Corregido

- **`--context=tenant_shared` generaba un módulo inalcanzable.** Su `tenant.php` no nombraba ni una
  vez al controlador que ese mismo comando escribía: declaraba un bloque por cada cliente del
  catálogo, importando clases que nadie había generado. La subfuncionalidad quedaba **sin una sola
  ruta**, y las que había respondían 500 al entrar.

- **`add-entity` dejaba la subfuncionalidad a medias.** No escribía **las rutas** —el controlador
  quedaba inalcanzable—, ni **la vista**, ni el **provider** de su contexto, ni la factory. Y sí
  escribía la prueba de esa vista, que la importa: el grupo nacía rojo apuntando a un archivo
  inexistente. Y es el caso normal: en un módulo de varias subfuncionalidades, todas menos la
  primera entran por esta puerta.

- **`innodite:deploy` no encontraba ningún seeder** tras el cambio de árbol, y **decidía si podía
  entrar en el contexto de un cliente preguntando por una función global** en vez de por su propio
  contrato: un proyecto con su propia implementación de `Contracts\TenantContext` no podía desplegar
  aunque la tuviera registrada.

- **`innodite:test`** anunciaba «no hay grupo de pruebas» sobre una subfuncionalidad que lo tiene
  entero.

- **El aviso de «esta subfuncionalidad no la despliega nadie»** dejó de salir: el cruce no encontraba
  ninguna en disco.

- **`innodite:doctor` no veía ninguna migración**, así que la comprobación de migraciones duplicadas
  pasaba siempre — y una tabla creada dos veces en el mismo contexto llegaba a producción.

- **Un `--context` mal escrito** reventaba con un error interno del generador en vez del mensaje con
  su FIX.

- **`--dry-run` escribía de verdad** y fallaba.

- **Trece mensajes** componían a mano la ruta que enseñan y decían una carpeta distinta de donde
  estaba el archivo. Ahora la derivan del archivo escrito.

### Retirado

- Los **archivos de ejemplo** de `Jobs`, `Notifications`, `Console/Commands` y `Exceptions`. Se crean
  **las carpetas, vacías**, con un `.gitkeep`: la estructura enseña dónde va cada cosa sin sembrar
  código que hay que borrar. Con ellos se retiran sus cuatro generadores y sus plantillas.

### Añadido

- **El modo del frontend deja de ser un interruptor mudo.** `--frontend=innodite` escribía una clave
  que **ningún generador leía**: elegirlo producía exactamente lo mismo que `default`.

  Ahora `make-module` **avisa** cuando el proyecto declara `innodite` y ningún paquete instalado
  aporta esas plantillas, en vez de escribir las genéricas y terminar en verde. Es el peor resultado
  posible el que se evita: pedir una cosa, obtener otra, y descubrirlo al abrir la pantalla con
  varios módulos ya generados. El `doctor` lo comprueba también, como punto 5 de su etapa 2.

  ⛔ El paquete **no trae** las plantillas de ninguna biblioteca: es público, y esa forma no se
  publica en un repositorio abierto. Las aporta quien las tiene, por el punto de extensión.

- **`frontend.layout`** ya se usa. Si el proyecto lo declara, la pantalla generada lo importa y lo
  declara con `defineOptions({ layout })` — dos líneas, **sin tocar el `<template>`**, que es como
  Inertia declara un layout persistente. Solo lo lleva el listado: las otras tres vistas son modales
  que viven dentro de él, y darles layout propio dibujaría la aplicación dentro de una ventana.

- **Dos accesos de despliegue con nombre propio**, en `database/seeders/`:
  `InnoditeStageSeeder` e `InnoditeProductionSeeder`.

  ```bash
  php artisan db:seed --class=InnoditeStageSeeder
  ```

  **Por qué.** El motor recibe la pieza por parámetro, lo cual está bien para el comando —que la
  pide explícitamente— y mal para `db:seed` y para el `DatabaseSeeder`: hay que acordarse de
  escribirla, y una llamada sin ella **despliega producción** creyendo que se pidió otra cosa. Con
  dos archivos, el nombre es la respuesta.

  ⛔ **No repiten la lista de módulos.** Cada uno declara su pieza y llama al motor; los módulos y su
  orden siguen saliendo de `config/make-module.php`, que es el único sitio donde se declaran —y
  donde `make-module` y `add-entity` registran cada subfuncionalidad al generarla. Escribir aquí las
  llamadas a cada maestro daría el mismo resultado hoy y **dos listas mañana**, de las que el día del
  módulo siguiente solo se actualizaría una.

### Cambiado

> **Nota de versión.** La v4 **todavía no se ha publicado ni está en uso**, así que estos cambios
> entran en la propia 4.x en vez de esperar a una mayor. Lo que se describe abajo como «qué se
> rompe» aplica a quien hubiera generado módulos con una 4.x preliminar.

- **Retirado el trait `RendersInertiaModule`.** El controlador generado llama ahora a
  `Inertia::render()` con la ruta literal de su vista, como cualquier controlador de Laravel.

  **Por qué.** La ruta se componía en **tiempo de ejecución**: en cada petición el trait leía
  `contexts.json`, deducía la carpeta desde el prefijo del nombre del componente y comprobaba que el
  archivo existiera. Eso metía los dos modos en una sola pieza —tenía que preguntar si el proyecto
  tenía eje de contexto para decidir si exigir prefijo— y ponía un `if` de configuración delante de
  cada pantalla, de modo que un error de configuración se convertía en una pantalla que no abre.

  El generador **ya sabe** en qué contexto escribe, porque es él quien elige la carpeta del `.vue`.
  Así que ahora escribe la ruta entera:

  ```php
  Inertia::render('Invoice::Invoice/InvoiceIndex');                        // aplicación única
  Inertia::render('Invoice::Central/Invoice/CentralInvoiceIndex');         // central
  Inertia::render('Invoice::Tenant/TenantOne/Invoice/TenantOneInvoiceIndex');
  ```

  ⚠️ **Qué se rompe.** Los controladores generados con una 4.x preliminar importan el trait. Al
  retirarlo, esas clases dejan de resolver y el proveedor del módulo revienta **en el arranque**: no
  es una pantalla caída, es la aplicación entera sin responder, incluido el `artisan` con el que se
  arreglaría. Se regeneran o se migran a mano con las cuatro líneas de abajo.

  **Cómo se migra**, controlador a controlador: quitar el `use` del trait y el `use
  RendersInertiaModule;` del cuerpo, añadir `use Inertia\Inertia;`, y sustituir
  `$this->renderModule('<Modulo>', '<ruta>')` por `Inertia::render('<Modulo>::<carpeta><ruta>')`,
  donde `<carpeta>` es la del contexto (vacía en aplicación única).

## [4.2.0] — 05/09/2026

**Por qué MINOR y no MAYOR.** Todo lo de abajo **añade** o **corrige**: nada cambia la forma de lo
que los comandos ya generaban, así que actualizar desde la 4.1 no obliga a tocar ningún módulo
existente. Los dos puntos de extensión nuevos —los stubs que otro paquete puede aportar y el
enganche del `boot()`— están **inactivos mientras nadie los use**: sin un paquete que aporte
plantillas, se genera exactamente lo mismo que antes.

### Añadido

- **Levantar un tenant recién creado desde tu propio código**, sin pasar por la consola:

  ```php
  use Innodite\LaravelModuleMaker\Services\TenantBootstrapper;

  $resultado = (new TenantBootstrapper($this->app))->bootstrap($tenant, 'production');

  if (! $resultado->successful()) {
      // Sabes QUÉ quedó aplicado, así que puedes deshacer el alta con criterio.
      $this->revertir($tenant, $resultado->applied());
  }
  ```

  Entra en el contexto del cliente, ejecuta el despliegue **en el orden que declaras** y devuelve un
  resultado con lo aplicado y lo fallido. Si ya estás dentro del contexto de ese tenant, lo respeta y
  no lo cierra al terminar.

  **Para qué.** Hasta ahora un tenant solo se levantaba con `innodite:deploy … --tenant=<clave>`, es
  decir, por consola y sobre uno que ya existía. Un alta de autoservicio ocurre dentro de una
  petición, sobre una base que acaba de nacer, y desde ahí no había a quién llamar.

  ⛔ **No devuelve «bien» cuando no puede levantar.** Si falta el orden de despliegue, si no está el
  seeder del proyecto o si la tenencia no se puede inicializar, **lanza**. Un alta que sale correcta
  con la base vacía no se descubre al desplegar: se descubre cuando el cliente entra.

- **`Services\DeploymentRunner`** y **`Support\DeploymentResult`**, que es lo que hace posible lo
  anterior: el despliegue deja de vivir dentro del comando y pasa a decir **hasta dónde llegó**.

- **`innodite:deploy --module=<Módulo>`** levanta **un módulo suelto** por sus maestros: el de la
  pieza que pidas y el de sus permisos. Sirve para desarrollo, para reparar un módulo concreto y
  para el alta de un tenant. Los maestros se resuelven del **orden de despliegue**, que es donde está
  escrito en qué contexto vive cada subfuncionalidad.

- **El borrado lógico, completo.** Hasta ahora el modelo generado traía `SoftDeletes` y la migración
  su columna, y ahí se acababa: **no había forma de deshacer un borrado** sin escribirla a mano en
  cuatro capas. Los módulos que generes a partir de ahora traen:

  | | |
  |---|---|
  | Rutas | `PATCH /{id}/restore` y `DELETE /{id}/force`, cada una con **su permiso** |
  | Repository | `findTrashed()`, `restore()`, `forceDelete()` |
  | Service | `restore()` y `forceDelete()`, que es donde va la cascada si la hay |
  | Vista | Botón **Restaurar**, visible solo en lo eliminado y con su propio permiso de vista |

  ⚠️ **Un módulo nuevo pasa de 6 rutas y 10 permisos a 8 y 13.** A los módulos que ya tienes no les
  pasa nada —`make-module` se niega sobre un módulo existente—, pero si mantienes a mano una lista
  de permisos, verás tres más por subfuncionalidad.

  ⛔ El **borrado definitivo** no lleva botón en la vista generada: existe como ruta con el permiso
  más restringido de los ocho. Un botón irreversible en la tabla principal, sin una papelera de por
  medio, es una trampa; dónde ponerlo lo decide tu proyecto.

- **`Contracts\TenantContext`**, con `Services\Tenancy\StanclTenantContext` detrás. El paquete ya no
  llama a la orden global `tenancy()` desde su código de despliegue: entra y sale del contexto de
  cada cliente a través del contrato. Si usas otro paquete de tenencia, ahí está el punto donde
  engancharlo.

- **Otro paquete instalado puede aportar sus stubs**, y el generador los usa sin configurar nada. El
  contrato es una ruta: cualquier paquete que traiga
  `stubs/module-maker/contextual/<nombre>.stub` participa en la resolución, por detrás de los del
  proyecto y por delante de los del paquete. `make-module` dice al terminar quién aportó qué, y avisa
  si dos aportan lo mismo.

  **Para qué.** Las plantillas del paquete son genéricas a propósito. Un proyecto montado sobre una
  biblioteca de interfaz querría generar contra ella —su tabla, sus formularios— y hasta ahora eso
  obligaba a copiar los stubs al proyecto y mantenerlos a mano, uno por uno y en cada proyecto.

- **La documentación del paquete, dentro del propio paquete** (`docs/`), declarada con
  `extra.innodite-docs`. Ocho fichas: instalación, elegir el modo, los comandos, crear un módulo,
  desplegar, las pruebas, personalizar lo generado y **cuándo NO usarlo**.

  Se escriben aquí a propósito: quien cambia un comando actualiza su ficha **en el mismo commit**.
  ⚠️ Están redactadas contra el código y no contra el README, así que corrigen algo que el README
  aún dice mal: los comandos vivos son **nueve**, y `innodite:migrate-plan` está **retirado**.

- **`provider-boot.stub`**, un stub **opcional** cuyo contenido se escribe en el `boot()` del
  ServiceProvider del módulo generado. No existe en el paquete: si no lo aporta nadie, el `boot()`
  sale vacío, exactamente como antes.

  **Para qué.** Enganchar el módulo recién generado en el menú de la aplicación. Esa línea el
  generador no puede escribirla —depende de cómo declare su menú cada proyecto—, y escribirla «por
  si acaso» llamaría a una clase que puede no existir: eso no deja un módulo invisible, deja la
  aplicación entera sin arrancar. Recibe `{{{ moduleName }}}` y `{{{ functionality }}}`.

### Corregido

- **El webmaster ya no asume que los identificadores son enteros.** Si tus tablas `roles`,
  `permissions` o `users` usan ULID —`char(26)`—, el seeder que escribe el instalador funcionaba mal
  en cuatro sitios, y **el peor no daba ningún error**: convertía el id del rol a entero, obtenía
  `0`, repartía todos los permisos a un rol inexistente y terminaba en verde.

  Ahora le pregunta al esquema si esa tabla espera que el id lo traiga quien inserta, y solo entonces
  lo genera. Sirve con las dos formas, sin ningún ajuste que mover.

- **`innodite:module-setup` publica `config/make-module.php`.** Antes solo llegaba con
  `vendor:publish`, y ahí es donde se declara el **orden de despliegue** — que no cabe en el `.env`.
  El propio despliegue te mandaba a un archivo que no existía en tu proyecto. ⛔ No pisa el que ya
  tengas.

- **El aviso «Primera instalación detectada» se calla cuando toca.** Miraba si existía la carpeta
  `module-maker-config/`, que no es la misma pregunta: seguía saliendo en cada comando después de
  instalar con éxito. Ahora mira si hay **modo elegido**, que es lo que el instalador deja hecho.

- **`innodite:deploy` no empieza si falta una tabla del esqueleto que tu proyecto va a usar** —
  `cache` si tu caché es de base de datos, `jobs` si tu cola lo es—. Antes fallaba en cadena y el
  primer error, el único que importaba, quedaba fuera de la pantalla. ⛔ No las crea: son de Laravel,
  no del generador; te dice que ejecutes `php artisan migrate`.

- **El diagnóstico comprueba Ziggy, del que dependen todas las pantallas generadas.** Las vistas
  piden sus rutas por el nombre —`route(contextRoute('invoices.list'))`—, y quien traduce ese nombre
  en el navegador es Ziggy. Si falta, o si está instalado pero ningún layout publica el mapa con
  `@routes`, la pantalla **muere al montarse**: `route is not defined`, antes de pintar nada, con un
  mensaje que no menciona ni al módulo ni al paquete. Y en el servidor no queda constancia de nada,
  porque la petición respondió 200.

  Ahora `innodite:doctor` comprueba **las dos mitades** por separado —instalarlo sin la directiva
  falla igual, y es lo que más se olvida— y `make-module` avisa al terminar si la pantalla que acaba
  de escribir no va a abrir. Es el gemelo del aviso que ya existía para el módulo que no carga: aquel
  mira el servidor, este el navegador.

- Las cuatro vistas generadas llaman a `window.route(...)` en vez de a `route(...)` a secas. La
  directiva `@routes` define la función en el ámbito global del navegador, y ese es el único punto de
  entrada que vale igual en las dos versiones de Ziggy —importarla de `ziggy-js` solo existe en la
  v2—. Es la misma razón por la que ya usaban `window.axios`.

### Cambiado

- `innodite:deploy --context=tenant`, cuando **algunos** tenants fallan, ahora dice **cuántos y
  cuáles**, y que a los demás no hace falta volver. Antes terminaba en error sin más detalle.

- `innodite:migrate-one` acompaña sus tres fallos —la coordenada que no resuelve, la conexión del
  contexto y la base inexistente— con el arreglo. Antes imprimía la excepción cruda.

## [4.1.0] — 05/09/2026

### Retirado

- **`innodite:migrate-plan`.** El esquema se aplica con `innodite:deploy`, que además siembra los
  datos y crea los permisos, en el orden que declaras en `deploy` dentro de `config/make-module.php`.

  El comando sigue registrado y te dice qué usar en su lugar, pero **devuelve error**: si lo tienes
  en un script de despliegue, ese script debe cambiar.

  ```bash
  # antes
  php artisan innodite:migrate-plan --context=central

  # ahora
  php artisan innodite:deploy stage --context=central
  ```

  **Por qué.** Ordenaba las subfuncionalidades por el nombre de sus carpetas, ignorando el orden que
  declaras: una tabla podía crearse antes que aquella a la que apunta. Y era el único punto del
  paquete que aplicaba migraciones fuera del seeder.

  ⚠️ **No es un cambio de nombre — `deploy` hace más.** Si tu script encadenaba `migrate-plan` con
  `db:seed`, ahora sobra la segunda mitad. Y comprueba el orden de tu `deploy`: es el único que manda,
  y `make-module` añade cada subfuncionalidad **al final**.

### Añadido

- **[Cómo se ejecutan las migraciones](docs/como-se-ejecutan-las-migraciones.md)** — quién decide el
  orden, por qué son dos niveles y qué no hacer.
- **Este archivo.** El README lo enlazaba desde hacía tiempo y no existía.
- **Bloque de soporte en `composer.json`** — dónde reportar un fallo, dónde está el código y dónde
  el manual. Quien llegaba desde el directorio de paquetes no lo tenía.

### Corregido

- **Los distintivos del README anunciaban requisitos que no son.** Decían PHP 8.3 y Laravel 11-12,
  cuando el paquete acepta **PHP 8.2** y **Laravel 11, 12 y 13** — y la integración continua ya
  probaba en 8.2. Si descartaste el paquete por su versión de PHP, vuelve a mirar.

### Retirado del repositorio

- Siete archivos de andamiaje interno que no le sirven a quien instala el paquete: notas de trabajo,
  un histórico de desarrollo y un archivo de instrucciones para herramientas de asistencia. **No
  afecta al código**: nada de lo retirado se cargaba en tiempo de ejecución.

### Por qué 4.1 y no 5.0

Lo que el generador **produce** no cambia: los mismos comandos escriben exactamente los mismos
archivos. ⚠️ Pero el comando retirado **devuelve error donde antes devolvía éxito**, y eso rompe un
guion de despliegue que lo invoque — a propósito, para que no siga adelante creyendo que no hay
migraciones que aplicar. Si lo tienes en un guion, cámbialo antes de actualizar.

---

## Versiones anteriores

El detalle de la v3 y del salto a la v4 está en la
**[guía de migración](docs/migracion-v3-a-v4.md)**, que recorre el cambio paso a paso.
