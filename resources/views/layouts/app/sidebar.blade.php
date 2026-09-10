<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas">
        <flux:sidebar sticky collapsible="mobile" class="w-63 border-e border-line bg-surface px-4 py-5">
            <flux:sidebar.header class="!px-1.5">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5" wire:navigate>
                    <x-brand-mark class="!size-[30px]" />
                    <span class="text-lg font-bold tracking-[-0.02em] text-ink">Tracely</span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="gap-5">
                <flux:sidebar.group :heading="__('General')">
                    {{-- Fuera del backoffice no aplica el ámbito `.backoffice` de
                         app.css, así que el estado activo (design.md → Sidebar:
                         `primary-soft` + `primary`) se pide aquí por item. --}}
                    <flux:sidebar.item
                        icon="squares-2x2"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        class="rounded-[10px] data-current:border-transparent data-current:bg-primary-soft data-current:text-primary data-current:shadow-none"
                        wire:navigate
                    >
                        {{ __('Panel') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            {{-- «Plataforma» son salidas de la app, no secciones: van debajo y
                 separadas (design.md → Sidebar). --}}
            <div class="my-5 h-px bg-line"></div>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Plataforma')">
                    <flux:sidebar.item
                        icon="globe-alt"
                        :href="route('home')"
                        class="rounded-[10px] data-current:border-transparent data-current:bg-primary-soft data-current:text-primary data-current:shadow-none"
                        wire:navigate
                    >
                        {{ __('Ir a la web') }}
                    </flux:sidebar.item>

                    {{-- Los mismos roles que protegen el bloque `backoffice.` en routes/web.php. --}}
                    @if (auth()->user()->hasAnyRole(['agente', 'administrador', 'superadministrador']))
                        <flux:sidebar.item
                            icon="briefcase"
                            :href="route('backoffice.index')"
                            class="rounded-[10px] data-current:border-transparent data-current:bg-primary-soft data-current:text-primary data-current:shadow-none"
                            wire:navigate
                        >
                            {{ __('Backoffice') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" />
        </flux:sidebar>

        {{-- Cabecera solo de móvil: abre el sidebar y deja a mano la cuenta. --}}
        <flux:header class="lg:hidden border-b border-line bg-surface">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <x-appearance-toggle class="me-2" />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Ajustes') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Cerrar sesión') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
