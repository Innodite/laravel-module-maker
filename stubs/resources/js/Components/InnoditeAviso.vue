<script setup>
/**
 * InnoditeAviso — pinta los avisos y las confirmaciones del módulo.
 *
 * No recibe props ni guarda estado propio: lee el que comparte `useAvisos`, así que se monta **una
 * sola vez** en la pantalla raíz —el listado— y desde ahí sirve también a los modales que se abren
 * encima. Montarlo en cada componente duplicaría los mensajes en pantalla.
 *
 *     <InnoditeAviso />
 *
 * El prefijo `Innodite` del nombre no es decoración: este archivo se publica dentro de
 * `resources/js/Components/` del proyecto, donde ya suele haber un `Modal.vue` y un `Alert.vue` de
 * la plantilla que se instaló con el frontend. Un nombre genérico los pisaría.
 */
import { useAvisos } from '@/Composables/useAvisos'

const { avisos, confirmacion, descartar, responder } = useAvisos()

/** El color lo decide el tipo, y el tipo lo decide quien avisa. Nadie escribe clases a mano. */
const estilos = {
    exito: 'bg-green-50 border-green-200 text-green-800',
    error: 'bg-red-50 border-red-200 text-red-700',
    info:  'bg-blue-50 border-blue-200 text-blue-800',
}

const iconos = {
    exito: '✓',
    error: '!',
    info:  'i',
}
</script>

<template>
    <div>

        <!--
            La pila de avisos.

            Fija arriba a la derecha y por encima de cualquier modal: un error que ocurre dentro de
            un formulario tiene que verse sin cerrarlo.
        -->
        <div
            class="fixed top-4 right-4 z-[60] w-full max-w-sm space-y-2"
            role="status"
            aria-live="polite"
        >
            <div
                v-for="aviso in avisos"
                :key="aviso.id"
                :data-test="`aviso-${aviso.tipo}`"
                :class="estilos[aviso.tipo]"
                class="flex items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-sm"
            >
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/70 text-xs font-bold">
                    {{ iconos[aviso.tipo] }}
                </span>
                <p class="flex-1">{{ aviso.mensaje }}</p>
                <button
                    type="button"
                    data-test="aviso-cerrar"
                    class="shrink-0 opacity-50 transition hover:opacity-100"
                    aria-label="Cerrar aviso"
                    @click="descartar(aviso.id)"
                >
                    ×
                </button>
            </div>
        </div>

        <!--
            La confirmación.

            Sustituye a `confirm()`, que bloqueaba el hilo del navegador para preguntar. Aquí la
            espera la resuelve la promesa de `confirmar()`: quien llama sigue escribiendo
            `if (!await confirmar(...)) return`.
        -->
        <div
            v-if="confirmacion"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-900/50 p-4"
            data-test="confirmacion"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">

                <div class="px-6 pt-6">
                    <h2 class="text-lg font-semibold text-gray-800">{{ confirmacion.titulo }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ confirmacion.mensaje }}</p>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4">
                    <button
                        type="button"
                        data-test="confirmacion-cancelar"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-50"
                        @click="responder(false)"
                    >
                        {{ confirmacion.textoCancelar }}
                    </button>
                    <button
                        type="button"
                        data-test="confirmacion-aceptar"
                        :class="confirmacion.peligrosa
                            ? 'bg-red-500 hover:bg-red-600'
                            : 'bg-blue-600 hover:bg-blue-700'"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-white transition"
                        @click="responder(true)"
                    >
                        {{ confirmacion.textoConfirmar }}
                    </button>
                </div>

            </div>
        </div>

    </div>
</template>
