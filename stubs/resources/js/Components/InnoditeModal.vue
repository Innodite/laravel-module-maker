<script setup>
/**
 * InnoditeModal — la ventana donde ocurren crear, ver y editar.
 *
 * De las seis rutas que el paquete genera por subfuncionalidad, **solo `index` devuelve una
 * pantalla**; las otras cinco devuelven datos. Por eso las tres acciones se resuelven sobre el
 * mismo listado, en un modal, y no navegando a rutas que no existen.
 *
 * Sabe hacer tres cosas, y las tres responden al mismo requisito: **que el usuario no tenga que
 * hacer scroll para rellenar un formulario**.
 *
 *  1. **Tamaño** — `tamano` decide el ancho. Un alta de tres campos no necesita el ancho de una de
 *     veinte, y una de veinte no cabe en el de tres.
 *  2. **Rejilla** — `columnas` reparte los campos en varias columnas en vez de apilarlos. Un campo
 *     que necesite la fila entera se marca con `class="sm:col-span-2"` donde se declara.
 *  3. **Pasos** — cuando ni con rejilla cabe, `pasos` parte el formulario en un asistente. El
 *     contenido recibe el paso actual por slot y decide qué enseña en cada uno.
 *
 * El modal no valida ni guarda: emite `guardar` y quien lo monta decide. Mantener aquí la llamada
 * al back ataría cada formulario a esta ventana.
 *
 *     <InnoditeModal
 *         titulo="Nuevo registro"
 *         tamano="lg"
 *         :columnas="2"
 *         :pasos="['Datos generales', 'Detalle']"
 *         :guardando="saving"
 *         @guardar="submit"
 *         @cerrar="$emit('cerrar')"
 *     >
 *         <template #default="{ paso }">
 *             <div v-show="paso === 0"> ...campos... </div>
 *         </template>
 *     </InnoditeModal>
 */
import { computed, onMounted, onBeforeUnmount, ref, useSlots } from 'vue'

const props = defineProps({
    titulo:      { type: String,  default: '' },
    /** sm · md · lg · xl · full */
    tamano:      { type: String,  default: 'md' },
    /** Columnas de la rejilla de campos: 1, 2 o 3. */
    columnas:    { type: Number,  default: 1 },
    /** Nombres de los pasos. Con menos de dos, el modal es de una sola página. */
    pasos:       { type: Array,   default: () => [] },
    guardando:   { type: Boolean, default: false },
    cargando:    { type: Boolean, default: false },
    textoGuardar: { type: String, default: 'Guardar' },
    /** Un formulario a medio llenar no se cierra por un clic fuera; el detalle sí puede. */
    cerrarAlClicarFondo: { type: Boolean, default: false },
})

const emit = defineEmits(['cerrar', 'guardar'])

const slots = useSlots()

const pasoActual = ref(0)

const esAsistente = computed(() => props.pasos.length > 1)
const esUltimoPaso = computed(() => !esAsistente.value || pasoActual.value === props.pasos.length - 1)

/**
 * Las clases se escriben enteras, no se arman concatenando.
 *
 * Tailwind lee los archivos en busca de nombres de clase completos: un `max-w-${tamano}` construido
 * en tiempo de ejecución no aparece en el CSS compilado y el modal sale sin ancho.
 */
const anchos = {
    sm:   'max-w-md',
    md:   'max-w-2xl',
    lg:   'max-w-4xl',
    xl:   'max-w-6xl',
    full: 'max-w-[95vw]',
}

const rejillas = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
}

const claseAncho   = computed(() => anchos[props.tamano] ?? anchos.md)
const claseRejilla = computed(() => rejillas[props.columnas] ?? rejillas[1])

function siguiente() {
    if (!esUltimoPaso.value) pasoActual.value++
}

function anterior() {
    if (pasoActual.value > 0) pasoActual.value--
}

function alPulsarPrincipal() {
    esUltimoPaso.value ? emit('guardar') : siguiente()
}

function cerrar() {
    emit('cerrar')
}

function alClicarFondo() {
    if (props.cerrarAlClicarFondo) cerrar()
}

/** Escape cierra: es lo que el usuario ya intenta antes de buscar el botón. */
function alPulsarTecla(evento) {
    if (evento.key === 'Escape') cerrar()
}

onMounted(() => {
    document.addEventListener('keydown', alPulsarTecla)
    // Sin esto, la página de detrás sigue desplazándose bajo el modal.
    document.body.classList.add('overflow-hidden')
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', alPulsarTecla)
    document.body.classList.remove('overflow-hidden')
})
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
        data-test="modal"
        role="dialog"
        aria-modal="true"
        @click.self="alClicarFondo"
    >
        <div
            :class="claseAncho"
            class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-xl bg-white shadow-xl"
        >

            <!-- Cabecera -->
            <div class="flex items-start justify-between border-b border-gray-100 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">{{ titulo }}</h2>
                    <p v-if="esAsistente" class="mt-1 text-xs text-gray-400">
                        Paso {{ pasoActual + 1 }} de {{ pasos.length }} — {{ pasos[pasoActual] }}
                    </p>
                </div>
                <button
                    type="button"
                    data-test="modal-cerrar"
                    class="text-2xl leading-none text-gray-400 transition hover:text-gray-600"
                    aria-label="Cerrar"
                    @click="cerrar"
                >
                    ×
                </button>
            </div>

            <!-- Los pasos, cuando los hay -->
            <div v-if="esAsistente" class="flex gap-2 border-b border-gray-100 px-6 py-3">
                <div
                    v-for="(nombre, indice) in pasos"
                    :key="nombre"
                    :data-test="`modal-paso-${indice}`"
                    :class="indice <= pasoActual ? 'bg-blue-600' : 'bg-gray-200'"
                    class="h-1 flex-1 rounded-full transition"
                    :title="nombre"
                />
            </div>

            <!-- Cuerpo: es lo único que se desplaza, y solo si no cabe -->
            <div class="flex-1 overflow-y-auto px-6 py-5">
                <div v-if="cargando" class="flex justify-center py-12">
                    <span class="text-sm text-gray-400">Cargando...</span>
                </div>

                <div v-else :class="claseRejilla" class="grid gap-x-6 gap-y-4">
                    <slot :paso="pasoActual" />
                </div>
            </div>

            <!-- Pie: acciones propias si se dan, y si no las de guardar -->
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                <slot name="pie" :paso="pasoActual" :es-ultimo-paso="esUltimoPaso">
                    <button
                        v-if="esAsistente && pasoActual > 0"
                        type="button"
                        data-test="modal-anterior"
                        class="mr-auto rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm text-gray-600 transition hover:bg-gray-50"
                        @click="anterior"
                    >
                        Anterior
                    </button>
                    <button
                        type="button"
                        data-test="modal-cancelar"
                        class="rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm text-gray-600 transition hover:bg-gray-50"
                        @click="cerrar"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        data-test="modal-guardar"
                        :disabled="guardando"
                        class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                        @click="alPulsarPrincipal"
                    >
                        {{ guardando ? 'Guardando...' : (esUltimoPaso ? textoGuardar : 'Siguiente') }}
                    </button>
                </slot>
            </div>

        </div>
    </div>
</template>
