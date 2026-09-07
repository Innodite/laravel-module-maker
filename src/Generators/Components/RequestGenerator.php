<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Innodite\LaravelModuleMaker\Support\RequestNames;

/**
 * Escribe los **dos FormRequests** de una subfuncionalidad: el del alta y el de la edición.
 *
 * **Antes había tres caminos y producían tres cosas distintas.** Según el contexto salían un Store y
 * un Update, o un único `…Request` genérico, o un `…Request` en la raíz del módulo. Tres formas para
 * la misma pieza, y ninguna manera de saber cuál te iba a tocar sin leer este archivo.
 *
 * El precio lo pagaba el resto del paquete: el manifiesto del contrato declara `…StoreRequest`, y en
 * el modo que escribía el genérico esa clase **no se generaba nunca** — así que la prueba del
 * andamiaje fallaba pidiendo una pieza que nadie había escrito. Dos lados correctos por separado
 * apuntando a sitios distintos, que es el defecto que este paquete lleva cuatro fases retirando.
 *
 * Ahora hay un solo camino. Toda subfuncionalidad nace con los dos, en los tres modos, y el nombre
 * lo decide `RequestNames` — el mismo sitio del que lo lee el controlador que los recibe.
 */
class RequestGenerator extends AbstractComponentGenerator
{
    /**
     * @param  string  $moduleName       Nombre del módulo
     * @param  string  $modulePath       Ruta absoluta al directorio del módulo
     * @param  bool    $isClean          true = stubs clean, false = stubs dynamic
     * @param  array   $componentConfig  Configuración del componente
     */
    public function __construct(
        string $moduleName,
        string $modulePath,
        bool $isClean,
        array $componentConfig = []
    ) {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
    }

    /**
     * Genera el FormRequest del alta y el de la edición.
     *
     * @return void
     */
    public function generate(): void
    {
        $requestDir = $this->buildPath('Http/Requests');
        $namespace  = $this->buildNamespace('Http\\Requests');

        $this->ensureDirectoryExists($requestDir);

        // Recorrer la lista, en vez de escribir dos bloques, es lo que impide que uno de los dos se
        // quede sin generar el día que alguien toque este método.
        foreach (RequestNames::all($this->getClassPrefix(), $this->subFeatureName()) as $accion => $className) {
            $stub = $this->getStubContent('request.stub', $this->isClean, [
                'namespace'   => $namespace,
                'requestName' => $className,
                'accion'      => $accion,
            ]);

            $this->putFile(
                "{$requestDir}/{$className}.php",
                $stub,
                'Request creado: ' . $this->rutaVisible("{$requestDir}/{$className}.php")
            );
        }
    }
}
