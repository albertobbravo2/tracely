<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas">
        <flux:header container class="!h-auto border-b border-line bg-surface py-3.5">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <a href="{{ route('home') }}" class="flex items-center gap-2.5" wire:navigate>
                <x-brand-mark class="!size-[30px]" />
                <span class="text-[17px] font-bold tracking-[-0.02em] text-ink">Tracely</span>
            </a>

            <flux:spacer />

            <div class="flex items-center gap-1.5">
                @auth
                    <x-nav-pill
                        icon="squares-2x2"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        class="max-lg:hidden"
                        wire:navigate
                    >
                        {{ __('Panel') }}
                    </x-nav-pill>

                    {{-- Mismas condiciones que protegen el bloque `backoffice.` en routes/web.php. --}}
                    @if (auth()->user()->canAccessBackoffice())
                        <x-nav-pill
                            icon="briefcase"
                            :href="route('backoffice.index')"
                            :current="request()->routeIs('backoffice.*')"
                            class="max-lg:hidden"
                            wire:navigate
                        >
                            {{ __('Backoffice') }}
                        </x-nav-pill>
                    @endif
                @endauth

                <x-appearance-toggle class="ms-1.5" />

                @auth
                    {{-- En móvil el navbar no tiene sitio para el nombre: queda solo el avatar. --}}
                    <x-desktop-user-menu class="ms-1.5" hide-name-on-mobile />
                @else
                    {{-- Registro en `ghost` y acceso en `primary`: son dos
                         acciones seguidas y solo una puede llevar el peso. --}}
                    <flux:button
                        :href="route('register')"
                        variant="ghost"
                        size="sm"
                        class="ms-1.5 !text-ink-2"
                        wire:navigate
                    >
                        {{ __('Registrarse') }}
                    </flux:button>

                    <flux:button
                        :href="route('login')"
                        variant="primary"
                        size="sm"
                        wire:navigate
                    >
                        {{ __('Iniciar sesión') }}
                    </flux:button>
                @endauth
            </div>
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-line bg-surface">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            @auth
                <flux:sidebar.nav>
                    <flux:sidebar.group :heading="__('Plataforma')">
                        <flux:sidebar.item icon="squares-2x2" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Panel') }}
                        </flux:sidebar.item>

                        {{-- La misma condición que la píldora del navbar, que en móvil está oculta. --}}
                        @if (auth()->user()->canAccessBackoffice())
                            <flux:sidebar.item icon="briefcase" :href="route('backoffice.index')" :current="request()->routeIs('backoffice.*')" wire:navigate>
                                {{ __('Backoffice') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                </flux:sidebar.nav>
            @endauth

            <flux:spacer />
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
