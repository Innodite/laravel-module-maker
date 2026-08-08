/**
 * useAvisos — los mensajes de la pantalla, en un solo sitio.
 *
 * Sustituye a `alert()` y `confirm()`, que son las dos únicas formas de aviso que el navegador trae
 * de fábrica y las dos peores que puede tener una aplicación: bloquean el hilo, no se pueden
 * estilar, no se pueden probar sin interceptar `window`, y en un modal abierto tapan la pantalla
 * entera para decir «no se pudo guardar».
 *
 * **El estado vive fuera de la función a propósito.** Se declara aquí, en el módulo, así que las
 * tres pantallas —el listado y los modales que se abren encima— comparten la misma pila. Es lo que
 * permite que un aviso lanzado dentro de un modal siga viéndose después de cerrarlo: si el estado
 * viviera dentro del componente, el mensaje moriría con él justo cuando hay algo que explicar.
 *
 * Se usa así:
 *
 *     const { exito, error, confirmar } = useAvisos()
 *
 *     exito('Registro guardado.')
 *     if (!await confirmar('¿Eliminar este registro?')) return
 *
 * Y una sola vez, en la pantalla que monta todo, va el componente que los pinta:
 *
 *     <InnoditeAviso />
 */
import { ref } from 'vue'

/** La pila de avisos visibles. Compartida por todas las pantallas del módulo. */
const avisos = ref([])

/**
 * La confirmación en curso, o `null` si no hay ninguna.
 *
 * Guarda el `resolve` de la promesa que devolvió `confirmar()`: es lo que convierte una respuesta
 * del usuario —dos botones, en algún momento— en un `await` que se lee como código secuencial.
 */
const confirmacion = ref(null)

let siguienteId = 0

export function useAvisos() {
    /**
     * Apila un aviso y lo descarta solo pasados unos segundos.
     *
     * @param {'exito'|'error'|'info'} tipo
     * @param {string} mensaje
     * @param {{ permanente?: boolean, duracion?: number }} opciones
     */
    function notificar(tipo, mensaje, opciones = {}) {
        const id = ++siguienteId

        avisos.value.push({ id, tipo, mensaje })

        if (!opciones.permanente) {
            setTimeout(() => descartar(id), opciones.duracion ?? 6000)
        }

        return id
    }

    function exito(mensaje, opciones = {}) {
        return notificar('exito', mensaje, opciones)
    }

    function error(mensaje, opciones = {}) {
        return notificar('error', mensaje, opciones)
    }

    function info(mensaje, opciones = {}) {
        return notificar('info', mensaje, opciones)
    }

    function descartar(id) {
        avisos.value = avisos.value.filter((aviso) => aviso.id !== id)
    }

    /**
     * Pide una confirmación y espera la respuesta.
     *
     * Devuelve una promesa que se resuelve a `true` o `false` cuando el usuario pulsa. El que llama
     * escribe `if (!await confirmar(...)) return` y no tiene que saber nada del diálogo.
     *
     * @returns {Promise<boolean>}
     */
    function confirmar(mensaje, opciones = {}) {
        // Dos confirmaciones a la vez dejarían una promesa sin resolver para siempre; la anterior se
        // cierra en negativo antes de plantear la nueva.
        if (confirmacion.value) {
            responder(false)
        }

        return new Promise((resolver) => {
            confirmacion.value = {
                mensaje,
                titulo: opciones.titulo ?? 'Confirmar',
                textoConfirmar: opciones.textoConfirmar ?? 'Confirmar',
                textoCancelar: opciones.textoCancelar ?? 'Cancelar',
                peligrosa: opciones.peligrosa ?? false,
                resolver,
            }
        })
    }

    /** La usa el componente al pulsar uno de los dos botones. */
    function responder(valor) {
        const pendiente = confirmacion.value
        confirmacion.value = null
        pendiente?.resolver(valor)
    }

    /**
     * El mensaje de un error de red o de servidor, sin `undefined` en pantalla.
     *
     * Un 500 no trae `message`, y un fallo de conexión no trae ni `response`. Este es el único sitio
     * del módulo que decide qué se le enseña al usuario cuando el back no dijo nada.
     */
    function mensajeDeError(err, porDefecto = 'Ocurrió un error inesperado.') {
        return err?.response?.data?.message ?? porDefecto
    }

    return {
        avisos,
        confirmacion,
        notificar,
        exito,
        error,
        info,
        descartar,
        confirmar,
        responder,
        mensajeDeError,
    }
}
