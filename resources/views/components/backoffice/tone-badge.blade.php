@props([
    'tone' => 'idle',
])

@php
    // Un único sitio donde vive la correspondencia tono → tokens del diseño
    // (design.md → Color). El par es siempre texto-sobre-fondo: `x` sobre
    // `x-soft`, sin cruzarlos.
    //
    // Los nombres viejos (`alerta`, `azul`, `gris`) siguen aceptándose como
    // alias porque los devuelven los `statusTone()` de las pantallas de
    // documentos, que son lógica Livewire y no se tocan desde aquí.
    //
    // Van con `!` porque el badge de Flux trae su propio fondo y su propio
    // texto: sin la marca de importancia ganaría él.
    $classes = match ($tone) {
        'ok' => '!bg-ok-soft !text-ok',
        'info', 'azul' => '!bg-info-soft !text-info',
        'aduana' => '!bg-aduana-soft !text-aduana',
        'warn' => '!bg-warn-soft !text-warn',
        'danger', 'alerta' => '!bg-danger-soft !text-danger',
        default => '!bg-idle-soft !text-idle',
    };
@endphp

<flux:badge rounded size="sm" {{ $attributes->class([$classes, '!font-semibold']) }}>
    {{ $slot }}
</flux:badge>
