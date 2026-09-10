{{-- Conmutador de tema del navbar (design.md → Navbar). `$flux.appearance` es
     la API que Flux ya usa en ajustes → apariencia, así que el botón y la
     pantalla de ajustes comparten estado. --}}
<button
    type="button"
    x-data
    x-on:click="$flux.appearance = $flux.appearance === 'dark' ? 'light' : 'dark'"
    {{ $attributes->class('flex size-[34px] shrink-0 items-center justify-center rounded-[10px] bg-surface-2 text-ink-2 transition-colors hover:text-ink') }}
    :aria-label="$flux.appearance === 'dark' ? '{{ __('Cambiar a modo claro') }}' : '{{ __('Cambiar a modo oscuro') }}'"
>
    <flux:icon icon="sun" variant="mini" class="hidden dark:block" />
    <flux:icon icon="moon" variant="mini" class="dark:hidden" />
</button>
