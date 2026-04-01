# 🔄 Guía de Migración: v2.x → v3.0.0

## 📋 Cambios Críticos

### 1. **Carpeta de Rutas: `routes/` → `Routes/`**

**Antes (v2.x):**
```
Modules/
  User/
    routes/          ← lowercase
      web.php
      api.php
```

**Ahora (v3.0.0):**
```
Modules/
  User/
    Routes/          ← UPPERCASE
      web.php
      tenant.php
      api.php
```

---

## ✅ **SOLUCIÓN AUTOMÁTICA**

El paquete **v3.0.6** es **backward compatible** y carga automáticamente módulos de ambas versiones:

- ✅ Módulos nuevos con `Routes/` (v3.0.0+)
- ✅ Módulos antiguos con `routes/` (v2.x legacy)

**No necesitas hacer nada si tus módulos siguen funcionando.**

---

## 🔧 **Migración Opcional (Recomendada)**

Para aprovechar las nuevas funcionalidades de v3.0.0, migra tus módulos:

### **Opción A: Comando Automático**

```bash
# Vista previa de cambios
php artisan innodite:migrate-modules --dry-run

# Aplicar migración
php artisan innodite:migrate-modules
```

Esto renombrará automáticamente `routes/` → `Routes/` en todos tus módulos.

### **Opción B: Manual**

```bash
# Para cada módulo en Modules/
cd Modules/User
mv routes Routes  # Renombrar carpeta
```

---

## 🐛 **Problemas Comunes Después de Actualizar**

### **Error: "No se cargan las rutas"**

**Diagnóstico:**
```bash
# Verificar estructura de carpetas
ls -la Modules/User/

# Debe mostrar:
# Routes/  (uppercase) ← v3.0.0
# O routes/ (lowercase) ← v2.x legacy (también compatible)
```

**Solución:**
```bash
# Actualizar autoload
composer dump-autoload

# Limpiar caché
php artisan route:clear
php artisan config:clear
php artisan optimize:clear

# Verificar rutas
php artisan route:list
```

---

### **Error: "Class 'Modules\User\Providers\UserServiceProvider' not found"**

**Causa:** El ServiceProvider del módulo no se está cargando.

**Solución:**
```bash
# 1. Verificar que existe
ls -la Modules/User/Providers/UserServiceProvider.php

# 2. Verificar namespace
head -10 Modules/User/Providers/UserServiceProvider.php
# Debe decir: namespace Modules\User\Providers;

# 3. Actualizar autoload
composer dump-autoload
```

---

### **Error: "Route [central.users.index] not defined"**

**Causa:** Los archivos de rutas no se están cargando o la sintaxis tiene errores.

**Solución:**
```bash
# 1. Verificar sintaxis
php -l Modules/User/Routes/web.php

# 2. Verificar estructura (debe usar convención v3.0.0)
cat Modules/User/Routes/web.php

# 3. Si es un módulo v2.x, debe tener:
# Route::get('/users', [UserController::class, 'index'])->name('users.index');

# 4. Verificar que el archivo se carga
php artisan route:list --path=users
```

---

## 🆕 **Nuevas Funcionalidades en v3.0.0**

### **1. Sistema de Contextos (`contexts.json`)**

Define contextos arquitectónicos para tu proyecto:

```json
{
  "contexts": {
    "central": [...],
    "shared": [...],
    "tenant_shared": [...],
    "tenant": [...]
  }
}
```

**Configurar:**
```bash
php artisan innodite:module-setup
# Edita: module-maker-config/contexts.json
```

---

### **2. Generación Contextualizada**

Genera módulos con contexto explícito:

```bash
# Módulo central (admin)
php artisan innodite:make-module User --context=central

# Módulo tenant (multi-tenant)
php artisan innodite:make-module Client --context=tenant
```

---

### **3. Archivos de Ruta por Contexto**

- `Routes/web.php` → Central + Shared (middleware 'web')
- `Routes/tenant.php` → Tenants (sin wrapper, self-contained)
- `Routes/api.php` → API externa (middleware 'api')

---

### **4. Middleware de Bridge Frontend-Backend**

```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', [
        \Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge::class,
    ]);
})
```

---

### **5. Trait `RendersInertiaModule` (Controladores Inertia)**

```php
use Innodite\LaravelModuleMaker\Traits\RendersInertiaModule;

class CentralUserController extends Controller
{
    use RendersInertiaModule;
    
    public function index()
    {
        return $this->renderModule('User', 'CentralUserIndex');
    }
}
```

---

## 📚 **Recursos**

- [SKILL.md](SKILL.md) — Documentación arquitectónica completa
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) — Guía de diagnóstico
- [README.md](README.md) — Documentación de usuario

---

## 🆘 **Soporte**

Si tus módulos siguen sin cargarse después de seguir esta guía:

```bash
# Ejecutar diagnóstico completo
php artisan innodite:module-check

# Ver log de errores
tail -50 storage/logs/laravel.log
```

**Reportar issue:** https://github.com/Innodite/laravel-module-maker/issues
