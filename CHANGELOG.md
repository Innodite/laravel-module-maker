# Changelog

Todo cambio que afecte a quien usa el paquete. El formato sigue
[Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y las versiones,
[SemVer](https://semver.org/lang/es/).

## [Sin publicar] — v4

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

---

## Versiones anteriores

El detalle de la v3 y del salto a la v4 está en la
**[guía de migración](docs/migracion-v3-a-v4.md)**, que recorre el cambio paso a paso.
