<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * `innodite:migrate-plan` está retirado, y lo dice bien.
 *
 * **Qué se prueba aquí y por qué no es ceremonia.** Un comando retirado tiene dos maneras de fallar,
 * y las dos son silenciosas: contestar «no está definido» —que no dice a dónde ir— o contestar en
 * verde, que en un script de despliegue pasa por trabajo hecho. Este grupo fija las dos.
 *
 * El comando aplicaba las migraciones recorriendo el árbol y ordenándolas por el nombre de las
 * carpetas, ignorando `deploy`. Se retiró en vez de corregirse: era el único sitio que ejecutaba
 * migraciones fuera del seeder —contra R22— y `innodite:deploy` ya hace lo mismo, en el orden que
 * el proyecto declara.
 *
 * **Se lee la salida con `Artisan::output()` y no con `$this->artisan()`**, a propósito: el
 * ayudante de consola envuelve el párrafo por el ancho del terminal, y ahí `innodite:deploy` se
 * parte a mitad de palabra. La prueba se caía por dónde cortaba la línea, no por lo que decía el
 * mensaje.
 */

/** Ejecuta el comando retirado y devuelve [código, salida]. */
function retirado(array $opciones = []): array
{
    $codigo = Artisan::call('innodite:migrate-plan', $opciones + ['--no-interaction' => true]);

    return [$codigo, Artisan::output()];
}

it('dice que se retiró y a qué comando ir', function () {
    [, $salida] = retirado(['--context' => 'central']);

    expect($salida)->toContain('se retiró');
    expect($salida)->toContain('innodite:deploy');
});

it('da la orden entera, lista para copiar', function () {
    // Un FIX que nombra el comando pero no lo escribe obliga a ir a buscar la firma a otro sitio.
    [, $salida] = retirado();

    expect($salida)->toContain('php artisan innodite:deploy stage --context=central');
});

it('falla, no termina en verde: no hizo lo que se le pidió', function () {
    // Es el mismo defecto que este issue vino a corregir en otro sitio —no aplicar nada y devolver
    // éxito—, así que anunciar la retirada así sería repetirlo en la despedida.
    [$codigo] = retirado(['--context' => 'central']);

    expect($codigo)->not->toBe(0);
});

it('sigue contestando cuando le pasan las opciones de antes', function () {
    // No las declara —no ensaya nada—, pero las tolera: quien lo tenga escrito en un script tiene
    // que leer que el comando se retiró, no un error sobre la firma.
    [$codigo, $salida] = retirado(['--context' => 'central', '--dry-run' => true]);

    expect($salida)->toContain('se retiró');
    expect($codigo)->not->toBe(0);
});

it('nombra la alternativa para una migración suelta', function () {
    // Quien usaba el plan para aplicar una sola cosa necesita saber que eso sigue existiendo.
    [, $salida] = retirado();

    expect($salida)->toContain('innodite:migrate-one');
});

it('dice por qué se retiró, no solo que se retiró', function () {
    // Sin el motivo, la retirada parece un capricho y el siguiente proyecto vuelve a pedir el
    // comando. El motivo es el orden: lo declara `deploy`, y este lo decidía por su cuenta.
    [, $salida] = retirado();

    expect($salida)->toContain('deploy');
    expect($salida)->toContain('carpetas');
});
