antes de revisar cualquier  archivo preguntarle al usuario  que desea hacer?

LUEGO DEBES TERMINAR DE REVISAR EL CLAUDE.md 

# Controladores — Renderizado de Vistas (REGLA OBLIGATORIA)
- TODOS los controladores deben usar el Trait `RendersInertiaModule` (`Innodite\LaravelModuleMaker\Traits\RendersInertiaModule`)
- El método `index()` SIEMPRE debe retornar `$this->renderModule('ModuleName', 'ContextPrefixModelIndex')`
- TODOS los métodos que redireccionen a una vista deben usar `renderModule()`
- NUNCA usar `Inertia::render()` directamente en un controlador — siempre a través del trait

# Arquitectura Frontend (REGLA OBLIGATORIA)
- Inertia.js se usa ÚNICAMENTE para navegación/redirección entre páginas (`router.visit()`, `router.get()`, etc.)
- TODOS los datos deben accederse vía axios (`axios.get`, `axios.post`, `axios.put`, `axios.delete`)
- NUNCA pasar datos del servidor a vistas Vue mediante props de Inertia
- Los controladores retornan JSON para operaciones de datos, NO `Inertia::render()`
- Las vistas Vue son shells de página que cargan sus propios datos al montarse via axios

# Arquitectura de archivos y carpetas (REGLA OBLIGATORIA)

El PAQUETE DEBE DE CREAR CADA VEZ QUE CREE UN MODULO EN CUALQUIER  CONTEXTO DEBE CREAR OBLIGATORIAMENTE SEGUN EL CONTEXTO LA LISTA DE ARCHIVOS QUE SE ENCUENTRA EN files-and-folders-structure.md

# OBLIGATORIO 

DEBES DE CONFIRMARME DESPUES DE LEER LAS REGLAS OBLIGATORIAS DEBES INFORMARME QUE LASLEISTE Y LAS GUARDASTE EN TU MEMORIA Y CONTEXTO  LUEGO 

DEBES GUARDAR EN TU MEMORIA Y EN TU CONTEXTO QUE DESPUES DE TERMINAR TODO LOQUE TE ASIGNE EL USUARIO DEBES VERIFICAR QUE EL PAQUETE FUNCIONA CORRECTAMENTE 