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

- **`Contracts\TenantContext`**, con `Services\Tenancy\StanclTenantContext` detrás. El paquete ya no
  llama a la orden global `tenancy()` desde su código de despliegue: entra y sale del contexto de
  cada cliente a través del contrato. Si usas otro paquete de tenencia, ahí está el punto donde
  engancharlo.

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
