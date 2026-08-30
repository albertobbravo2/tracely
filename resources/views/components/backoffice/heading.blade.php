@props([
    'heading',
    'subheading' => null,
])

{{-- Cabecera de sección: título a la izquierda y acciones a la derecha, en
     columna hasta lg para que en móvil el botón principal no se estreche. --}}
<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <flux:heading size="xl" class="text-gris-900 dark:text-blanco">{{ $heading }}</flux:heading>

        @if ($subheading)
            <flux:text class="mt-1 text-gris-600 dark:text-azul-100">{{ $subheading }}</flux:text>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
