<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    {{-- `backoffice` no pinta nada por sí sola: es el ámbito con el que app.css
         lleva las tablas, los diálogos y el sidebar de Flux a los tokens del
         diseño sin tocar el resto de la app. --}}
    <body class="backoffice min-h-screen bg-canvas">
        {{-- Un único sidebar para las dos anchuras: `collapsible="mobile"` lo
             convierte en panel deslizante por debajo de lg, y lo abre el toggle
             de la cabecera. --}}
        <flux:sidebar sticky collapsible="mobile" class="w-63 border-e border-line bg-surface px-4 py-5">
            @php
                // Bajo la marca se pone la empresa de quien mira, porque es el
                // recorte con el que va a ver todos los listados. Solo aplica a
                // agente y administrador: el superadministrador está por encima
                // de las empresas y no tiene una que enseñarle.
                $sidebarCompany = auth()->user()->hasAnyRole(['agente', 'administrador'])
                    ? auth()->user()->company?->name
                    : null;
            @endphp

            <flux:sidebar.header class="!px-1.5">
                <a href="{{ route('backoffice.shipments') }}" class="flex min-w-0 items-center gap-2.5" wire:navigate>
                    <x-brand-mark class="!size-[30px]" />

                    <span class="flex min-w-0 flex-col">
                        <span class="text-lg font-bold leading-tight tracking-[-0.02em] text-ink">Tracely</span>

                        @if ($sidebarCompany)
                            <span class="truncate text-[11.5px] leading-tight text-ink-muted">
                                {{ $sidebarCompany }}
                            </span>
                        @endif
                    </span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="gap-5">
                <flux:sidebar.group :heading="__('General')">
                    <flux:sidebar.item
                        icon="cube"
                        :href="route('backoffice.shipments')"
                        :current="request()->routeIs('backoffice.shipments')"
                        wire:navigate
                    >
                        {{ __('Pedidos') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="clock"
                        :href="route('backoffice.shipment-histories')"
                        :current="request()->routeIs('backoffice.shipment-histories')"
                        wire:navigate
                    >
                        {{ __('Historial') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Gestión')">
                    <flux:sidebar.item
                        icon="document-text"
                        :href="route('backoffice.documents')"
                        :current="request()->routeIs('backoffice.documents')"
                        wire:navigate
                    >
                        {{ __('Documentos') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="users"
                        :href="route('backoffice.users')"
                        :current="request()->routeIs('backoffice.users')"
                        wire:navigate
                    >
                        {{ __('Usuarios') }}
                    </flux:sidebar.item>

                    {{-- Solo el superadministrador gestiona empresas (ver
                         `RolesAndPermissionsSeeder`): para administrador y agente
                         el enlace ni se pinta. --}}
                    @if (auth()->user()->hasRole('superadministrador'))
                        <flux:sidebar.item
                            icon="building-office-2"
                            :href="route('backoffice.companies')"
                            :current="request()->routeIs('backoffice.companies')"
                            wire:navigate
                        >
                            {{ __('Compañías') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            {{-- «Plataforma» va debajo de las páginas y separado: son salidas de
                 la app, no secciones del backoffice (design.md → Sidebar). --}}
            <div class="my-5 h-px bg-line"></div>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Plataforma')">
                    <flux:sidebar.item icon="globe-alt" :href="route('home')" wire:navigate>
                        {{ __('Ir a la web') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="map-pin" :href="route('dashboard')" wire:navigate>
                        {{ __('Mis pedidos') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- Cuenta y tema juntos al pie: los dos son preferencias de quien
                 mira, no secciones del backoffice. --}}
            <div class="hidden items-center gap-2 lg:flex">
                <x-desktop-user-menu class="min-w-0 flex-1" />
                <x-appearance-toggle />
            </div>
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

        <flux:main container="true">
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
