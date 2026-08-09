import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

/**
 * El motor que ejecuta el tema 6 — la vista.
 *
 * El paquete genera la prueba (`{Componente}.test.js`) y su manifiesto, pero no puede generar el
 * entorno que los corre: Vitest, jsdom y el plugin de Vue son dependencias del proyecto, no del
 * paquete. Esto es ese entorno, ya configurado para encontrar lo que el generador escribe.
 *
 * Tres cosas que no son obvias y por las que este archivo existe:
 *
 *   · **`include` mira dentro de los módulos.** Las pruebas de vista viven en
 *     `Modules/<Modulo>/resources/js/__tests__/...`, junto al componente que prueban y no en una
 *     carpeta de tests global. Sin esta línea, `vitest run` no encuentra ninguna y termina en verde.
 *   · **El alias `@`** apunta al `resources/js` del proyecto, que es de donde el componente generado
 *     importa sus composables. Sin él, el `vi.mock('@/Composables/usePermissions')` de la prueba no
 *     resuelve y el fallo se lee como si el composable no existiera.
 *   · **`environment: 'jsdom'`** porque se monta un componente: sin DOM no hay nada que montar.
 */
export default defineConfig({
    plugins: [vue()],

    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },

    test: {
        environment: 'jsdom',
        include: [
            'Modules/**/resources/js/__tests__/**/*.test.js',
            'resources/js/**/__tests__/**/*.test.js',
        ],
    },
});
