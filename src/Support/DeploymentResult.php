<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Support;

/**
 * El resultado de un despliegue: qué se ejecutó, qué falló y con qué mensaje.
 *
 * **Existe por lo que un código de salida no puede contar.** Mientras el despliegue solo se lanzaba
 * por consola, un entero bastaba: la persona leía la pantalla. En cuanto lo invoca el propio
 * proyecto —al dar de alta un cliente, por ejemplo—, quien llama necesita saber **hasta dónde
 * llegó** para poder deshacerlo; y de un `0` o un `1` eso no se deduce.
 *
 * **Inmutable a propósito.** Cada paso devuelve un resultado nuevo en vez de mutar el anterior, de
 * modo que el que se recibió al empezar no cambie bajo los pies mientras se recorre el resto.
 */
final class DeploymentResult
{
    /**
     * @param  array<int, string>     $applied  Lo que se ejecutó, en orden.
     * @param  array<string, string>  $errors   Clave del paso → mensaje del fallo.
     */
    private function __construct(
        private readonly array $applied = [],
        private readonly array $errors = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /** Un paso que salió bien. La clave identifica el paso: el tenant, el módulo, el contexto. */
    public function withApplied(string $step): self
    {
        return new self([...$this->applied, $step], $this->errors);
    }

    /** Un paso que falló. El mensaje es el del seeder, sin reescribir. */
    public function withFailure(string $step, string $message): self
    {
        return new self($this->applied, [...$this->errors, $step => $message]);
    }

    public function successful(): bool
    {
        return $this->errors === [];
    }

    /** @return array<int, string> */
    public function applied(): array
    {
        return $this->applied;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * El primer mensaje de fallo, para quien solo va a enseñar uno.
     *
     * Es lo que el comando necesita: el seeder ya listó cada fallo con su archivo y su línea, así
     * que repetirlos todos aquí sería enseñar dos veces lo mismo.
     */
    public function firstError(): ?string
    {
        foreach ($this->errors as $message) {
            return $message;
        }

        return null;
    }
}
