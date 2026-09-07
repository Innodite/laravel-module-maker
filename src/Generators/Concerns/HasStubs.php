<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Concerns;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\StubPlaceholder;
use Innodite\LaravelModuleMaker\Support\StubsDeVendor;

/**
 * Trait HasStubs
 *
 * Resolves stubs from most specific to most generic:
 *   1. {config_path}/stubs/contextual/{ContextFolder}/{stub}  — project override, per context
 *   2. {config_path}/stubs/contextual/{stub}                  — project override, generic
 *   3. vendor/{cualquiera}/stubs/module-maker/contextual/{stub} — lo que aporte otro paquete
 *   4. package/stubs/contextual/{stub}                        — the package, single source of truth
 *
 * ContextFolder is passed as $contextFolder (e.g. "Central", "Tenant/Shared").
 * $isClean and $context are kept for signature compatibility.
 *
 * Placeholders are `{{{ key }}}` — see StubPlaceholder for why triple.
 */
trait HasStubs
{
    /**
     * Obtiene el contenido del stub y reemplaza los marcadores de posición.
     *
     * @param  string       $stubFile      Nombre del archivo stub (ej: 'model.stub')
     * @param  bool         $isClean       Mantenido por compatibilidad
     * @param  array        $placeholders  Mapa de marcadores → valores
     * @param  string|null  $context       Clave de contexto (ej: 'central') — usado internamente
     * @return string
     */
    protected function getStubContent(string $stubFile, bool $isClean, array $placeholders = [], ?string $context = null): string
    {
        $stub = $this->getStub($stubFile, $isClean, $context);
        return $this->replacePlaceholders($stub, $placeholders);
    }

    /**
     * Obtiene el contenido puro del archivo stub.
     *
     * @throws \Exception Si el archivo stub no se encuentra en ninguna ubicación
     */
    protected function getStub(string $stubFile, bool $isClean, ?string $context = null): string
    {
        $stubPath = $this->getStubPath($stubFile, $isClean, $context);

        if (!File::exists($stubPath)) {
            // El nivel 3 es el propio paquete: si aquí no está, no falta publicar nada
            // —publicar stubs dejó de ser parte de instalar—, falta el archivo.
            throw new \Exception(
                "El archivo stub '{$stubFile}' no se encuentra.\n" .
                "Buscado en: {$stubPath}\n" .
                "Ese es un stub del paquete, no del proyecto: reinstala con 'composer reinstall innodite/laravel-module-maker'.\n" .
                "Si querías personalizarlo, cópialo a module-maker-config/stubs/contextual/{$stubFile} y edítalo allí."
            );
        }

        return File::get($stubPath);
    }

    /**
     * El contenido de un stub **opcional**, o null si no lo aporta nadie.
     *
     * La diferencia con {@see self::getStub()} es el último escalón: aquel termina en el paquete y
     * lanza si allí no está, porque sus stubs son obligatorios —un `model.stub` que falta es una
     * instalación rota—. Este recorre los tres primeros escalones y, si ninguno lo trae, devuelve
     * null sin queja.
     *
     * Es lo que permite un **punto de enganche**: un trozo de código que el paquete no escribe
     * porque no sabe escribirlo, que aparece solo si el proyecto o una biblioteca instalada lo
     * aportan, y cuya ausencia no es un fallo sino el caso normal.
     *
     * @param  string       $stubFile      Nombre del archivo stub
     * @param  array        $placeholders  Mapa de marcadores → valores
     * @param  string|null  $context       Clave o carpeta de contexto
     * @return string|null                 Contenido ya resuelto, o null si no existe
     */
    protected function getOptionalStubContent(string $stubFile, array $placeholders = [], ?string $context = null): ?string
    {
        $customBase = config('make-module.stubs.path') . '/contextual';
        $folder     = $this->normalizeContextFolder($context);

        $candidatas = [];

        if ($folder) {
            $candidatas[] = "{$customBase}/{$folder}/{$stubFile}";
        }

        $candidatas[] = "{$customBase}/{$stubFile}";

        $aportada = StubsDeVendor::buscar($stubFile);
        if ($aportada !== null) {
            $candidatas[] = $aportada;
        }

        foreach ($candidatas as $ruta) {
            if (File::exists($ruta)) {
                return $this->replacePlaceholders(File::get($ruta), $placeholders);
            }
        }

        return null;
    }

