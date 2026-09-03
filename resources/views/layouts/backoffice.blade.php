<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    {{-- `backoffice` no pinta nada por sí sola: es el ámbito con el que app.css
         lleva las tablas y los diálogos de Flux a la paleta de marca sin tocar
         el resto de la app. --}}
    <body class="backoffice min-h-screen bg-gris-050 dark:bg-[#10262B]">
        {{-- Un único sidebar para las dos anchuras: `collapsible="mobile"` lo
             convierte en panel deslizante por debajo de lg, y lo abre el toggle
             de la cabecera. Mismo patrón que layouts/app/sidebar.blade.php, con
             la paleta de marca que ya usa el resto del producto. --}}
        <flux:sidebar sticky collapsible="mobile" class="border-e border-azul-200 bg-azul-100 dark:border-azul-900 dark:bg-azul-800">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('backoffice.shipments') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Backoffice')" class="grid">
                    <flux:sidebar.item
                        icon="truck"
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
                        {{ __('Historial de pedidos') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="users"
                        :href="route('backoffice.users')"
                        :current="request()->routeIs('backoffice.users')"
                        wire:navigate
                    >
                        {{ __('Usuarios') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item
                        icon="document-text"
                        :href="route('backoffice.documents')"
                        :current="request()->routeIs('backoffice.documents')"
                        wire:navigate
                    >
                        {{ __('Documentos') }}
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

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('home')" wire:navigate>
                    {{ __('Ir a la web') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" wire:navigate>
                    {{ __('Mis pedidos') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" />
        </flux:sidebar>

        {{-- Cabecera solo de móvil: abre el sidebar y deja a mano la cuenta. --}}
        <flux:header class="lg:hidden border-b border-azul-200 bg-azul-100 dark:border-azul-900 dark:bg-azul-800">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

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
