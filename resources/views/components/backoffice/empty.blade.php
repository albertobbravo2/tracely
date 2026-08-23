@props([
    'icon' => 'inbox',
    'heading',
    'text' => null,
])

{{-- Estado vacío de un listado. Se distingue del estado de error a propósito:
     "no hay nada" no es lo mismo que "no lo hemos podido cargar". --}}
<div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-gris-200 bg-blanco px-6 py-12 text-center dark:border-azul-800 dark:bg-azul-900">
    <flux:icon :name="$icon" variant="outline" class="size-8 text-gris-400 dark:text-azul-200" />

    <flux:heading size="lg" class="text-gris-900 dark:text-blanco">{{ $heading }}</flux:heading>

    @if ($text)
        <flux:text class="max-w-md text-gris-600 dark:text-azul-100">{{ $text }}</flux:text>
    @endif

    @isset($actions)
        <div class="mt-1 flex flex-wrap items-center justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
