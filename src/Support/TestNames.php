<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

/**
 * TestNames — cómo se llama el grupo de pruebas de una subfuncionalidad, y en qué orden se ejecuta.
 *
 * El hermano de `SeederNames`, para el otro contrato del patrón. Por subfuncionalidad hay **seis
 * piezas** —los nueve temas repartidos— más su manifiesto y su base:
 *
 *     {Prefijo}{SubFunc}Contract      el manifiesto: lo no derivable, y nada más
 *     {Prefijo}{SubFunc}TestCase      la base del grupo: la derivación, en un solo sitio
 *     {Prefijo}{SubFunc}ScaffoldTest      tema 0     · las piezas existen y su contenido cumple
 *     {Prefijo}{SubFunc}SchemaTest        temas 1-2  · tablas y columnas
 *     {Prefijo}{SubFunc}PermissionsTest   temas 3-5  · ruta ↔ permiso, y 403/200
 *     {Prefijo}{SubFunc}DeploymentTest    tema 8     · el despliegue, ejecutado
 *     {Prefijo}{SubFunc}HttpTest          tema 7     · el comportamiento del negocio
 *     {Componente}.test.js                tema 6     · la vista, con Vitest
 *
 * **Por qué existe esta clase y no son literales donde hagan falta.** Los nombres los necesitan dos
 * lados que no se hablan: el **generador**, que los escribe, y el **comando** que los ejecuta en
 * cascada. Calculados por separado, el día que una pieza cambie de nombre el generador emitirá el
 * nuevo y el comando seguirá buscando el viejo — y no dará error: dirá que la subfuncionalidad no
 * tiene esa prueba, que es lo mismo que decir que está en verde.
 *
 * Es el mismo motivo por el que `SeederNames` existe, y el mismo defecto que ya costó una tarde en
 * la fase 4: dos mitades correctas por separado que dejaron de apuntar al mismo sitio.
 */
final class TestNames
{
    /**
     * El manifiesto del grupo. No es una prueba: es lo que las pruebas leen.
     */
    public const CONTRACT = 'Contract';

    /**
     * La base del grupo. Tampoco se ejecuta sola — no declara ningún `test_`.
     */
    public const BASE = 'TestCase';

    /**
     * Las piezas ejecutables **en el orden de la cascada**, con el tema que cubre cada una.
     *
     * **El orden no es alfabético ni histórico: es de dependencia.** Cada pieza da por supuesto lo
     * que la anterior comprobó. Si el andamiaje no está, el esquema falla por lo mismo; si el
     * esquema no está, los permisos fallan por lo mismo; y el comportamiento por HTTP falla, de
     * treinta formas distintas, por el mismo motivo único.
     *
     * De ahí el corte temprano: treinta fallos rojos de una sola causa no informan
     * treinta veces mejor que uno — informan **peor**, porque hay que leerlos todos para descubrir
     * que eran el mismo.
     *
     * @var array<string, string>  sufijo => qué cubre
     */
    public const CASCADE = [
        'ScaffoldTest'    => 'tema 0 · el andamiaje existe y su contenido cumple',
        'SchemaTest'      => 'temas 1-2 · las tablas y sus columnas',
        'PermissionsTest' => 'temas 3-5 · permiso único por ruta, sembrado, y la puerta',
        'DeploymentTest'  => 'tema 8 · el despliegue, ejecutado de verdad',
        'HttpTest'        => 'tema 7 · el comportamiento del negocio',
    ];

    /**
     * El tema 6 va aparte porque **lo ejecuta otro motor**: es JavaScript, y lo corre Vitest.
     *
     * No es una excepción del contrato —los nueve temas siguen siendo nueve— sino de la mecánica:
     * `phpunit` no sabe nada de un `.test.js`, y la infraestructura que lo lanza es del proyecto
     * anfitrión, no del paquete.
     */
    public const VITEST_SUFFIX = 'Index.test.js';

    /**
     * El nombre de clase de una pieza: prefijo de contexto + subfuncionalidad + sufijo.
     *
     * `('Central', 'Invoice', 'HttpTest')` → `CentralInvoiceHttpTest`
     *
     * El prefijo va vacío donde no hay eje de contexto, igual que en `SeederNames::piece()`, y por
     * eso se compone aquí y no en cada llamador: en single-app la misma línea tiene que producir
     * `InvoiceHttpTest` sin un condicional en cada sitio.
     */
    public static function piece(string $classPrefix, string $subFeature, string $suffix): string
    {
        return $classPrefix . $subFeature . $suffix;
    }

    /**
     * Las piezas ejecutables del grupo, ya compuestas y en el orden de la cascada.
     *
     * @return array<int, array{clase: string, sufijo: string, cubre: string}>
     */
    public static function cascadeFor(string $classPrefix, string $subFeature): array
    {
        $piezas = [];

        foreach (self::CASCADE as $sufijo => $cubre) {
            $piezas[] = [
                'clase'  => self::piece($classPrefix, $subFeature, $sufijo),
                'sufijo' => $sufijo,
                'cubre'  => $cubre,
            ];
        }

        return $piezas;
    }

    /**
     * Todas las piezas del grupo, ejecutables o no: el manifiesto, la base y la cascada.
     *
     * La usa quien tenga que comprobar que el grupo está **completo** — que es distinto de ejecutarlo.
     * Un grupo al que le falta el manifiesto no es un grupo con una prueba menos: es un grupo que no
     * puede derivar nada.
     *
     * @return array<int, string>
     */
    public static function allPieces(string $classPrefix, string $subFeature): array
    {
        $todas = [
            self::piece($classPrefix, $subFeature, self::CONTRACT),
            self::piece($classPrefix, $subFeature, self::BASE),
        ];

        foreach (array_keys(self::CASCADE) as $sufijo) {
            $todas[] = self::piece($classPrefix, $subFeature, $sufijo);
        }

        return $todas;
    }
}
