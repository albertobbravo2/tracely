@props([
    'heading' => null,
    'subheading' => null,
])

@php
    // Sub-navegación de Ajustes (design.md → Sidebar: el item actual va en
    // `primary-soft` + `primary`). No usa `flux:navlist` porque Flux pinta su
    // item activo como píldora blanca con borde y sombra, que es justo lo que
    // el diseño sustituye.
    $sections = [
        ['route' => 'profile.edit', 'label' => __('Perfil'), 'icon' => 'user'],
        ['route' => 'security.edit', 'label' => __('Seguridad'), 'icon' => 'shield-check'],
        ['route' => 'appearance.edit', 'label' => __('Apariencia'), 'icon' => 'swatch'],
    ];
@endphp

<div class="flex items-start gap-8 max-md:flex-col max-md:gap-6">
    <nav aria-label="{{ __('Ajustes') }}" class="w-full shrink-0 md:w-56">
        <ul class="flex gap-1 max-md:overflow-x-auto md:flex-col">
            @foreach ($sections as $section)
                @php $current = request()->routeIs($section['route']); @endphp

                <li class="shrink-0">
                    <a
                        href="{{ route($section['route']) }}"
                        wire:navigate
                        @class([
                            'flex items-center gap-2.5 rounded-[10px] px-3 py-2.5 text-[0.9rem] transition-colors',
                            'bg-primary-soft font-semibold text-primary' => $current,
                            'font-medium text-ink-2 hover:bg-surface-2 hover:text-ink' => ! $current,
                        ])
                        @if ($current) aria-current="page" @endif
                    >
                        <flux:icon :icon="$section['icon']" variant="mini" @class([
                            'text-primary' => $current,
                            'text-ink-muted' => ! $current,
                        ]) />

                        {{ $section['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="min-w-0 flex-1 space-y-6 self-stretch">
        <section class="rounded-xl border border-line bg-surface p-5 sm:p-6">
            @if ($heading)
                <h2 class="text-xl font-semibold tracking-[-0.02em] text-ink">{{ $heading }}</h2>
            @endif

            @if ($subheading)
                <p class="mt-1 text-sm leading-relaxed text-ink-2">{{ $subheading }}</p>
            @endif

            <div @class(['w-full max-w-lg', 'mt-6' => $heading || $subheading])>
                {{ $slot }}
            </div>
        </section>

        {{-- Bloques que van fuera de la tarjeta principal porque tienen función
             propia (2FA, passkeys, eliminar cuenta). --}}
        {{ $extra ?? '' }}
    </div>
</div>
