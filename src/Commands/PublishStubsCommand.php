<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Commands\Concerns\PrintsHeader;
use Innodite\LaravelModuleMaker\Commands\Concerns\ReportsFailures;
use Innodite\LaravelModuleMaker\Support\Disk;

/**
 * Exporta las plantillas del paquete al proyecto, para quien quiera personalizar lo que se genera.
 *
 * **Por qué es un comando aparte y no parte de instalar.** Lo era, y las publicaba todas de golpe. El
 * problema no es el número: es que **una plantilla copiada deja de actualizarse**. Quien instalara
 * una versión nueva del paquete seguiría generando con las plantillas del día que instaló, sin un
 * solo aviso — el módulo sale completo, compila, y está escrito con la forma vieja.
 *
 * Personalizar un stub es una decisión deliberada y con un coste que hay que conocer. Por eso se
 * pide a propósito, se puede pedir **solo lo que se va a tocar**, y el comando dice el coste antes
 * de copiar nada.
 */
class PublishStubsCommand extends Command
{
    use PrintsHeader;
    use ReportsFailures;

    protected $signature = 'innodite:publish-stubs
        {stub?* : Plantillas concretas (ej: vue-index controller). Sin ninguna, las lista todas}
        {--all : Exporta todas de golpe — solo si de verdad las vas a personalizar todas}
        {--force : Sobreescribe las que ya estén exportadas}';

    protected $description = 'Exporta plantillas al proyecto para personalizar lo que se genera.';

    public function handle(): int
    {
        $this->cabecera('Plantillas del generador');

        $origen  = dirname(__DIR__, 2) . '/stubs/contextual';
        $destino = config('make-module.stubs.path') . '/contextual';

        if (! File::isDirectory($origen)) {
            $this->fallo(
                'el paquete no trae la carpeta de plantillas.',
                'reinstala con <comment>composer reinstall innodite/laravel-module-maker</comment>.',
                "Buscada en: {$origen}"
            );

            return self::FAILURE;
        }

        $disponibles = collect(File::files($origen))
            ->map(fn ($f) => $f->getFilename())
            ->filter(fn ($n) => str_ends_with($n, '.stub'))
            ->values();

        $pedidas = (array) $this->argument('stub');

        // Sin argumentos y sin --all no se copia nada: se enseña qué hay y cuánto cuesta.
        if ($pedidas === [] && ! $this->option('all')) {
            return $this->soloListar($disponibles, $destino);
        }

        $aCopiar = $this->option('all')
            ? $disponibles->all()
            : $this->resolverPedidas($pedidas, $disponibles);

        if ($aCopiar === null) {
            return self::FAILURE;
        }

        return $this->copiar($aCopiar, $origen, $destino);
    }

    /** Enseña el catálogo y el coste, sin escribir nada. */
    private function soloListar($disponibles, string $destino): int
    {
        $this->components->info("El paquete trae {$disponibles->count()} plantillas.");
        $this->newLine();

        foreach ($disponibles->chunk(3) as $fila) {
            $this->line('  ' . collect($fila)
                ->map(fn ($n) => str_pad(str_replace('.stub', '', $n), 26))
                ->implode(''));
        }

        $this->newLine();
        $this->line('  <fg=yellow>⚠️ Una plantilla exportada deja de actualizarse con el paquete.</>');
        $this->line('  <fg=gray>Al instalar una versión nueva seguirás generando con la que copiaste,');
        $this->line('  y el módulo saldrá completo y con la forma vieja. Exporta solo lo que vayas a tocar.</>');
        $this->newLine();
        $this->line('  <fg=green>Exportar una o varias:</> <comment>php artisan innodite:publish-stubs vue-index controller</comment>');
        $this->line('  <fg=green>Exportarlas todas:</>     <comment>php artisan innodite:publish-stubs --all</comment>');
        $this->newLine();
        $this->line("  <fg=gray>Destino: {$destino}</>");

        return self::SUCCESS;
    }

    /**
     * Resuelve los nombres pedidos contra el catálogo, tolerando que se escriban sin `.stub`.
     *
     * Un nombre que no existe **detiene el comando** en vez de copiarse a medias: exportar tres de
     * cuatro y salir en verde deja al proyecto generando con una plantilla que el usuario cree haber
     * personalizado.
     *
     * @return array<int, string>|null
     */
    private function resolverPedidas(array $pedidas, $disponibles): ?array
    {
        $resueltas = [];
        $faltan    = [];

        foreach ($pedidas as $pedida) {
            $nombre = str_ends_with($pedida, '.stub') ? $pedida : $pedida . '.stub';

            $disponibles->contains($nombre) ? $resueltas[] = $nombre : $faltan[] = $pedida;
        }

        if ($faltan !== []) {
            $this->fallo(
                'estas plantillas no existen: ' . implode(', ', $faltan) . '.',
                'lánzalo sin argumentos para ver el catálogo completo.',
                'No se exportó ninguna: media exportación deja el proyecto generando con una '
                . 'plantilla que crees haber personalizado.'
            );

            return null;
        }

        return $resueltas;
    }

    /** @param array<int, string> $nombres */
    private function copiar(array $nombres, string $origen, string $destino): int
    {
        Disk::makeDirectory($destino, 0755, true, true);

        $copiadas = $omitidas = 0;

        foreach ($nombres as $nombre) {
            $ruta = "{$destino}/{$nombre}";

            if (File::exists($ruta) && ! $this->option('force')) {
                $this->components->twoColumnDetail($nombre, '<fg=gray>ya estaba — se conserva</>');
                $omitidas++;
                continue;
            }

            Disk::copy("{$origen}/{$nombre}", $ruta);
            $this->components->twoColumnDetail($nombre, '<fg=green>exportada</>');
            $copiadas++;
        }

        $this->newLine();
        $this->components->info("{$copiadas} exportada(s)" . ($omitidas ? ", {$omitidas} conservada(s)" : '') . '.');

        if ($copiadas > 0) {
            $this->line('  <fg=yellow>⚠️ A partir de ahora estas plantillas son tuyas:</> el paquete ya no las actualiza.');
            $this->line('  <fg=gray>Si dejas de personalizar alguna, bórrala y volverá a leerse la del paquete.</>');
        }

        if ($omitidas > 0) {
            $this->line('  <fg=gray>Para sobreescribir las que ya estaban: --force</>');
        }

        return self::SUCCESS;
    }
}
