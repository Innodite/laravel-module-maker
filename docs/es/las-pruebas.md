Cada subfuncionalidad generada trae su grupo de pruebas, y se ejecuta así:

```bash
php artisan innodite:crear-bd-test
php artisan innodite:test Invoice Payment
```

## La base de pruebas es un clon del esquema real, sin filas

`crear-bd-test` copia el esquema de tu base real a la base de pruebas y **no copia ni un dato**.

⛔ **Re-clonarla es un paso del ciclo, no algo que ocurra en cada corrida.** Rehacerla siempre
esconde justo lo que hay que ver: una prueba que solo pasa con la base recién hecha está contando
algo, y borrar la evidencia antes de leerla lo hace indiagnosticable.

## Un rojo se clasifica antes de investigarse

El orden importa, porque investigar un intermitente como si fuera un defecto cuesta horas:

| Sospecha | Cómo se comprueba |
|---|---|
| La base venía sucia | `--reclonar` — rehace la base antes de empezar |
| Es intermitente | `--repetir=3` — repite la pieza que falló |
| Es un defecto de verdad | `--sin-reclonar` — conserva el estado para poder mirarlo |

⭐ **Y si pasa tras re-clonar, eso es lo que se reporta**: «pasó tras re-clonar», no un verde limpio.
Son cosas distintas y confundirlas oculta un problema de datos que va a volver.

## Acotar y continuar

```bash
php artisan innodite:test Invoice Payment --filter=puede_crear
php artisan innodite:test Invoice Payment --continuar
```

Por defecto el grupo **corta en la primera pieza que falla**: las siguientes dependen de ella y sus
rojos serían ruido. `--continuar` desactiva ese corte cuando lo que quieres es el panorama completo.

## Qué se prueba

Las capas —que el controlador delegue, que solo el repositorio toque el modelo—, los permisos de
cada ruta, el esquema, el despliegue y las pantallas. En contexto de cliente, la prueba **aparta la
identificación por dominio**: en una suite no hay dominio de cliente y cada petición moriría con un
404 donde se espera un 403. Lo que se mide es la puerta del permiso.
