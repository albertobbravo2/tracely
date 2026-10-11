@props([
    'heading',
    'subheading' => null,
])

{{-- Cabecera de página del backoffice (design.md → Página de backoffice):
     título grande a la izquierda y botones de acción a la derecha, en columna
     hasta lg para que en móvil el botón principal no se estreche.

     El título no usa `<flux:heading>`: su escala máxima se queda muy por debajo
     de los 38 px del diseño, así que va como `<h1>` con los tokens del tema. --}}
<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <h1 class="text-3xl font-bold tracking-[-0.03em] text-ink lg:text-[2.375rem] lg:leading-tight">
            {{ $heading }}
        </h1>

        @if ($subheading)
            <flux:text class="mt-2 max-w-2xl leading-relaxed text-ink-2">{{ $subheading }}</flux:text>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2 lg:pt-2">
            {{ $actions }}
        </div>
    @endisset
</div>
