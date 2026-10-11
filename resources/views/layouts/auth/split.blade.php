<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas antialiased">
        <div class="relative grid min-h-dvh lg:grid-cols-[42fr_58fr]">
            {{-- Panel de marca. Solo desde lg: en móvil la tarjeta ocupa todo el
                 ancho y la marca se repite arriba. --}}
            <div class="relative hidden flex-col justify-between bg-linear-to-br from-primary to-primary-hover p-12 lg:flex">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" wire:navigate>
                    <x-brand-mark inverse />
                    <span class="text-lg font-bold text-on-primary">Tracely</span>
                </a>

                <div class="flex flex-col gap-4">
                    <p class="text-[2.75rem] font-bold leading-[1.12] tracking-[-0.04em] text-balance text-on-primary">
                        {{ __('Cada envío,') }}<br>{{ __('en un solo lugar.') }}
                    </p>

                    <p class="max-w-[26rem] text-base leading-relaxed text-on-primary/85">
                        {{ __('Alta, seguimiento y documentación de pedidos para agentes, administradores y clientes.') }}
                    </p>
                </div>

                <p class="text-[0.8rem] text-on-primary/70">
                    {{ __('© :year Tracely · Rastreo de envíos', ['year' => now()->year]) }}
                </p>
            </div>

            <div class="flex items-center justify-center px-6 py-12 sm:px-12">
                <div class="w-full max-w-[26.25rem]">
                    <a href="{{ route('home') }}" class="mb-8 flex items-center justify-center gap-2.5 lg:hidden" wire:navigate>
                        <x-brand-mark />
                        <span class="text-lg font-bold text-ink">Tracely</span>
                    </a>

                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
