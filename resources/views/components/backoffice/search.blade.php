@props([
    // Propiedad Livewire a la que se enlaza. Al ser `.live`, el propio campo de
    // Flux pinta su indicador de carga mientras esa propiedad viaja, así que no
    // necesita ninguno añadido.
    'model',
    'label',
    'placeholder',
])

{{-- El buscador de las cinco pantallas: mismo icono, misma anchura y la
     etiqueta siempre presente aunque no se vea, que es lo que lo hace
     utilizable con un lector de pantalla. Estaba copiado cinco veces. --}}
<flux:input
    wire:model.live.debounce.400ms="{{ $model }}"
    icon="magnifying-glass"
    class="sm:max-w-sm"
    :placeholder="$placeholder"
    :label="$label"
    label:class="sr-only"
/>
