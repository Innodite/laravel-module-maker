El modo decide la **forma** de todo lo que se genera, y se elige una vez, al instalar.

| Modo | Cuándo | Qué cambia en lo generado |
|---|---|---|
| `single-app` | Una aplicación, una base de datos | Sin contextos: `--context` no aplica |
| `multitenant-shared` | Varios clientes, **la misma** funcionalidad | Los modelos y seeders **no** declaran conexión |
| `multitenant-per-tenant` | Varios clientes, lógica **distinta** por cliente | Cada contexto lleva su conexión escrita |

```bash
php artisan innodite:module-setup --mode=single-app
```

## ⛔ Ningún comando adivina el modo

Y ninguno asume uno por defecto: sin modo elegido se niegan a generar y dicen cómo elegirlo.

Es la decisión de diseño más importante del paquete. Un valor por defecto puesto «para que nadie
note nada» no produce un error: produce una **estructura equivocada, multiplicada por cada módulo**
que se genere a partir de ahí, y descubierta mucho después.

## La diferencia entre los dos multitenant no es de matiz

Declarar `per-tenant` donde los clientes comparten funcionalidad **ata cada modelo a un solo
cliente**. Declararlo `shared` donde cada uno tiene su propia estructura deja el código apuntando a
la conexión que estuviera activa en ese momento.

⭐ **Por eso en `shared` los seeders salen sin conexión declarada, y está bien.** Con clientes
iguales, la conexión la conmuta el paquete de tenencia al entrar en el contexto. Nombrar una
conexión ahí ataría el modelo a un cliente y rompería justo lo que protege.

## El paquete de tenencia también se declara

```bash
php artisan innodite:module-setup --mode=multitenant-shared --tenancy=stancl
```

Los archivos de rutas de un proyecto multiinquilino necesitan una envoltura, y esa envoltura está
escrita en el vocabulario del paquete de tenencia concreto. Si el tuyo no está soportado, el
generador **no la inventa**: deja el archivo con una nota que dice dónde va y qué haría.
