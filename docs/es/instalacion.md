```bash
composer require innodite/laravel-module-maker:^5.1
```

Instala el paquete vía Composer. El siguiente paso es `innodite:module-setup`, que elige el modo del
proyecto y deja escrita la configuración — ver su ficha en **Los comandos**.

Después de configurar el proyecto, `innodite:doctor` confirma que quedó bien:

```bash
php artisan innodite:doctor
```

Antes de generar el primer módulo, revisar **Modificaciones manuales**: hay piezas que el
generador no escribe por su cuenta.
