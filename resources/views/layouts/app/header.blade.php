<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gris-050 dark:bg-zinc-800">
        <flux:header container class="border-b border-azul-200 bg-azul-050 dark:border-azul-900 dark:bg-azul-800">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('home') }}" wire:navigate />

                <flux:spacer />

                <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                    {{--     BUSCAR ( de momento no necesario en header)
                    <flux:tooltip :content="__('Search')" position="bottom">
                        <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                        </flux:tooltip>
                        --}}

                @auth

                <flux:navbar.item
                    icon="layout-grid"
                    class="h-10 max-lg:hidden [&>div>svg]:size-5"
                    :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>

                @endauth

                <flux:tooltip :content="__('Repository')" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="folder-git-2"
                        href="https://github.com/laravel/livewire-starter-kit"
                        target="_blank"
                        :label="__('Repository')"
                    />
                </flux:tooltip>
                <flux:tooltip :content="__('Documentation')" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="book-open-text"
                        href="https://laravel.com/docs/starter-kits#livewire"
                        target="_blank"
                        :label="__('Documentation')"
                    />
                </flux:tooltip>
            </flux:navbar>

            @auth
                <x-desktop-user-menu />
            @else
                <flux:navbar class="space-x-0.5">
                    <flux:navbar.item :href="route('login')" wire:navigate>
                        {{ __('Log in') }}
                    </flux:navbar.item>
                    <flux:navbar.item :href="route('register')" wire:navigate>
                        {{ __('Register') }}
                    </flux:navbar.item>
                </flux:navbar>
            @endauth
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-azul-200 bg-azul-050 dark:border-azul-900 dark:bg-azul-800">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            @auth
                <flux:sidebar.nav>
                    <flux:sidebar.group :heading="__('Platform')">
                        <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard')  }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                </flux:sidebar.nav>
            @endauth

            <flux:spacer />

            <flux:sidebar.nav>

                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
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
