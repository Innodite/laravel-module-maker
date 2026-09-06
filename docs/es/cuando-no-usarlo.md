El paquete escribe una estructura concreta, con una opinión detrás. Cuando esa opinión no es la
tuya, forzarlo cuesta más que no usarlo.

## No lo uses si…

**Tu proyecto no separa las capas.** El generador escribe siempre la misma cadena: la validación en
un form request, el controlador que delega en un servicio, el servicio que pregunta al repositorio y
**solo el repositorio tocando el modelo**. Si tu proyecto consulta el modelo desde el controlador,
lo generado va a chocar con lo que ya tienes en cada módulo.

**Quieres un CRUD y nada más.** Un módulo generado trae seis rutas con su permiso cada una, los
seeders que crean esos permisos, la pantalla y su grupo de pruebas. Para una tabla auxiliar de tres
campos que solo toca un administrador, es más andamiaje del que vas a mantener.

**Tu aplicación no es Laravel con Inertia y Vue.** Las pantallas generadas asumen esa combinación.
El backend te serviría igual, pero tendrías que tirar las vistas en cada módulo.

**Necesitas que un módulo tenga una forma distinta a los demás.** El valor del paquete es que todos
se parezcan. Si el tuyo es la excepción, escríbelo a mano: personalizar las plantillas para un solo
caso convierte la excepción en la norma de todo lo que generes después.

## Sí te compensa cuando…

- Vas a crear **varios** módulos con la misma forma, y quieres que sigan pareciéndose dentro de un
  año.
- Te importa que cada ruta tenga su permiso y que ese permiso **exista de verdad**, creado por un
  seeder, y no dependa de que alguien se acuerde.
- Trabajas con varios clientes y necesitas que el mismo módulo se despliegue en el contexto correcto
  sin decidirlo a mano cada vez.

## Y una advertencia sobre el punto intermedio

⚠️ **Generar el módulo y después reescribirlo entero a mano es el peor de los dos mundos.** Queda un
módulo que parece generado pero ya no lo es: la próxima vez que ejecutes `add-entity` sobre él, lo
que se inyecte no encajará con lo que hay. Si vas a reescribirlo, no lo generes.
