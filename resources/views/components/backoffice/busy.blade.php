@props([
    'target',
])

{{-- Región de resultados de un listado.

     Buscar, filtrar o cambiar de página no es gratis: sale una petición HTTP a
     nuestra propia API, con su token acuñado y su ida y vuelta. Sin esto la
     tabla se quedaba exactamente igual mientras tanto, así que teclear en el
     buscador no daba ninguna señal de que estuviera pasando algo.

     `target` es la lista de propiedades y métodos que cuentan como "recargando"
     — el resto de acciones de la pantalla (abrir el formulario, guardar) tienen
     su propio indicador y no deben atenuar el listado. --}}
<div class="relative" wire:target="{{ $target }}" wire:loading.attr="aria-busy">
    <div
        class="transition-opacity duration-150"
        wire:target="{{ $target }}"
        wire:loading.class="pointer-events-none opacity-40"
    >
        {{ $slot }}
    </div>

    {{-- Fuera del bloque que se atenúa: dentro heredaría la opacidad y el
         indicador sería lo menos visible de la pantalla. --}}
    <div
        class="absolute inset-x-0 top-0 hidden justify-center pt-16"
        wire:target="{{ $target }}"
        wire:loading.flex
    >
        <flux:icon name="loading" class="size-6 text-azul-600 dark:text-azul-200" />

        <flux:text class="sr-only">{{ __('Cargando resultados...') }}</flux:text>
    </div>
</div>
