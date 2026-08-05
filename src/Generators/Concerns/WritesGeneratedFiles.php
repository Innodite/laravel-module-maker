<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Concerns;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Support\GeneratedFileCheck;

/**
 * The single door through which a generator writes to the user's project.
 *
 * It is a trait and not just a base-class method because the generators do not share one
 * base class: five of them —exception, notification, console command, job, test support—
 * only pull in HasStubs, and each wrote its file with its own `File::put`. That is how a
 * check placed in `AbstractComponentGenerator::putFile()` would have covered eleven
 * generators and quietly missed five. A net with five holes in it is not a net; it is a
 * false sense of one.
 */
trait WritesGeneratedFiles
{
    /**
     * Comprueba y escribe. En ese orden, y solo entonces anuncia.
     *
     * El éxito se emite **después** de escribir. Anunciarlo antes es A15: el comando decía
     * «✅ DatabaseSeeder modificado» cuando no había tocado nada, y el usuario se quedaba
     * creyendo que estaba configurado.
     *
     * @throws \Innodite\LaravelModuleMaker\Exceptions\GeneratedFileRejectedException
     */
    protected function putFile(string $filePath, string $content, string $message = ''): void
    {
        GeneratedFileCheck::assertWritable($filePath, $content);

        File::put($filePath, $content);

        // Los generadores sueltos no tienen consola: escriben igual, en silencio.
        if ($message !== '' && method_exists($this, 'info')) {
            $this->info("✅ {$message}");
        }
    }
}
