<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

/**
 * RequestNames — cómo se llaman los FormRequests de una subfuncionalidad.
 *
 * El tercer hermano de `SeederNames` y `TestNames`, y nace por la misma razón que los otros dos: un
 * nombre que necesitan **varios lados que no se hablan** deja de coincidir en cuanto uno cambia.
 *
 * Aquí los lados son tres, y ninguno puede fallar:
 *
 *     RequestGenerator     los escribe
 *     ControllerGenerator  los importa y los pone en la firma
 *     TestGenerator        los declara en el manifiesto del contrato, para que el andamiaje los mire
 *
 * **Ya falló, y de la peor manera.** El manifiesto del contrato declaraba siempre `…StoreRequest`
 * mientras el generador, en uno de sus tres caminos, escribía un `…Request` genérico: la prueba del
 * andamiaje pedía una clase que en ese modo nadie escribía nunca. No es un fallo que se lea en el
 * código —los dos lados están bien por separado—, solo aparece al generar en ese modo concreto.
 *
 * **Por qué dos y no uno.** Toda subfuncionalidad nace con los dos, como base del patrón: las reglas
 * de un alta y las de una edición casi nunca son iguales —el `unique` que tiene que ignorarse a sí
 * mismo al editar es el ejemplo de todos los días— y un solo FormRequest obliga a preguntar por el
 * método dentro de `rules()`, que es donde la validación empieza a esconderse.
 *
 * Que una funcionalidad concreta necesite un tercero lo decide su desarrollador. El paquete entrega
 * los dos que hacen falta siempre.
 */
final class RequestNames
{
    /** El sufijo de cada uno. La acción del controlador que lo recibe, en StudlyCase. */
    public const STORE  = 'StoreRequest';
    public const UPDATE = 'UpdateRequest';

    /**
     * El nombre de **una** pieza: `{Prefijo}{SubFuncionalidad}{Sufijo}`.
     *
     * El prefijo llega vacío en aplicación única —ahí no hay contextos que desambiguar— y con el
     * contexto delante en multitenant. Lo resuelve `getClassPrefix()`, que ya pregunta al modo.
     */
    public static function piece(string $classPrefix, string $subFeature, string $suffix): string
    {
        return $classPrefix . $subFeature . $suffix;
    }

    /** El FormRequest del alta. Lo recibe `store()`. */
    public static function store(string $classPrefix, string $subFeature): string
    {
        return self::piece($classPrefix, $subFeature, self::STORE);
    }

    /** El FormRequest de la edición. Lo recibe `update()`. */
    public static function update(string $classPrefix, string $subFeature): string
    {
        return self::piece($classPrefix, $subFeature, self::UPDATE);
    }

    /**
     * Los dos, en el orden en que el controlador los declara.
     *
     * Lo lee el generador que los escribe y el que los declara en el manifiesto: recorrer esta lista
     * es lo que impide que uno de los dos se quede sin generar o sin vigilar.
     *
     * @return array<string, string>  acción del controlador => nombre de la clase
     */
    public static function all(string $classPrefix, string $subFeature): array
    {
        return [
            'store'  => self::store($classPrefix, $subFeature),
            'update' => self::update($classPrefix, $subFeature),
        ];
    }
}
