<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modo del proyecto — se elige UNA vez, al instalar
    |--------------------------------------------------------------------------
    |
    | Decide la forma de todo lo que se genera: si existe el eje de contexto
    | (Central/Tenant), si los nombres de clase llevan prefijo, si un tenant
    | declara su propia conexión y qué middleware protege cada ruta.
    |
    | Ningún comando lo adivina leyendo el proyecto ni asume uno por defecto:
    | adivinar produce archivos que parecen correctos y están mal en el único
    | sitio que nadie revisa, multiplicado por cada módulo. Sin modo elegido,
    | los comandos se niegan a generar y dicen cómo elegirlo.
    |
    |   'single-app'              Aplicación única, sin tenancy
    |   'multitenant-shared'      Todos los tenants comparten funcionalidad
    |   'multitenant-per-tenant'  Cada tenant con lógica de negocio propia
    |
    */
    'mode' => env('MODULE_MAKER_MODE'),

    /*
    |--------------------------------------------------------------------------
    | Clave primaria de las tablas generadas
    |--------------------------------------------------------------------------
    |
    |   'ulid'        ULID — lo que exige el patrón, y el valor por defecto
    |   'increments'  Entero autoincremental — el de Laravel, si lo prefieres
    |
    | A diferencia del modo, esta clave SÍ tiene valor por defecto: los tres modos son
    | igual de legítimos y adivinar uno produce una estructura equivocada, pero aquí hay
    | una respuesta correcta —el patrón pide ULID— y quien no diga nada la recibe.
    |
    | El ULID no es enumerable: con un entero en la URL se recorre la tabla probando
    | números, y en un multitenant eso cruza inquilinos.
    |
    | ⛔ Se elige AL INSTALAR y no se cambia después. Con tablas ya creadas, las foráneas
    | nuevas saldrían de un tipo que no puede referenciar a las claves existentes y la
    | restricción no llega a crearse. Cambiarla es una migración de datos de tu proyecto,
    | no un ajuste de configuración.
    |
    */
    'primary_key' => env('MODULE_MAKER_PRIMARY_KEY', 'ulid'),

    /*
    |--------------------------------------------------------------------------
    | El paquete de tenencia del proyecto — solo aplica en los modos multitenant
    |--------------------------------------------------------------------------
    |
    |   'stancl'  stancl/tenancy v3.10 — el único soportado hoy
    |   'none'    Ninguno soportado: la envoltura de las rutas la escribes tú
    |
    | Las rutas generadas de un proyecto multitenant necesitan envoltura: la aplicación
    | central se sirve en los dominios centrales, y una ruta de tenant tiene que
    | identificar a su tenant antes que nada. Esa envoltura no es genérica — está escrita
    | en el vocabulario del paquete de tenencia que use el proyecto—, así que el proyecto
    | la declara, igual que declara el modo. Ningún comando la adivina mirando el vendor.
    |
    | Con 'none' el paquete NO envuelve: escribe el archivo de rutas con un comentario que
    | dice dónde va la envoltura y qué haría stancl. El valor por defecto es ese y no
    | 'stancl' por la asimetría del fallo: escribir una envoltura de stancl en un proyecto
    | que no lo tiene produce un archivo de rutas que referencia clases inexistentes y la
    | aplicación deja de arrancar; dejarla fuera deja una nota visible en un archivo válido.
    |
    | ⛔ Los valores válidos son esos DOS y ninguno más. Cualquier otro es un error, no un
    | «todavía no soportado que se ignora»: la configuración diría que el proyecto corre con
    | un paquete y las rutas saldrían sin envoltura, que es la clase de contradicción que no
    | da la cara hasta que el módulo ya está sirviéndose. Cuando se integre otro paquete de
    | tenencia, se añade aquí y pasa a ser válido.
    |
    | Se elige AL INSTALAR, junto al modo, y en multitenant es OBLIGATORIO: sin elegirlo, el
    | instalador no continúa. En single-app no se pregunta — no hay tenants que identificar
    | ni dominios centrales que separar.
    |
    */
    'tenancy' => [
        'package' => env('MODULE_MAKER_TENANCY_PACKAGE', 'none'),
    ],

    /*
    |--------------------------------------------------------------------------
    | El frontend — qué componentes usan las vistas generadas
    |--------------------------------------------------------------------------
    |
    | ⛔ **Este paquete no configura el frontend de tu proyecto.** No publica composables, ni
    | componentes, ni toca `app.js`, `bootstrap.js` ni el middleware de Inertia. Solo GENERA las
    | vistas de cada subfuncionalidad. Quien monta el frontend es tu proyecto —o la biblioteca de
    | interfaz que uses—, y esa separación es deliberada: un generador que además instala el
    | andamiaje acaba peleándose con lo que el proyecto ya tenía.
    |
    | Lo que decides aquí es CONTRA QUÉ se generan esas vistas:
    |
    |   'default'   Vistas autónomas, sin depender de ninguna biblioteca.
    |   'innodite'  Vistas escritas con los componentes de la biblioteca de la casa.
    |
    */
    'frontend' => [

        'modo' => env('MODULE_MAKER_FRONTEND', 'default'),

        /*
        | El layout que envuelve cada pantalla generada.
        |
        | Vacío significa que la vista no importa ninguno y se dibuja suelta — que es lo correcto
        | mientras no sepas cuál es el tuyo. Se declara aquí y no se adivina porque el nombre y la
        | ruta del layout son de tu proyecto: escribir uno inventado produce una vista que no
        | compila, y el error aparece en el navegador del usuario, no al generar.
        |
        | Ejemplo:  '@/Layouts/AppLayout.vue'
        */
        'layout' => env('MODULE_MAKER_LAYOUT', ''),

    ],

    /*
    |--------------------------------------------------------------------------
    | Ruta raíz de todos los módulos del proyecto
    |--------------------------------------------------------------------------
    */
    'module_path' => base_path('Modules'),

    /*
    |--------------------------------------------------------------------------
    | Ruta de la carpeta de configuración del paquete (project root)
    | Publicada por: php artisan innodite:module-setup
    |--------------------------------------------------------------------------
    */
    'config_path' => base_path('module-maker-config'),

    /*
    |--------------------------------------------------------------------------
    | Ruta al archivo contexts.json del proyecto.
    | Si no existe, el paquete usa el template incluido en stubs/contexts.json
    |--------------------------------------------------------------------------
    */
    'contexts_path' => base_path('module-maker-config/contexts.json'),

    /*
    |--------------------------------------------------------------------------
    | Ruta base donde el paquete buscará stubs personalizados.
    | Estructura esperada: {stubs_path}/contextual/{stub_file}
    |--------------------------------------------------------------------------
    */
    'stubs' => [
        'path' => base_path('module-maker-config/stubs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Orden de despliegue — lo declaras tú, y se lee de arriba abajo
    |--------------------------------------------------------------------------
    |
    | Cada línea es la CARPETA de una subfuncionalidad, no un nombre de clase:
    |
    |     'UserManagement/Central/Role'     donde hay eje de contexto
    |     'Invoice/Invoice'                 donde no lo hay
    |
    | Por carpeta y no por FQCN para que renombrar una clase no rompa esta lista
    | —y para escribir mucho menos—. De ahí salen las tres piezas ejecutables de
    | cada subfuncionalidad: quien llama añade Stage, Production o Permissions
    | según lo que esté desplegando.
    |
    | EL ORDEN IMPORTA y el paquete no puede adivinarlo: una tabla con clave
    | foránea no puede sembrarse antes que aquella a la que apunta, y eso lo sabe
    | el negocio. `make-module` añade cada subfuncionalidad nueva AL FINAL;
    | moverla a su sitio es tuyo.
    |
    | Los marcadores son donde se añaden las entradas nuevas. Si los borras, el
    | comando te dice qué línea escribir a mano en vez de fallar.
    |
    | Con contextos se agrupa por contexto, y el maestro de cada uno lee el suyo:
    |
    |     'deploy' => [
    |         'central' => [
    |             'UserManagement/Central/Role',
    |             // {{DEPLOY_CENTRAL_END}}
    |         ],
    |         'tenant' => [
    |             'Invoice/Tenant/Shared/Invoice',
    |             // {{DEPLOY_TENANT_END}}
    |         ],
    |         // {{DEPLOY_END}}
    |     ],
    |
    */
    'deploy' => [
        // {{DEPLOY_END}}
    ],

    /*
    |--------------------------------------------------------------------------
    | El webmaster — el rol que lo puede todo
    |--------------------------------------------------------------------------
    |
    | Su seeder recoge TODOS los permisos que existan en la base donde corra,
    | así que un módulo generado mañana queda cubierto sin tocar ninguna lista.
    | Lo único que no puede adivinar es quién es esa persona.
    |
    | Se declara aquí, y no se lee del entorno dentro del seeder, porque un
    | proyecto en producción cachea la configuración (`config:cache`) y entonces
    | el `.env` ya no se carga: un `env()` fuera de config/ devolvería el valor
    | por defecto y el webmaster se crearía con OTRO correo y OTRA contraseña,
    | sin un solo error. Leído desde aquí, el valor queda horneado en la caché.
    |
    | `password` sin declarar NO es un problema: al crear el usuario se genera
    | una aleatoria y se imprime una sola vez. Lo que no puede haber es una
    | contraseña por defecto escrita en el código, igual en cada instalación.
    |
    */
    'webmaster' => [
        'role'     => env('WEBMASTER_ROLE', 'webmaster'),
        'name'     => env('WEBMASTER_NAME', 'Webmaster'),
        'email'    => env('WEBMASTER_EMAIL', 'webmaster@innodite.local'),
        'password' => env('WEBMASTER_PASSWORD'),
    ],
];
