# Migrar de la v3 a la v4

La v4 es una **major**, y lo es de verdad: cambia lo que generan los comandos que ya usabas, cambia
los nombres de la mitad de ellos y añade una decisión —el modo del proyecto— que antes se adivinaba.
Nada de eso ocurre solo al actualizar el paquete.

**La v3 sigue instalable.** Si no quieres migrar todavía, fija `"innodite/laravel-module-maker": "^3.6"`
y no pasa nada. Esta guía es para cuando decidas dar el salto.

Lo que sigue es el orden en el que conviene hacerlo. Cada paso dice **cómo se nota si te lo saltas**,
porque casi todos fallan de forma silenciosa: el módulo se genera igual y el problema aparece días
después, en el despliegue.

---

## 0. Actualiza el paquete

```bash
composer require innodite/laravel-module-maker:^4.0
```

Al terminar, y antes de generar nada, lanza el diagnóstico:

```bash
php artisan innodite:doctor
```

Ese comando es el mapa de esta guía. Recorre el entorno del generador, el contrato del proyecto
anfitrión y las reglas del criterio, y **cada fallo trae en su propia línea qué hacer**. Si el
diagnóstico pasa entero en verde, has terminado de migrar.

---

## 1. Elige el modo del proyecto

Es el cambio de fondo de la v4. Un proyecto puede ser de tres formas, y **el paquete ya no lo
adivina leyendo el código**:

| Modo | Cuándo | Qué implica |
|------|--------|-------------|
| `single-app` | Una aplicación, una base de datos | No hay contextos: `--context` no se usa |
| `multitenant-shared` | Varios clientes, **misma funcionalidad** | Los seeders y modelos **no** declaran conexión: el paquete de tenencia la conmuta al entrar en el contexto |
| `multitenant-per-tenant` | Varios clientes, **lógica distinta** | Cada contexto declara su `connection_key`, y el código generado lo lleva escrito |

```bash
php artisan innodite:module-setup
```

**Si te lo saltas:** los comandos se niegan a generar y dicen cómo elegirlo. Es el único fallo de
esta guía que no es silencioso, y está hecho a propósito: un modo por defecto «para que nadie note
nada» produce una estructura equivocada multiplicada por cada módulo que generes.

⛔ **La diferencia entre los dos modos multitenant no es de matiz.** Declarar `per-tenant` donde los
clientes comparten funcionalidad ata cada modelo generado a un solo cliente; declarar `shared` donde
cada uno tiene su estructura deja el código apuntando a la conexión que toque en ese momento.

---

## 2. Publica la configuración

```bash
php artisan vendor:publish --tag=module-maker-config
```

Esto crea `config/make-module.php`, y ahí vive **el orden de despliegue** (`deploy`): la lista de
subfuncionalidades que el despliegue del proyecto recorre. Cada vez que generes un módulo, el
paquete se declara solo en esa lista.

**Si te lo saltas:** el módulo se genera entero y correcto, y **no queda declarado en ninguna
parte**. Lo que te queda es un módulo completo en disco que ningún despliegue levanta: sus tablas no
se crean y sus permisos no existen, así que la pantalla generada no abre para nadie. El generador lo
avisa mientras escribe, en una línea entre cuarenta, y esa línea se lee una vez. Desde la v4 el
diagnóstico también lo comprueba.

---

## 3. Borra los stubs de la v3

Si en su día publicaste los stubs para personalizarlos, los tienes en
`module-maker-config/stubs/contextual/`. **Los de la v3 no sirven en la v4**: cambió el formato de
los marcadores —de `{{ clave }}` a `{{{ clave }}}`— y cambió el juego de piezas.

- **Si no los personalizaste** (el caso más común): bórralos y usa los del paquete.

  ```bash
  rm -rf module-maker-config/stubs
  ```

- **Si sí los personalizaste**: republica los del paquete y vuelve a aplicar tus cambios encima.

  ```bash
  php artisan vendor:publish --tag=module-maker-stubs --force
  ```

**Si te lo saltas:** la generación arranca, escribe las carpetas y los documentos, y muere en el
primer stub. Lo que queda en disco no es un módulo completo. El diagnóstico detecta los stubs en
formato v3 y los lista por nombre.

⛔ **Y hay una variante que el diagnóstico no puede ver:** un stub publicado **con una versión
anterior de la v4**. El formato es el correcto, así que pasa el examen, pero tapa al del paquete y
sigue generando el código viejo. Si actualizas el paquete y algo sigue comportándose como antes,
mira si ese archivo está publicado en tu proyecto.

---

## 4. Retira los comandos locales que tapan a los del paquete

En la v3 era normal escribirse en el proyecto un comando que el paquete no traía —el más habitual,
el que clona la base de datos de pruebas—. En la v4 esos comandos **ya vienen dentro**, y si el tuyo
se llama igual, gana el del proyecto.

Busca en `app/Console/Commands/` cualquier comando cuya firma empiece por `innodite:` y compárala con
la lista del paso 6. Si el paquete ya lo trae, borra el tuyo.

**Si te lo saltas:** te quedas con la versión antigua del comando sin enterarte, porque el nombre
responde igual. El diagnóstico avisa de las colisiones, en «Comandos: ninguno tapado».

---

## 5. Revisa el catálogo de contextos

`module-maker-config/contexts.json` describe los contextos del proyecto. En la v4 **cada modo exige
el suyo**: una aplicación única no tiene contextos, un proyecto de tenants iguales tiene central y
tenant, y uno de lógica por cliente tiene un bloque por cliente con su `connection_key`.

