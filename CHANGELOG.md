# Changelog

Todo cambio que afecte a quien usa el paquete. El formato sigue
[Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y las versiones,
[SemVer](https://semver.org/lang/es/).

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