    /**
     * Resuelve la ruta completa del archivo stub con resolución por carpeta de contexto.
     *
     * Orden de prioridad:
     *   1. custom/{ContextFolder}/{stub}  — project override, per context
     *   2. custom/{stub}                  — project override, generic
     *   3. package/{stub}                 — the package's single source of truth
     *
     * The package no longer ships per-context copies. It used to carry four of them
     * (Central, Shared, TenantShared, TenantName), byte-for-byte identical to the base
     * stub, and they took precedence over it: fixing a base stub without touching its
     * four copies changed nothing, because the stale copy won. The context override
     * still exists — but only where it can legitimately differ, in the project.
     *
     * @param  string       $stubFile       Stub file name
     * @param  bool         $isClean        Kept for signature compatibility
     * @param  string|null  $contextFolder  Context folder (e.g. "Central", "Tenant/Shared")
     * @return string
     */
    protected function getStubPath(string $stubFile, bool $isClean, ?string $contextFolder = null): string
    {
        $customBase  = config('make-module.stubs.path') . '/contextual';
        $packageBase = __DIR__ . '/../../../stubs/contextual';

        $folder = $this->normalizeContextFolder($contextFolder);

        // 1. Project override, per context
        if ($folder) {
            $p = "{$customBase}/{$folder}/{$stubFile}";
            if (File::exists($p)) {
                return $p;
            }
        }

        // 2. Project override, generic
        $p = "{$customBase}/{$stubFile}";
        if (File::exists($p)) {
            return $p;
        }

        // 3. Lo que aporte otro paquete instalado.
        //
        // Va **detrás** del proyecto y **delante** del paquete, y ese orden es el contenido de la
        // decisión: quien aporta sabe más que este generador sobre cómo se escribe una pantalla en
        // su biblioteca, y menos que el proyecto sobre su propio código. Lo de en medio, entonces.
        //
        // Que no haya nadie es el caso normal y no cuesta nada: el descubrimiento se hace una vez
        // por proceso y devuelve una lista vacía.
        $aportado = StubsDeVendor::buscar($stubFile);
        if ($aportado !== null) {
            return $aportado;
        }

        // 4. The package's single source of truth
        return "{$packageBase}/{$stubFile}";
    }

    /**
     * Normaliza la carpeta de contexto para usarla en la ruta de stubs del PROYECTO.
     *
     * Acepta la clave (`central`, `tenant`) o la carpeta ya escrita (`Central`, `Tenant`), y
     * devuelve el nombre de carpeta bajo el que un proyecto puede poner una plantilla distinta para
     * ese contexto: `module-maker-config/stubs/contextual/Central/controller.stub`.
     *
     * ⛔ El paquete NO trae carpetas por contexto —solo `contextual/`—, y esto es solo el nivel 1 de
     * la resolución. Traía cuatro (`Central`, `Shared`, `TenantShared`, `TenantName`) idénticas byte
     * a byte a la plantilla base y que le ganaban en prioridad: corregir la base sin tocar sus cuatro
     * copias no cambiaba nada.
     *
     * Un contexto que el proyecto declare por su cuenta cae en `null`, y eso es correcto: usará la
     * plantilla genérica, que es la que hay.
     *
     * @param  string|null  $context  Clave de contexto o carpeta de contexto
     * @return string|null  Nombre de carpeta de stubs (ej: "Central", "Tenant")
     */
    private function normalizeContextFolder(?string $context): ?string
    {
        if (!$context) {
            return null;
        }

        return match ($context) {
            'central', 'Central' => 'Central',
            'tenant',  'Tenant'  => 'Tenant',
            default              => null,
        };
    }

    /**
     * Resuelve los placeholders {{{ key }}} del contenido del stub.
     *
     * Las claves llegan **desnudas** —`['modelName' => 'Role']`— y quien las envuelve es
     * StubPlaceholder::wrap(), una sola vez. Una clave que llegue ya envuelta lanza, en vez
     * de no sustituir nada en silencio: así fue B15, y costó cuatro vistas rotas por módulo.
     *
     * El valor se convierte a texto aquí. No es ceremonia: hay generadores que entregan números
     * —un `0` de longitud, un contador— y hasta que este archivo declaró `strict_types` PHP los
     * convertía por su cuenta, en silencio. Con la declaración puesta, `str_replace` rechaza el
     * entero y **la generación entera se cae**; convertir aquí, en el único punto por el que pasan
     * todos los placeholders, mantiene el contrato («el stub recibe texto») en un solo sitio.
     *
     * @param  string                       $stub          Contenido del stub
     * @param  array<string, string|int|float|bool|null> $placeholders  Mapa clave desnuda → valor
     * @return string
     */
    protected function replacePlaceholders(string $stub, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $stub = str_replace(StubPlaceholder::wrap((string) $key), (string) $value, $stub);
        }

        return $stub;
    }
}
