@props([
    'icon' => null,
    'href' => '#',
    'current' => false,
])

{{-- Item de la barra superior (design.md → Navbar). Flux pinta sus
     `navbar.item` con indicador subrayado; el diseño pide píldora, así que
     esta parte va con utilidades en vez de con el componente de Flux. --}}
<a
    href="{{ $href }}"
    {{ $attributes->class([
        'flex items-center gap-1.5 rounded-[9px] px-3 py-2 text-sm transition-colors',
        'bg-primary-soft font-semibold text-primary' => $current,
        'font-medium text-ink-2 hover:bg-surface-2' => ! $current,
    ]) }}
>
    @if ($icon)
        <flux:icon :icon="$icon" variant="micro" @class([
            'text-primary' => $current,
            'text-ink-muted' => ! $current,
        ]) />
    @endif

    {{ $slot }}
</a>
