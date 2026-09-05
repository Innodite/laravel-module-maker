# Changelog

Todo cambio que afecte a quien usa el paquete. El formato sigue
[Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y las versiones,
[SemVer](https://semver.org/lang/es/).

## [Sin publicar]

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