El catálogo de la v3 suele seguir sirviendo; lo que ya no sirve es tenerlo a medias. El diagnóstico
lo comprueba entero y dice qué clave falta en qué contexto.

---

## 6. Los comandos cambiaron de nombre

Los nombres de la v3 describían el mecanismo; los de la v4 describen lo que hacen.

| v3 | v4 | Qué hace |
|----|----|----------|
| `innodite:module-check` | `innodite:doctor` | Diagnostica el entorno y el contrato del proyecto, en cascada |
| `innodite:test-module` | `innodite:test` | Ejecuta el contrato de pruebas de una subfuncionalidad |
| `innodite:check-env` | *(absorbido por `innodite:doctor`)* | — |
| `innodite:seed-one` | *(absorbido por `innodite:deploy`)* | — |
| `innodite:migration-sync` | *(absorbido por `innodite:migrate-plan`)* | — |
| `innodite:test-sync` | *(retirado)* | — |
| — | `innodite:deploy` | **Nuevo.** Levanta el proyecto entero en el orden declarado |
| — | `innodite:crear-bd-test` | **Nuevo.** Clona el esquema real en la base `_test`, sin una sola fila |

Si tienes scripts de despliegue, un `Makefile` o tareas de CI que llamen a los nombres viejos,
actualízalos: los antiguos ya no existen y fallan con «command not found».

---

## 7. Registra el puente de contexto

*Solo en proyectos con contextos (los dos modos multitenant).*

La vista generada lee `auth.context` para saber a qué cliente sirve, y quien lo pone es un middleware
del paquete. Regístralo en el grupo `web`, **después** del middleware de Inertia:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', [
        \Innodite\LaravelModuleMaker\Middleware\InnoditeContextBridge::class,
    ]);
})
```

⛔ **El orden importa y no es simétrico.** El puente mezcla el contexto dentro de `auth`, e Inertia
comparte con una fusión superficial: registrado *antes*, el middleware de Inertia sobrescribe la
clave `auth` entera y se lleva el contexto por delante.

**Si te lo saltas:** la pantalla generada carga perfecta y no sabe a qué cliente sirve. El
diagnóstico lo comprueba y te da el bloque exacto para pegar.

---

## 8. Los seeders de despliegue

La v4 introduce un despliegue del proyecto entero. El instalador (paso 1) escribe los seeders en
`database/seeders/`: uno en aplicación única, y dos —central y tenant— en multitenant. Son **del
proyecto**, no del paquete: puedes leerlos y ampliarlos.

Llámalos desde tu `DatabaseSeeder`, antes de tus propios seeders maestros:

```php
public function run(): void
{
    $this->call(InnoditeDeploySeeder::class);   // o InnoditeCentralDeploySeeder en multitenant
    // … lo tuyo
}
```

Y para desplegar:

```bash
php artisan innodite:deploy stage                              # aplicación única
php artisan innodite:deploy stage --context=central            # multitenant, la central
php artisan innodite:deploy stage --context=tenant --tenant=acme
php artisan innodite:deploy stage --context=tenant --all
```

⛔ **En el contexto de tenant hay que decir a cuál**, con `--tenant=<clave>` o `--all`. Sin elegir, el
comando no arranca. Cuando los clientes comparten funcionalidad, el despliegue **entra en el contexto
de cada uno**: en HTTP eso lo hace el middleware de identificación, pero en consola no hay middleware
que lo haga, y un despliegue que no entra escribe en la base central creyendo que escribe en la del
cliente.

---

## 9. La base de datos de pruebas

El contrato de pruebas corre contra una base `_test` que es **clon del esquema real, sin una sola
fila**:

```bash
php artisan innodite:crear-bd-test
```

Declárala en el `phpunit.xml` del proyecto, como siempre:

```xml
<env name="DB_DATABASE" value="tu_base_test"/>
```

No necesitas `force="true"`: desde la v4 el paquete entrega esas variables al proceso que lanza la
suite, así que ganan sobre las del entorno —que en un contenedor traen la base real—.

Re-clonar es un **paso del ciclo**, no algo que ocurra en cada corrida. `innodite:test` trae
`--reclonar`, `--sin-reclonar` y `--repetir=N` para clasificar un rojo antes de investigarlo: base
sucia, intermitente o defecto de verdad.

---

## 10. Vuelve a lanzar el diagnóstico

```bash
php artisan innodite:doctor
```

Cuando pase entero, has migrado. Y antes de dar por bueno un módulo generado, despliégalo y ejecuta
su contrato: es la única comprobación que mira los efectos y no los archivos.

```bash
php artisan innodite:deploy stage --context=…
php artisan innodite:test <Módulo> <SubFuncionalidad> --context=…
```

---

## Preguntas que salen siempre

**¿Tengo que regenerar los módulos que ya tenía?**
No. Los módulos de la v3 siguen funcionando: son archivos de tu proyecto y el paquete no los toca. Lo
que cambia es lo que se genere **a partir de ahora**. Regenera solo si quieres que un módulo viejo
adopte las piezas nuevas.

**¿Puedo tener módulos de la v3 y de la v4 conviviendo?**
Sí. Lo que no puedes es tener stubs de la v3 publicados mientras generas con la v4 (paso 3).

**¿Y si algo sigue comportándose como en la v3 después de actualizar?**
Mira, por este orden: un stub publicado que tape al del paquete (paso 3), un comando local con el
mismo nombre (paso 4), y la configuración cacheada (`php artisan config:clear`).
