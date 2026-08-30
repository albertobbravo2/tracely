@props([
    'tone' => 'gris',
])

@php
    // Las cuatro tonalidades de la paleta de marca que usa el backoffice, en un
    // único sitio: `ok` para lo confirmado, `alerta` para lo que va mal, `azul`
    // para lo que está en curso y `gris` para lo neutro o lo que no
    // reconocemos. Antes cada pantalla repetía estas parejas de clases a mano,
    // y bastaba tocar una para que dejaran de coincidir entre sí.
    //
    // Van con `!` porque el badge de Flux trae su propio fondo y su propio
    // texto: sin la marca de importancia ganaría él.
    $classes = match ($tone) {
        'ok' => '!bg-ok-fondo !text-ok-fuerte',
        'alerta' => '!bg-alerta-fondo !text-alerta-fuerte',
        'azul' => '!bg-azul-050 !text-azul-800',
        default => '!bg-gris-050 !text-gris-600',
    };
@endphp

<flux:badge rounded size="sm" {{ $attributes->class($classes) }}>
    {{ $slot }}
</flux:badge>
