📦 Laravel Module Maker

Genera módulos y componentes para Laravel de forma rápida, limpia y estructurada, siguiendo buenas prácticas de desarrollo.
🚀 Instalación
1. Instala el paquete vía Composer

Para comenzar a usar el paquete Laravel Module Maker en tu proyecto Laravel, sigue estos sencillos pasos:

    Instala el paquete vía Composer:

    Abre tu terminal y ejecuta el siguiente comando en la raíz de tu proyecto Laravel:

    composer require innodite/laravel-module-maker

    Esto realiza:

        Verificación o creación de la carpeta Modules/

        Copia de los archivos JSON de ejemplo (post.json, blog.json)

        Publicación de stubs que puedes personalizar

2. Configura el paquete y publica los stubs

Después de la instalación, es crucial ejecutar el comando de configuración del paquete. Esto publicará los archivos de configuración de ejemplo y los "stubs" (plantillas) que el paquete utiliza para generar los diferentes componentes. Publicar los stubs te permite personalizarlos si deseas modificar la estructura o el contenido por defecto de los archivos generados.

Ejecuta el siguiente comando:

```
php artisan innodite:module-setup
```

🧱 Modalidades de Creación
1. 🔧 Crear un Módulo Limpio

Crea una estructura de módulo completa con los componentes esenciales (Modelo, Controlador, Servicio, Repositorio, Request, Provider, Rutas, Migración, Seeder, Factory, Test) listos para ser personalizados. Es ideal para iniciar un nuevo módulo desde cero con una base sólida.

```
php artisan innodite:make-module <NombreDelModulo>
```

Ejemplo:

```
php artisan innodite:make-module Products
```

Estructura generada:

```
Modules/
└── Products/
    ├── config/
    ├── Http/
    │   ├── Controllers/ProductController.php
    │   └── Requests/ProductStoreRequest.php
    ├── Models/Product.php
    ├── Services/
    │   ├── Contracts/ProductServiceInterface.php
    │   └── ProductService.php
    ├── Repositories/
    │   ├── Contracts/ProductRepositoryInterface.php
    │   └── ProductRepository.php
    ├── Providers/ProductServiceProvider.php
    ├── Database/
    │   ├── Migrations/202X_XX_XX_XXXXXX_create_products_table.php
    │   ├── Seeders/ProductSeeder.php
    │   └── Factories/ProductFactory.php
    ├── routes/api.php
    ├── routes/web.php
    ├── resources/views/
    ├── resources/lang/
    └── Tests/Unit/ProductTest.php
```

2. 🧬 Crear un Módulo Dinámico (JSON)

Permite definir la estructura de tu módulo y sus componentes (modelos, atributos, relaciones) a través de un archivo de configuración JSON. Es perfecto para módulos complejos o para estandarizar la creación de módulos en tu equipo.

```
php artisan innodite:make-module <NombreDelModulo> --config=<ruta/a/tu/archivo.json>
```

Ejemplo:

```
php artisan innodite:make-module Blog --config=blog.json
```

Estructura JSON de ejemplo (blog.json):

```json
{
  "module_name": "Blog",
  "components": [
    {
      "name": "Post",
      "table": "posts",
      "attributes": [
        { "name": "title", "type": "string", "length": 255 },
        { "name": "body", "type": "text", "nullable": true },
        {
          "name": "comments",
          "type": "relationship",
          "relationship": {
            "type": "hasMany",
            "model": "Comment"
          }
        }
      ]
    },
    {
      "name": "Comment",
      "table": "comments",
      "attributes": [
        { "name": "body", "type": "string" },
        {
          "name": "post_id",
          "type": "foreignId",
          "references": "id",
          "on": "posts"
        },
        {
          "name": "post",
          "type": "relationship",
          "relationship": {
            "type": "belongsTo",
            "model": "Post"
          }
        }
      ]
    }
  ]
}
```

3. 🧩 Añadir Componentes Individuales

Si ya tienes un módulo y solo necesitas añadir un componente específico (ej. un nuevo modelo, controlador o migración), puedes usar esta modalidad.

```
php artisan innodite:make-module <NombreDelModuloExistente> --<opcion>=<NombreDelComponente>
```

Opciones disponibles:

Opción
	

Descripción

--model
	

Crea un nuevo modelo

--controller
	

Genera un controlador

--request
	

Crea un formulario Request

--service
	

Genera un servicio e interfaz

--repository
	

Crea un repositorio e interfaz

--migration
	

Crea una nueva migración

Ejemplos:

```
php artisan innodite:make-module Products --model=Category
```
php artisan innodite:make-module Sales --controller=OrderController
```
php artisan innodite:make-module Analytics --service=ReportService
```

✅ ¡Listo para usar!

Laravel Module Maker acelera tu desarrollo y mantiene tu código modular, limpio y profesional. ¡A crear sin límites!