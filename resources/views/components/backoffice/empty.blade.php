@props([
    'icon' => 'inbox',
    'heading',
    'text' => null,
])

{{-- Estado vacío de un listado. Se distingue del estado de error a propósito:
     "no hay nada" no es lo mismo que "no lo hemos podido cargar". --}}
<div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-line-strong bg-surface px-6 py-12 text-center">
    <flux:icon :name="$icon" variant="outline" class="size-8 text-ink-muted" />

    <flux:heading size="lg" class="text-ink">{{ $heading }}</flux:heading>

    @if ($text)
        <flux:text class="max-w-md text-ink-2">{{ $text }}</flux:text>
    @endif

    @isset($actions)
        <div class="mt-1 flex flex-wrap items-center justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
