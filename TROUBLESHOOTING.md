# Guía de Diagnóstico — Carga Automática de Rutas

## ❌ Problema: "Las rutas de los módulos no se cargan automáticamente"

### 🔍 Diagnóstico Paso a Paso

#### **1. Verificar que ejecutaste el comando de setup**

Después de instalar el paquete, DEBES ejecutar:

```bash
php artisan innodite:module-setup
```

Este comando publica:
- ✅ `module-maker-config/contexts.json` — **Configuración de contextos**
- ✅ `module-maker-config/stubs/contextual/` — Stubs personalizables
- ✅ `config/make-module.php` — Configuración del paquete

**Sin `contexts.json`, el paquete NO puede generar rutas correctamente.**

---

#### **2. Verificar que `contexts.json` existe en tu proyecto**

Ruta esperada: `{tu-proyecto-laravel}/module-maker-config/contexts.json`

```bash
# Desde la raíz de tu proyecto Laravel:
ls -la module-maker-config/contexts.json
```

Si NO existe:
```bash
php artisan vendor:publish --tag=module-maker-contexts --force
```

---

#### **3. Verificar que se generaron los archivos de rutas del módulo**

Cuando ejecutas:
```bash
php artisan innodite:make-module User --context=central
```

Debe crear:
```
Modules/
  User/
    Routes/
      web.php       ← Debe existir (central y shared)
      tenant.php    ← Debe existir (tenants)
```

**Verifica que existan:**
```bash
ls -la Modules/User/Routes/
```

---

#### **4. Verificar el contenido del archivo `web.php` o `tenant.php`**

El archivo debe contener rutas válidas de Laravel. Ejemplo para central:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\Central\CentralUserController;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {

        Route::prefix('central-users')
            ->name('central.users.')
            ->group(function () {
                // Vista principal
                Route::get('/', [CentralUserController::class, 'index'])
                    ->name('index')
                    ->middleware('central-permission:central_users_index');

                // ... más rutas
            });
        
        // {{CENTRAL_END}}
    });
}
```

---

#### **5. Verificar que el ServiceProvider del paquete está cargando rutas**

El `LaravelModuleMakerServiceProvider` debe ejecutarse automáticamente al iniciar Laravel.

**Verifica que el paquete está en `composer.json`:**
```bash
composer show innodite/laravel-module-maker
```

**Verifica que el autoload está actualizado:**
```bash
composer dump-autoload
```

---

#### **6. Verificar que las rutas se registraron en Laravel**

```bash
php artisan route:list --path=central-users
```

Deberías ver rutas como:
```
GET|HEAD  central-users .............. central.users.index
POST      central-users .............. central.users.store
GET|HEAD  central-users/{id} ........ central.users.show
PUT       central-users/{id} ........ central.users.update
DELETE    central-users/{id} ........ central.users.destroy
```

---

### 🚨 Errores Comunes

#### **Error: `config('tenancy.central_domains') is undefined`**

**Solución:** El contexto `central` espera que tengas configurado Laravel Tenancy.

En `config/tenancy.php`, agrega:
```php
'central_domains' => [
    env('CENTRAL_DOMAIN', 'localhost'),
],
```

O modifica `contexts.json` para NO usar `wrap_central_domains`:
```json
{
    "central": [{
        "wrap_central_domains": false
    }]
}
```

---

#### **Error: Las rutas no aparecen en `route:list`**

**Posibles causas:**
1. El archivo `Modules/{Module}/Routes/web.php` no existe
2. El archivo tiene errores de sintaxis PHP
3. Composer no está cargando el `ServiceProvider`
4. El módulo está en una carpeta diferente a `Modules/`

**Solución:**
```bash
# 1. Verificar sintaxis
php -l Modules/User/Routes/web.php

# 2. Limpiar cache
php artisan route:clear
php artisan config:clear
php artisan optimize:clear

# 3. Re-generar autoload
composer dump-autoload

# 4. Verificar configuración
php artisan config:show make-module
```

---

#### **Error: `Class 'Modules\User\Http\Controllers\...' not found`**

**Causa:** Composer no encuentra las clases del módulo.

**Solución:** Añade el directorio `Modules/` al autoload de Composer.

En `composer.json`:
```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Modules\\": "Modules/"
        }
    }
}
```

Luego:
```bash
composer dump-autoload
```

---

### ✅ Flujo Completo de Verificación

```bash
# 1. Setup inicial (solo una vez)
php artisan innodite:module-setup

# 2. Generar módulo
php artisan innodite:make-module User --context=central

# 3. Verificar archivos
ls -la Modules/User/Routes/web.php

# 4. Actualizar autoload
composer dump-autoload

# 5. Limpiar cache
php artisan route:clear

# 6. Verificar rutas
php artisan route:list --path=central-users
```

---

### 📝 Notas Importantes

- **El ServiceProvider carga las rutas AUTOMÁTICAMENTE** — no necesitas `require` manual
- **Las rutas se cargan desde `Modules/{Module}/Routes/`** — NO desde `routes/` del proyecto
- **El paquete usa `Route::middleware('web')->group()` automáticamente** para `web.php`
- **Para `tenant.php`, el archivo maneja sus propios grupos** sin wrapper del paquete

---

### 🆘 ¿Sigues teniendo problemas?

Ejecuta el comando de verificación del paquete:

```bash
php artisan innodite:module-check User
```

Esto mostrará el estado completo del módulo y te indicará qué falta.
