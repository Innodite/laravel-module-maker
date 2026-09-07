# 🏗️ Innodite Laravel Module Maker

[![Tests](https://github.com/Innodite/laravel-module-maker/actions/workflows/tests.yml/badge.svg)](https://github.com/Innodite/laravel-module-maker/actions/workflows/tests.yml)
[![Coverage](https://github.com/Innodite/laravel-module-maker/actions/workflows/coverage.yml/badge.svg)](https://github.com/Innodite/laravel-module-maker/actions/workflows/coverage.yml)
[![Latest Version](https://img.shields.io/github/v/tag/Innodite/laravel-module-maker?label=version&color=indigo)](https://github.com/Innodite/laravel-module-maker/releases)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![License](https://img.shields.io/github/license/Innodite/laravel-module-maker?color=green)](LICENSE)

**v5.0** — Generador de módulos Laravel para proyectos de una aplicación o multiinquilino. Genera el
backend completo, sus rutas con el permiso de cada una y sus vistas Vue 3, con un solo comando. Un
módulo agrupa varias subfuncionalidades, y cada una es autocontenida:
`Modules/<Módulo>/<SubFuncionalidad>/<Capa>/<Contexto>/`.

## 👉 La versión que se instala es la **5.0.0**

```json
"innodite/laravel-module-maker": "^5.0"
```

**Y cambia la forma de todo lo que el paquete escribe.** Un módulo generado con una 4.x no coincide
con lo que genera esta —ni en carpetas ni en namespaces—, así que actualizar sin mover nada deja el
proyecto con dos formas dentro. Qué cambió exactamente, y en qué orden se mueve un módulo existente,
está en el [CHANGELOG](CHANGELOG.md).

El primer comando después de actualizar es el diagnóstico, que dice qué le falta al proyecto y con
qué línea se arregla cada cosa:

```bash
php artisan innodite:doctor
```

---

## ⚠️ Versiones Deprecadas

Se consideran **deprecados** los tags históricos con referencias heredadas a software/proyecto externo.

Tags deprecados:
- `v2.5.0`
- `v3.2.7` a `v3.4.0`

Versión mínima recomendada para uso nuevo:
- `v3.4.1+`

> Nota: la deprecación es de soporte/uso recomendado. No se reescribe el historial Git publicado.

---

## 📋 Tabla de Contenidos

- [La versión que se instala](#-la-versión-que-se-instala-es-la-500)
- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Tabla comparativa de contextos](#-tabla-comparativa-de-contextos)
- [Versiones Deprecadas](#-versiones-deprecadas)
- [Arquitectura Frontend](#-arquitectura-frontend)
- [Guía de comandos](#-guía-de-comandos)
- [Archivos generados por contexto](#-archivos-generados-por-contexto)
- [Flujo completo por contexto](#-flujo-completo-por-contexto)
- [Composables Vue 3](#-composables-vue-3)
- [Stubs contextuales](#-stubs-contextuales)
- [Bridge Frontend-Backend](#-bridge-frontend-backend)
- [Estructura de contextos](#-estructura-de-contextos-contextsjson)
- [Estructura de árbol de un módulo generado](#-estructura-de-árbol-de-un-módulo-generado)
- [Convenciones de nomenclatura](#-convenciones-de-nomenclatura)
- [Flujo de inyección de rutas](#-flujo-de-inyección-de-rutas)
- [Auditoría](#-auditoría)
- [Pruebas](#-pruebas)
- [Estándares de código](#-estándares-de-código)
- [Publicar en Packagist](#-publicar-en-packagist--repositorio-privado)
- [Documentación](#-documentación)
- [Changelog](#-changelog)
- [Licencia](#-licencia)

**Nuevos en la v4:**
- [Cómo se ejecutan las migraciones](docs/como-se-ejecutan-las-migraciones.md) — quién decide el orden, y por qué son dos niveles
- [Changelog](CHANGELOG.md) — qué cambió, y qué hay que tocar al actualizar
- [`innodite:doctor`](#-innoditedoctor--diagnóstico-en-cascada) — el diagnóstico en cascada, que sustituye a `module-check` y `check-env`
- [`innodite:deploy`](#-innoditedeploy--desplegar-el-proyecto) — levanta el proyecto entero en el orden declarado
- [`innodite:crear-bd-test`](#-pruebas) — clona el esquema real en la base `_test`, sin una sola fila

---

## ✅ Requisitos

| Dependencia | Versión mínima |
|---|---|
| PHP | 8.2+ |
| Laravel | 11.0+ |
| illuminate/support | ^11.0\|^12.0 |
| illuminate/console | ^11.0\|^12.0 |
| illuminate/filesystem | ^11.0\|^12.0 |
| illuminate/routing | ^11.0\|^12.0 |
| @inertiajs/vue3 | ^1.0 (frontend) |
| Vue | ^3.0 (frontend) |

> Compatible opcionalmente con `stancl/tenancy` y `spatie/laravel-permission`.

---

## 🚀 Instalación

```bash
composer require innodite/laravel-module-maker
```

Al instalar por primera vez, el paquete detecta la ausencia de configuración y sugiere el setup en consola.

### Inicializar el proyecto (requerido)

```bash
php artisan innodite:module-setup
```

Crea `module-maker-config/` en la raíz del proyecto con:
- `contexts.json` — Definición de contextos y tenants
- `stubs/contextual/` — Plantillas PHP y Vue personalizables

### Publicar assets manualmente

```bash
# Configuración make-module.php
php artisan vendor:publish --tag=module-maker-config

# Todas las plantillas de golpe — solo si de verdad vas a personalizarlas todas
php artisan vendor:publish --tag=module-maker-stubs

# contexts.json de ejemplo
php artisan vendor:publish --tag=module-maker-contexts
```

> ⚠️ **Publicar las plantillas tiene un coste que conviene saber antes:** una copia publicada se
> queda congelada en tu proyecto y deja de actualizarse con el paquete, así que sigue generando la
> forma antigua sin decir nada. Por eso el instalador ya no las publica y existe
> `innodite:publish-stubs`, que te deja pedir **solo las que vas a tocar** y sin argumentos se limita
> a listarlas.

---

## 🗺️ Los dos contextos

En multiinquilino hay **dos contextos y solo dos**. Un proyecto que necesite otro lo declara en su
`module-maker-config/contexts.json`; el paquete no trae ninguno más de fábrica.

| Contexto | Prefijo de clase | Carpeta | Archivo de rutas | Prefijo de URL | Nombre de ruta | Conexión del modelo |
|---|---|---|---|---|---|---|
| `central` | `Central` | `…/Central/` | `Routes/web.php` | `central-users` | `central.users.index` | `$connection = 'central'` |
| `tenant` | `Tenant` | `…/Tenant/` | `Routes/tenant.php` | `tenant-users` | `tenant.users.index` | **ninguna** — la conmuta la tenencia |

> ⛔ **Ningún archivo lleva el nombre de un cliente.** Una carpeta y una conexión por cliente
> producen una copia por cliente de lógica idéntica, permisos que hay que sembrar y renombrar uno a
> uno, y rutas servidas bajo el prefijo de un cliente concreto.
>
> ⛔ **Y el modelo del inquilino no declara conexión, nunca.** La conmuta el paquete de tenencia al
> identificar la ruta; nombrarla ata el modelo a un cliente. Si el proyecto declara
> `tenancy.package = none`, el modelo sale con una **nota** que dice quién tiene que conmutarla.

---

## 🖥️ Arquitectura Frontend

> **Regla fundamental — No negociable en este paquete.**

| Responsabilidad | Tecnología |
|---|---|
| Navegación entre páginas | Inertia.js (`router.visit()`, `router.get()`) |
| Carga y mutación de datos | axios (`GET`, `POST`, `PUT`, `DELETE`) |
| Contexto activo y permisos | Props de Inertia — compartidos por `InnoditeContextBridge` |

Los controladores utilizan el trait `RendersInertiaModule` y el método `renderModule()` para devolver la vista Inertia correcta según el contexto. **Nunca** pasan datos de negocio por props de Inertia.

Las vistas Vue son *shells* que se autocargan al montarse vía axios. Inertia nunca transporta datos de negocio; solo gestiona la navegación SPA.

```php
// Controlador — uso de renderModule()
class CentralUserController extends Controller
{
    use RendersInertiaModule;

    public function index(): JsonResponse
    {
        $users = $this->service->paginate();
        return response()->json($users);
    }

    public function create(): \Inertia\Response
    {
        return $this->renderModule('CentralUserCreate');
        // Retorna la vista Inertia — sin datos de negocio
    }
}
```

```js
// Vista Vue — carga sus propios datos al montarse
onMounted(async () => {
    const { data } = await window.axios.get(window.route('central.users.list'))
    items.value = data.data
})
```

---

## 🛠️ Guía de comandos

### `innodite:make-module` — Generador principal

Genera backend completo + vistas Vue en un solo comando.

```bash
# Módulo completo (backend + vistas + rutas inyectadas)
php artisan innodite:make-module User --context=central

# El contexto del inquilino: rutas en tenant.php, permiso tenant-permission
php artisan innodite:make-module Invoice --context=tenant

# Selección interactiva de contexto
php artisan innodite:make-module User

# En aplicación única no se pasa contexto: no hay eje que elegir
php artisan innodite:make-module User

# Componentes individuales en módulo existente
php artisan innodite:make-module User --context=central -S -R   # Service + Repository
php artisan innodite:make-module User --context=central -C      # Controller + rutas
php artisan innodite:make-module User --context=central -G      # Migration
php artisan innodite:make-module User --context=central -M -Q   # Model + Request

# Desde JSON de configuración dinámica
php artisan innodite:make-module User --json
```

**Flags de componentes:**

| Flag | Componente generado |
|---|---|
| `-M` / `--model` | Modelo Eloquent con `$table` definida |
| `-C` / `--controller` | Controlador con `RendersInertiaModule` + inyección de rutas CRUD |
| `-S` / `--service` | Servicio + Interface en `Services/Contracts/` |
| `-R` / `--repository` | Repositorio + Interface en `Repositories/Contracts/` |
| `-G` / `--migration` | Migración anónima contextualizada |
| `-Q` / `--request` | Form Request validado — el de alta y el de edición |

**Validaciones de seguridad:**
- Nombres no PascalCase son rechazados
- Palabras reservadas de PHP y Laravel bloqueadas: `class`, `model`, `auth`, `route`, etc.
- Módulos duplicados bloqueados con opción de añadir componentes
- En caso de error, se ofrece **rollback** para eliminar archivos generados

---

### `innodite:add-entity` — Agregar entidad a módulo existente

Agrega una nueva entidad a un módulo **ya existente**, generando sus componentes dentro de la subcarpeta de entidad correspondiente. Diseñado para módulos multi-entidad como `UserManagement` (con `User`, `Role`, `Permission`, `Module`).

```bash
# Agregar entidad Role al módulo UserManagement en contexto central
php artisan innodite:add-entity UserManagement Role --context=central

# Solo componentes específicos
php artisan innodite:add-entity UserManagement Permission --context=central -M -C -S -R -G -Q

# La misma entidad, en el contexto del inquilino
php artisan innodite:add-entity UserManagement Role --context=tenant
```

**Firma:**

```
innodite:add-entity {module} {entity} {--context=} [-M] [-C] [-S] [-R] [-G] [-Q]
```

| Argumento | Descripción |
|---|---|
| `module` | Nombre del módulo existente (ej: `UserManagement`) |
| `entity` | Nombre de la entidad nueva (ej: `Role`, `Permission`) |
| `--context=` | Contexto destino: `central` o `tenant`. En aplicación única no se pasa |
| `-M` a `-Q` | Mismos flags que `make-module` (sin flags = genera todos los componentes) |

**Ejemplo de archivos generados** — `add-entity UserManagement Role --context=central`:

```
Modules/UserManagement/
└── Role/                                  ← la subfuncionalidad manda
    ├── Models/Central/CentralRole.php
    ├── Http/Controllers/Central/CentralRoleController.php
    ├── Http/Requests/Central/CentralRoleStoreRequest.php
    ├── Http/Requests/Central/CentralRoleUpdateRequest.php
    ├── Services/Central/CentralRoleService.php
    ├── Services/Contracts/Central/CentralRoleServiceInterface.php
    ├── Repositories/Central/CentralRoleRepository.php
    ├── Repositories/Contracts/Central/CentralRoleRepositoryInterface.php
    └── Database/Migrations/Central/..._create_roles_table.php
```

**La capa va dentro de la subfuncionalidad, y el contexto es la hoja.** En aplicación única el
último tramo no existe (`Role/Services/RoleService.php`) y el resto es idéntico: los dos modos
comparten estructura en vez de parecerse.

**Diferencia con `make-module`:**

| | `make-module` | `add-entity` |
|---|---|---|
| Crea módulo nuevo | ✅ | ❌ |
| Agrega a módulo existente | ❌ | ✅ |
| Valida que el módulo exista primero | — | ✅ |
| Sin flags = genera todos los componentes | ✅ | ✅ |
| Naming convention intacta | ✅ | ✅ |

---

### `innodite:module-setup` — Configuración inicial

```bash
php artisan innodite:module-setup
```

Crea la estructura de configuración del paquete en la raíz del proyecto. Debe ejecutarse una sola vez al inicializar un nuevo proyecto que use este paquete.

---

### `innodite:doctor` — Diagnóstico, en cascada

```bash
php artisan innodite:doctor
```

Recorre tres etapas, y **cada fallo trae en su propia línea qué hacer**.

**Etapa 1 · El entorno del generador**

1. El modo del proyecto — sin él, los comandos no generan
2. `contexts.json` — validez, estructura y el catálogo que exige ese modo
3. Permisos de escritura en `Modules/` y `storage/logs/`
4. Colisiones de nombres entre módulos, y comandos del proyecto que tapen a los del paquete
5. `config/make-module.php` publicada — el orden de despliegue vive ahí
6. Stubs publicados — detecta los que siguen en el formato de la v3
7. Últimas entradas del log de eventos

**Etapa 2 · El contrato del proyecto anfitrión**

Verifica el bridge Inertia y, si algo falta, imprime el **bloque de código exacto** a copiar:

1. Modelo User — `HasRoles` (Spatie) o `InnoditeUserPermissions`
2. `HandleInertiaRequests` — `auth.permissions` compartido
3. `InnoditeContextBridge` — registrado en el grupo web

**Etapa 3 · Lo que dice el criterio**

Consulta al proveedor de reglas configurado y lista sus hallazgos.

> **Viene de `innodite:module-check` y de `innodite:check-env`, que ya no existen.** Eran dos
> comandos útiles cuyos nombres no decían lo que hacían, y que había que acordarse de lanzar por
> separado: uno miraba el entorno y el otro el contrato, cuando lo que se quiere saber es una sola
> cosa —si este proyecto puede generar y si lo generado va a encontrar su sitio—.

---

### `innodite:publish-frontend` — ⛔ RETIRADO

El paquete **no configura el frontend de tu proyecto**: no publica composables ni componentes, y
no toca `app.js` ni el middleware de Inertia. Solo **genera** las vistas de cada subfuncionalidad,
y lo hace sin depender de ninguna biblioteca — el `can()` va escrito en la propia pantalla.

Contra qué se generan lo decide `--frontend=default|innodite` en el instalador.

---
### `innodite:migrate-plan` — ⛔ RETIRADO

Aplicaba las migraciones del proyecto recorriendo el árbol. **Usa `innodite:deploy`**, que aplica el
esquema, los datos y los permisos juntos y en el orden que declaras tú:

```bash
php artisan innodite:deploy stage --context=central
php artisan innodite:deploy stage --context=central --dry-run   # ver el plan sin tocar la base
```

El comando sigue registrado para decir esto mismo si lo ejecutas, y devuelve error
—no éxito—, de modo que un script que lo tuviera escrito no lo dé por hecho.

**Por qué se retiró.** Ordenaba las subfuncionalidades por el **nombre de sus carpetas**, ignorando
el orden que el proyecto declara en `deploy`. Con `Cart`, `Customer` y `Order` en un módulo de
ventas, `carts` se migraba antes que las dos tablas a las que apunta. Y era el único sitio del
paquete que ejecutaba migraciones **fuera del seeder**, contra la regla que el propio paquete
escribe en cada trait que genera: *el vehículo del despliegue es el seeder*.

**Dónde vive ahora cada mitad del orden**, que es lo único que hay que recordar:

| Quién | Qué decide |
|---|---|
| `deploy`, en `config/make-module.php` | El orden **entre** subfuncionalidades — lo declaras tú |
| El trait `MigrationsList` de cada una | El orden **dentro** de una subfuncionalidad — lo genera el paquete |

Para una migración suelta sigue estando `innodite:migrate-one`.

---

### `innodite:migrate-one` — Ejecutar una migración específica

Ejecuta una sola migración, nombrada por su coordenada `Modulo:Contexto/archivo.php`. La base de
datos sale de la propia coordenada, que ya lleva encima su carpeta de contexto.

```bash
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php"

# Sin confirmación interactiva
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php" --yes

# Ver qué haría
php artisan innodite:migrate-one "Invoice:Central/2026_08_01_120000_crea_facturas.php" --dry-run
```

| Opción | Descripción |
|---|---|
| `--context=` | Fuerza el contexto de ejecución en vez de derivarlo de la coordenada |
| `--yes` | Omite la confirmación |
| `--dry-run` | Muestra lo que haría sin ejecutar |

---

### `innodite:deploy` — Levanta el proyecto entero

Esquema, datos y permisos, en el orden declarado en `deploy` (`config/make-module.php`), más el
webmaster al cerrar. Es el comando que sustituye a `innodite:seed-one` y a `innodite:migration-sync`.

```bash
# Aplicación única
php artisan innodite:deploy production

# Multitenant: dos despliegues, contra dos bases de datos
php artisan innodite:deploy production --context=central
php artisan innodite:deploy stage --context=tenant
```

| Argumento / opción | Descripción |
|---|---|
| `entorno` | **Obligatorio.** `stage` o `production`. Nada por defecto: `stage` puede reconstruir desde cero |
| `--context=` | Obligatorio en multitenant: `central` o `tenant` |
| `--force` | No pedir confirmación aunque `SEEDER_DESTRUCTIVE` esté activo |

El seeder que invoca lo escribe el instalador en `database/seeders/`, y es donde se lee y se amplía
la secuencia de pasos del despliegue.

> **Retirados en la v4:** `innodite:seed-one` y `innodite:migration-sync`. Los dos existían para
> mantener los manifiestos `*.order.json`, que ya no lee nadie: el orden de las migraciones lo
> declaran los traits `MigrationsList` y el de los seeders el array `deploy`. Si tu proyecto todavía
> tiene esa carpeta, los comandos de migración te lo dirán al ejecutarse; puedes borrarla.

---


### `innodite:test` — Ejecutar el contrato de una subfuncionalidad

```bash
# El contrato completo de una subfuncionalidad
php artisan innodite:test Invoice Payment

# En multitenant, diciendo en qué contexto vive
php artisan innodite:test Invoice Payment --context=central

# Acotando dentro de una pieza
php artisan innodite:test Invoice Payment --filter=test_no_se_ve_sin_permiso

# Sin corte temprano, para ver el grupo entero de una pasada
php artisan innodite:test Invoice Payment --continuar
```

**Se ejecuta por subfuncionalidad, no por módulo**, porque el contrato es de ella: son sus nueve
temas, su manifiesto y sus seis piezas. Un módulo con cuatro subfuncionalidades no tiene un
contrato, tiene cuatro — y ejecutarlos juntos mezcla el resultado de cosas que se despliegan y
fallan por separado.

**En cascada, y con corte temprano:**

```
Scaffold  →  Schema     →  Permissions  →  Deployment  →  Http
tema 0       temas 1-2     temas 3-5       tema 8         tema 7

tema 6 (la vista) lo ejecuta Vitest, aparte
```

El orden es de dependencia: cada pieza da por supuesto lo que comprobó la anterior. Si el andamiaje
no está, el esquema falla por lo mismo; si el esquema no está, los permisos fallan por lo mismo; y el
comportamiento por HTTP falla de treinta formas distintas por la misma causa única.

Treinta fallos rojos de un solo problema no informan treinta veces mejor: informan **peor**, porque
hay que leerlos todos para descubrir que eran el mismo. Por eso al primer fallo se para, y dice qué
falló, qué cubría y **qué queda sin ejecutar**.

**Antes de lanzar nada comprueba que el grupo esté entero.** Faltar el manifiesto no es «una prueba
menos»: sin él ninguna de las otras puede derivar rutas ni permisos, y lo que saldría serían fallos
que describen el síntoma y esconden la causa.

**El tema 6 se nombra siempre**, también cuando todo pasa. Un contrato «en verde» que se saltó un
tema sin decirlo es la peor clase de silencio.

#### Qué tiene que traer tu proyecto para que el grupo corra

El grupo generado se escribe **contra las tablas y contra el router**, no contra las clases de un
paquete concreto — así sobrevive a que cambies de versión, o de paquete de permisos. A cambio da por
supuestas cinco cosas, y las cinco las pone el proyecto, no el módulo:

| Lo que hace falta | Por qué |
|---|---|
| `Tests\TestCase` | La base del grupo la extiende. Es la de Laravel de toda la vida |
| `Modules\` en el autoload PSR-4 de Composer | Sin él las piezas no se cargan |
| `users` y las tablas de permisos | `permissions` (con `description` y `module_id`), `modules`, `roles`, `model_has_permissions` y `role_has_permissions`. El seeder generado llena `description` y `module_id` **si existen** |
| El alias del middleware de permiso, registrado | El que use tu proyecto: `permission`, `central-permission`, `tenant-permission`… El grupo lo lee de la propia ruta, así que reconoce cualquiera que termine en `permission` |
| `APP_KEY` | El grupo `web` cifra la sesión: sin clave, la petición revienta **antes** de llegar al middleware de permiso, y el fallo no se parece en nada a su causa |

Y una consecuencia del diseño de las migraciones: el trait `MigrationsList` declara sus rutas
**relativas a la raíz del proyecto**, porque así las recibe `migrate --path`. El módulo tiene que
vivir bajo esa raíz para que el seeder las encuentre.

**Tres pruebas nacen saltadas, y es a propósito.** Las de validación —entrada inválida, edición
inválida, y la de crear y volver a leer— se saltan mientras el FormRequest generado no declare
reglas: sin `rules()` no hay nada que pueda fallar la validación, y una prueba que pasa porque no
comprueba nada es peor que una que dice que no corrió. **Escribe tus reglas y corren solas.**

> **Viene de `innodite:test-module`, que ya no existe.** El comando anterior trabajaba por módulo y
> contexto leyendo `Modules/{Modulo}/Tests/test-config.json`. Ese archivo era el último manifiesto
> JSON del paquete y se retiró junto con `innodite:test-sync`, que lo generaba: el contexto lo dice
> ahora el comando (`--context`) y la forma del módulo la dice el modo. Un proyecto que aún tenga
> esos archivos recibe un **aviso** al ejecutar `innodite:test` — no un error: el proyecto funciona
> sin ellos, y puedes borrarlos cuando hayas comprobado que no te falta nada de ellos.
---

## 📁 La forma de lo generado

```
Modules/<Módulo>/<SubFuncionalidad>/<Capa>/<Contexto>/<Archivo>
```

**La subfuncionalidad manda, la capa va dentro de ella y el contexto es la hoja.** En aplicación
única ese último tramo no existe y el resto es idéntico: los dos modos comparten estructura en vez
de parecerse.

```
Modules/User/
├── Docs/  architecture.md · history.md · schema.md          ← del MÓDULO
├── Routes/web.php · tenant.php                              ← del MÓDULO, uno por contexto
├── Providers/Central/CentralUserServiceProvider.php         ← del MÓDULO, uno por contexto
│             Tenant/TenantUserServiceProvider.php
├── Database/Seeders/Application/                            ← del MÓDULO: los 3 maestros
│       ├── Central/CentralUserApplication{Stage,Production,Permissions}Seeder.php
│       └── Tenant/TenantUserApplication…
│
├── User/                                                    ← SUBFUNCIONALIDAD, autocontenida
│   ├── Models/Central/CentralUser.php  ·  Tenant/TenantUser.php
│   ├── Http/Controllers/{Ctx}/{Ctx}UserController.php
│   │     Requests/{Ctx}/{Ctx}User{Store,Update}Request.php
│   ├── Services/{Ctx}/…  +  Services/Contracts/{Ctx}/…
│   ├── Repositories/{Ctx}/…  +  Repositories/Contracts/{Ctx}/…
│   ├── Database/Factories/{Ctx}/ · Migrations/{Ctx}/ · Seeders/{Ctx}/   (las 6 piezas)
│   ├── Tests/Feature/{Ctx}/                                 (las 6 piezas + TestCase)
│   ├── resources/js/Pages/{Ctx}/ · __tests__/{Ctx}/
│   └── Jobs/{Ctx}/ · Notifications/{Ctx}/ · Console/Commands/{Ctx}/ · Exceptions/{Ctx}/
│                                                            ← vacías, con .gitkeep
└── Role/                                                    ← la misma forma
```

**Qué vive dónde:** si la pieza recorre **todas** las subfuncionalidades —`Docs/`, `Routes/`,
`Providers/` y los tres maestros—, vive al nivel del módulo. Todo lo demás baja a la suya.

**Las cuentas, medidas generando de verdad:**

| | |
|---|---|
| Módulo `User` con 2 subfuncionalidades × 2 contextos | **129 archivos + 16 carpetas** |
| El mismo en aplicación única | **66 archivos + 8 carpetas** |
| Una subfuncionalidad en un solo contexto | 29 archivos |

---

## 🔄 El ciclo, de principio a fin

```bash
# 1 · Configurar el proyecto — se elige el modo UNA vez
php artisan innodite:module-setup --mode=multitenant --tenancy=stancl --frontend=default

# 2 · El primer módulo, en el contexto que sea
php artisan innodite:make-module User --context=central

# 3 · Las demás subfuncionalidades, y el otro contexto
php artisan innodite:add-entity User Role --context=central
php artisan innodite:add-entity User User --context=tenant

# 4 · Levantar
php artisan innodite:deploy stage --context=central
php artisan innodite:deploy stage --context=tenant --all

# 5 · El contrato de pruebas
php artisan innodite:test User Role --context=central
```

⛔ **`add-entity` deja exactamente lo mismo que `make-module`**: las cuatro capas, la migración, las
seis piezas de seeder, la factory, las pantallas, **las rutas**, el provider de su contexto y el
grupo de pruebas. Las dos puertas por las que aparece una subfuncionalidad emiten lo mismo.

---

## 🧩 Composables Vue 3

⛔ **El paquete ya no publica composables.** Se retiraron en la 4.x junto con
`innodite:publish-frontend`: montar el andamiaje del frontend es del proyecto, o de la biblioteca de
interfaz que use. Lo que sigue describe **el contrato que la vista generada espera** de tu proyecto,
por si prefieres implementarlo con composables propios; la pantalla que genera el paquete no importa
ninguno — lleva su `can()` de dos líneas escrito dentro.

### El contexto activo, y cómo lo lee la pantalla

Lee `auth.context.route_prefix` desde las props de Inertia compartidas por `InnoditeContextBridge` y antepone automáticamente el prefijo correcto a cualquier clave de ruta.

```js
// La pantalla generada lee lo que el puente compartió:
const { route_prefix, permission_prefix } = usePage().props.auth.context

// central → 'central'   ·   inquilino → 'tenant'   ·   aplicación única → null
```

⛔ **El paquete no publica ningún composable para esto.** Lo hizo hasta la 4.x, con
`useModuleContext`; se retiró junto con el resto del andamiaje de frontend, porque montar el
frontend es del proyecto o de la biblioteca que lo instale, no del generador.

⭐ **Y el nombre de la ruta ya no hay que resolverlo en cada petición:** el generador lo escribe en
la pantalla con el prefijo de su contexto ya puesto, porque lo que se sabe al generar no se calcula
mil veces después. El contexto compartido sigue sirviendo para lo que sí depende de la sesión.

---

### `usePermissions` — Verificación de permisos del usuario

Lee `auth.permissions` desde las props de Inertia y permite verificar permisos de forma declarativa en las plantillas Vue.

```js
import { usePermissions } from '@/Composables/usePermissions'

const { can, canAny, canAll } = usePermissions()

can('users.create')                          // true/false
canAny(['users.edit', 'users.create'])       // true si tiene al menos uno
canAll(['users.view', 'users.edit'])         // true si tiene todos
```

**Estrategia dual:** verifica `{prefix}.{perm}` y `{perm}` plano simultáneamente. El mismo componente funciona en cualquier contexto sin cambios.

```vue
<template>
  <!-- Botón visible solo si el usuario tiene permiso -->
  <button v-if="can('users.create')" @click="goToCreate()">
    Nuevo usuario
  </button>

  <!-- Acciones de fila protegidas por permisos -->
  <button v-if="can('users.edit')" @click="edit(item.id)">Editar</button>
  <button v-if="can('users.delete')" @click="destroy(item.id)">Eliminar</button>
</template>
```

---

### Flujo de datos en las vistas Vue generadas

```
Montaje  → axios.get(route('central.users.list'))       ← carga datos
Guardar  → axios.post/patch(route('central.users.…'))   ← muta datos
Renderiza→ Inertia, una sola vez, para la pantalla      ← y nada más
Permisos → can('central.users.edit')                    ← oculta/muestra la UI
```

### Ejemplo — `CentralUserIndex.vue`

```vue
<script setup>
import { computed, ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import CentralUserCreate from './CentralUserCreate.vue'
import CentralUserEdit from './CentralUserEdit.vue'
import CentralUserShow from './CentralUserShow.vue'

// Los permisos van escritos aquí, no importados: la vista no depende de
// ningún archivo que el paquete deje en tu proyecto.
const permisos = computed(() => usePage().props?.auth?.permissions ?? [])
const can = (permiso) => permisos.value.includes(permiso)

const items = ref([])
const meta  = ref({ current_page: 1, last_page: 1, total: 0 })

async function fetchItems(page = 1) {
    // El nombre de la ruta lo escribe el generador con el prefijo de su contexto
    // ya puesto: lo que se sabe al generar no se resuelve en cada petición.
    const { data } = await window.axios.get(window.route('central.users.list'), { params: { page } })
    items.value = data.data
    meta.value  = { current_page: data.current_page, last_page: data.last_page, total: data.total }
}

async function destroy(id) {
    await window.axios.delete(window.route('central.users.destroy', { id }))
    fetchItems(meta.value.current_page)
}

onMounted(() => fetchItems())
</script>
```

### Ejemplo — `CentralUserCreate.vue`

```vue
async function submit() {
    await window.axios.post(window.route('central.users.store'), form.value)
    emit('guardado')   // el índice recarga su tabla; no hay navegación de por medio
}
```

- Errores de validación Laravel 422 mostrados campo a campo
- Botón deshabilitado durante el envío (previene doble submit)

### Ejemplo — `CentralUserEdit.vue`

```vue
onMounted(async () => {
    const { data } = await window.axios.get(window.route('central.users.show', { id: props.id }))
    form.value = { ...data }  // rellena el formulario con datos existentes
})

async function submit() {
    await window.axios.patch(window.route('central.users.update', { id: props.id }), form.value)
    emit('guardado')   // el índice recarga su tabla
}
```

- Recibe solo `id` como prop de Inertia (nunca el objeto completo)
- Carga el registro vía axios al montarse

---

## 🔧 Stubs contextuales

El paquete trae **un solo juego** de plantillas, en una carpeta plana. Tú decides cuáles
personalizar, y puedes hacerlo para todo el proyecto o solo para un contexto.

### Estructura de stubs

```
module-maker-config/
└── stubs/
    └── contextual/
        ├── controller.stub          ← lo que publicas: una carpeta plana
        ├── service.stub
        ├── repository.stub
        ├── model.stub
        ├── request.stub
        ├── vue-index.stub
        ├── vue-create.stub
        ├── vue-edit.stub
        ├── vue-show.stub
        ├── …                        ← 34 en total
        │
        └── Central/                 ← OPCIONAL: solo si un contexto necesita algo distinto
            └── controller.stub
```

⚠️ **El paquete ya NO envía copias por contexto.** Llegó a llevar cuatro —`Central`, `Shared`,
`TenantShared`, `TenantName`—, idénticas byte a byte a la plantilla base y con prioridad sobre ella:
corregir la base sin tocar sus cuatro copias no cambiaba nada, porque ganaba la copia vieja. La
carpeta por contexto **sigue existiendo en tu proyecto**, que es donde puede diferir de verdad.

### Publicar stubs para personalización

```bash
php artisan vendor:publish --tag=module-maker-stubs
```

Copia las plantillas a `module-maker-config/stubs/contextual/`. A partir de ahí ganan a las del
paquete, y puedes borrar las que no vayas a tocar: lo que no esté ahí se sigue leyendo del paquete.

⚠️ **Publicarlas tiene un coste**: una plantilla copiada **no se actualiza** con el paquete. Si
publicaste las 38 «por si acaso», borra las que no personalizaste — si no, seguirás generando con
las de la versión en que las copiaste.

### Stubs aportados por otro paquete instalado

Un paquete instalado puede aportar sus propios stubs, y el generador los usará sin que haya que
configurar nada. El contrato es una ruta: cualquier paquete que traiga

```
vendor/<quien-sea>/<paquete>/stubs/module-maker/contextual/<nombre>.stub
```

participa en la resolución. Sirve para generar contra una biblioteca de interfaz concreta —su tabla,
sus formularios— en vez de contra las plantillas genéricas del paquete.

**Orden de resolución**, de lo más específico a lo más genérico:

| # | Dónde | Quién manda |
|---|---|---|
| 1 | `module-maker-config/stubs/contextual/{Contexto}/` | El proyecto, para un contexto |
| 2 | `module-maker-config/stubs/contextual/` | El proyecto |
| 3 | `vendor/*/*/stubs/module-maker/contextual/` | Un paquete instalado |
| 4 | Los del propio `laravel-module-maker` | El paquete |

El proyecto siempre gana: es el único que puede tener la última palabra sobre su propio código. Si
dos paquetes aportan el mismo stub, gana el primero por orden alfabético y `make-module` lo dice al
terminar, junto con el nombre de quién aportó qué.

### `provider-boot.stub` — el punto de enganche del módulo

Es un stub **opcional**: no existe en el paquete y solo se usa si el proyecto o un paquete instalado
lo aportan. Su contenido se escribe dentro del `boot()` del ServiceProvider del módulo generado, y
sin él ese `boot()` sale vacío.

Está pensado para enganchar el módulo en el menú de la aplicación, que es lo que el generador no
puede escribir por su cuenta: la línea que lo hace depende de cómo se declare un menú en cada
proyecto. Recibe dos variables:

| Variable | Qué trae | Ejemplo |
|---|---|---|
| `{{{ moduleName }}}` | El nombre del módulo | `Invoice` |
| `{{{ functionality }}}` | La funcionalidad, como la nombran sus rutas | `invoices` |

### Variables disponibles en los stubs

⛔ **Se escriben con TRIPLE llave**: `{{{ nombre }}}`, no `{{ nombre }}`. No es capricho: los stubs
generan código que a su vez lleva llaves —Blade, Vue, arrays de PHP—, y con doble llave el
sustituidor se comía trozos que no eran suyos. Un stub personalizado con doble llave **no se
sustituye y sale literal en el archivo generado**; el `doctor` lo detecta y lo dice.

Las que verás en casi todos:

| Variable | Descripción | Ejemplo |
|---|---|---|
| `{{{ moduleName }}}` | Nombre del módulo | `Invoice` |
| `{{{ modelName }}}` | Nombre del modelo | `Invoice` |
| `{{{ namespace }}}` | Namespace de la clase que se genera | `Modules\Invoice\Http\Controllers` |
| `{{{ className }}}` | Nombre de la clase generada | `InvoiceController` |
| `{{{ tableName }}}` | Nombre de la tabla | `invoices` |
| `{{{ subFeature }}}` | Subfuncionalidad | `Payment` |
| `{{{ subFeatureLabel }}}` | Etiqueta legible de la subfuncionalidad | `Payment` |
| `{{{ routeBase }}}` | La funcionalidad, como la nombran las rutas | `invoices` |
| `{{{ routePrefix }}}` | Prefijo de ruta del contexto | `central` |
| `{{{ connection }}}` | Conexión declarada, cuando el modo la lleva | `tenant_one` |

Y en las cuatro vistas Vue, además:

| Variable | Descripción | Ejemplo |
|---|---|---|
| `{{{ vueComponentName }}}` | Componente de **esta** vista | `InvoiceIndex` |
| `{{{ vueComponentBase }}}` | Nombre sin el sufijo de vista | `Invoice` |
| `{{{ permViewStore }}}` · `{{{ permViewShow }}}` · `{{{ permViewUpdate }}}` · `{{{ permViewDestroy }}}` · `{{{ permViewRestore }}}` | El permiso que decide si se muestra cada elemento | |

⭐ **La lista completa la tiene cada stub**: abre el que vayas a personalizar y mira qué variables
usa. Son las que su generador le entrega, y no todas están en todos.

---

## 🌉 Bridge Frontend-Backend

### Middleware `InnoditeContextBridge`

Intercepta cada request e inyecta vía `Inertia::share()`:

| Prop | Valor ejemplo |
|---|---|
| `auth.context.route_prefix` | `central`, `tenant` — `null` en aplicación única |
| `auth.context.permission_prefix` | `central`, `tenant` — `null` en aplicación única |
| `auth.permissions` | `['central.users.edit', 'users.view', ...]` |

**Cadena de resolución de permisos:**
1. Spatie Permission → `$user->getAllPermissions()->pluck('name')`
2. `InnoditeUserPermissions` → `$user->getInnoditePermissions()`
3. Fail-safe → `[]` + Warning en log

**Registrar en `bootstrap/app.php` (Laravel 11+):**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', [
        \Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge::class,
    ]);
})
```

**Alias para rutas específicas:**

```php
Route::middleware('innodite.bridge')->group(fn() => ...);
```

---

### Interfaz `InnoditeUserPermissions`

```php
use Innodite\LaravelModuleMaker\Contracts\InnoditeUserPermissions;

class User extends Authenticatable implements InnoditeUserPermissions
{
    public function getInnoditePermissions(): array
    {
        return $this->permissions->pluck('name')->toArray();
    }
}
```

---

## ⚙️ El catálogo de contextos (`contexts.json`)

Lo publica el instalador en `module-maker-config/contexts.json`, y en multiinquilino trae **dos**:

```json
{
    "contexts": {
        "central": {
            "id": "central",
            "is_tenant": false,
            "tenancy_strategy": "manual",
            "connection_key": "central",
            "class_prefix": "Central",
            "folder": "Central",
            "namespace_path": "Central",
            "route_file": "web.php",
            "route_prefix": "central",
            "route_name": "central."
        },

        "tenant": {
            "id": "tenant",
            "is_tenant": true,
            "class_prefix": "Tenant",
            "folder": "Tenant",
            "namespace_path": "Tenant",
            "route_file": "tenant.php",
            "route_prefix": "tenant",
            "route_name": "tenant."
        }
    }
}
```

⛔ **El inquilino no declara `connection_key`, y no es un olvido**: la conmuta el paquete de tenencia
al identificar la ruta. Nombrarla ataría cada modelo a un cliente.

**Un contexto propio** —una segunda central, un almacén aparte— se declara aquí con su carpeta, su
prefijo y su archivo de rutas, y el generador lo acepta. Ese sí tiene que declarar su conexión: no
la conmuta nadie.

---

## 📐 Convenciones de nomenclatura

| Contexto | Prefijo de clase | Ejemplo Vue | Ejemplo PHP |
|---|---|---|---|
| `central` | `Central` | `CentralUserIndex.vue` | `CentralUserController.php` |
| `tenant` | `Tenant` | `TenantUserIndex.vue` | `TenantUserController.php` |
| aplicación única | — | `UserIndex.vue` | `UserController.php` |

**Reglas adicionales:**
- El nombre del módulo siempre va en PascalCase (ej: `User`, `InvoiceItem`, `TaxReport`)
- Las migraciones son anónimas (`return new class extends Migration`) para evitar colisiones de nombres
- Los ServiceProviders llevan el nombre del módulo sin prefijo de contexto (`UserServiceProvider`, no `CentralUserServiceProvider`)
- Los Seeders, Jobs, Notifications y Commands **sí llevan prefijo de contexto** a partir de v3.1.0

---

## 🔀 Flujo de inyección de rutas

### Marcadores en `routes/web.php`

```php
// {{CENTRAL_ROUTES_END}}
```

### Marcadores en `routes/tenant.php`

```php
// {{TENANT_ROUTES_END}}
```

**Lo decide el ARCHIVO, no el contexto.** Cada archivo de rutas sirve a un contexto y solo a uno, así
que dentro hay **una** sección y un solo sitio donde crece: ahí entra la siguiente subfuncionalidad,
dentro del grupo que le da dominio y middleware.

### Proceso interno de inyección

```
1. resolveMarkerKey()   → contexto + route_file → clave del marcador
                          central + web.php         → CENTRAL_ROUTES_END
                          innodite + tenant.php      → TENANT_INNODITE_ROUTES_END

2. blockExists()        → busca firma del bloque existente
                          si ya existe: OMITE (operación idempotente)

3. detectIndentation()  → inspecciona el archivo destino
                          preserva espacios o tabs del estilo existente

4. ensureUseStatement() → verifica que existe `use App\Http\Controllers\...`
                          inserta el `use` si no está presente

5. buildBlock()         → genera el grupo de 7 rutas CRUD con comentario de cabecera

6. str_replace()        → inserta el bloque inmediatamente antes del marcador
                          el marcador permanece en su lugar para futuros módulos
```

### Un contexto por archivo de rutas

| Contexto | Archivo destino | Prefijo URL | Nombre de ruta |
|---|---|---|---|
| `central` | `routes/web.php` | `central-{funcionalidad}` | `central.` |
| `tenant` | `routes/tenant.php` | `tenant-{funcionalidad}` | `tenant.` |

**El archivo declara las rutas de su contexto y de ninguno más.** Hasta la 4.x un archivo podía
recibir un bloque por cada inquilino del catálogo, importando controladores que nadie había escrito:
el módulo salía sin una sola ruta utilizable y el fallo no se veía leyendo el código.

---

## 📋 Resumen de todos los comandos

| Comando | Descripción |
|---|---|
| `innodite:make-module {Name}` | Genera módulo completo con backend, vistas Vue y rutas |
| `innodite:add-entity {Module} {Entity}` | Agrega una entidad a un módulo existente |
| `innodite:module-setup` | Inicializa configuración del paquete en el proyecto |
| `innodite:doctor` | Diagnóstico en cascada: el entorno del generador y el contrato del proyecto |
| `innodite:crear-bd-test` | Clona el esquema real en la base `_test`, sin una sola fila |
| `innodite:publish-stubs` | Exporta las plantillas que quieras personalizar |
| ~~`innodite:migrate-plan`~~ | **Retirado** — el esquema lo aplica `innodite:deploy`, a través de los seeders |
| `innodite:migrate-one` | Ejecuta una migración puntual por coordenada |
| `innodite:deploy {stage\|production}` | Levanta el proyecto: esquema, datos, permisos y webmaster |
| `innodite:test` | Ejecuta el contrato de una subfuncionalidad, en cascada y con corte temprano |
| `vendor:publish --tag=module-maker-config` | Publica `make-module.php` |
| `vendor:publish --tag=module-maker-stubs` | Publica stubs contextuales personalizables |
| `vendor:publish --tag=module-maker-contexts` | Publica `contexts.json` de ejemplo |

> Son **tres** los tags publicables, y son los que el proveedor de servicios declara. El de
> `module-maker-frontend` que esta tabla listaba **no existe**: se fue con el andamiaje de frontend.

---

## 📊 Auditoría

`storage/logs/module_maker.log` — formato NDJSON (una entrada JSON por línea):

```json
{"timestamp":"2026-04-01T12:00:00+00:00","event":"module.created","package":"innodite/laravel-module-maker","version":"3.1.0","module":"User","context_key":"central","context_name":"App Central","routes":true}
```

| Evento | Cuándo se registra |
|---|---|
| `module.created` | Módulo completo generado correctamente |
| `module.components` | Componentes individuales añadidos a módulo existente |
| `routes.injected` | Rutas inyectadas exitosamente en el proyecto |
| `module.rollback` | Rollback ejecutado tras error durante la generación |

```php
// Acceso programático al log
ModuleAuditor::readLog();  // devuelve array de entradas
ModuleAuditor::logPath();  // devuelve ruta absoluta al archivo de log
```

---

## 🧪 Pruebas

```bash
composer test           # todos los tests
composer test:unit      # solo unitarios
composer test:feature   # solo integración
composer test:coverage  # con cobertura HTML en /coverage
```

Los tests generados se ubican todos en `Modules/{Name}/Tests/Feature/{Context}/{SubFuncionalidad}/`,
como un **grupo por subfuncionalidad**: un manifiesto (`{SubFunc}Contract.php`), su base
(`{SubFunc}TestCase.php`) y las piezas de los nueve temas —`ScaffoldTest`, `SchemaTest`,
`PermissionsTest`, `DeploymentTest` y `HttpTest`—. La del tema 6 la ejecuta Vitest y vive con el
JavaScript, en `resources/js/__tests__/{Context}/{SubFuncionalidad}/`.

Los emiten por igual `make-module` y `add-entity`: una subfuncionalidad nace con su grupo entero
venga por donde venga.

---

## 📏 Estándares de código

```bash
composer lint         # verificar PSR-12
composer lint:fix     # corregir automáticamente
composer lint:strict  # verificar declaraciones strict_types
```

El paquete incluye configuración de PHP CS Fixer compatible con PSR-12. Todos los archivos PHP generados incluyen `declare(strict_types=1)` por defecto.

---

## 📦 Publicar en Packagist / repositorio privado

### Repositorio público (Packagist)

```bash
git tag -a v4.2.0 -m "v4.2.0 — resumen de la versión"
git push origin refs/tags/v4.2.0
```

Luego registrar el repositorio en [packagist.org](https://packagist.org) con la URL del repositorio.

⚠️ **La etiqueta se empuja sola, por su referencia completa.** `--tags` arrastra todas las que tengas
en local, incluidas las de pruebas que no querías publicar — y una etiqueta publicada no se corrige:
hay que retirarla y rehacerla, cuando Packagist ya la ha servido.

⭐ Y **etiqueta después de cerrar el `CHANGELOG.md`**, no antes: lo que se publica es el árbol tal y
como está en ese commit, así que una versión etiquetada con su changelog aún en «sin publicar» sale
diciendo que no publica nada de lo que trae.

### Repositorio privado (VCS)

Agregar en el `composer.json` del proyecto consumidor:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/innodite/laravel-module-maker"
        }
    ]
}
```

```bash
composer require innodite/laravel-module-maker:^4.2
```

---

## 📚 Documentación

El manual de uso vive en **`docs/`**, dentro del propio paquete, y se declara en el `composer.json`
con `extra.innodite-docs`:

```json
{ "extra": { "innodite-docs": "docs/fichas.json" } }
```

Ocho fichas: instalación, elegir el modo, los comandos, crear un módulo, desplegar, las pruebas,
personalizar lo generado y **cuándo NO usarlo**.

⭐ **Vive aquí a propósito**: quien cambia un comando actualiza su ficha **en el mismo commit**. Una
documentación que se escribe en otro sitio se actualiza «después», y «después» es como se llega a un
manual que anuncia comandos que ya no existen.

---

## 📝 Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para el historial completo de versiones.

---

## 📄 Licencia

MIT — [Anthony Filgueira](https://www.innodite.com)
