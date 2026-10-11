@props([
    'hideNameOnMobile' => false,
])

{{-- `$attributes` va al desplegable para que quien lo coloque pueda darle
     ancho o esconderlo por breakpoint; sin esto la clase se perdía.
     `hideNameOnMobile` esconde el nombre por debajo de `lg` y deja el avatar y
     el chevron: el componente de Flux no tiene opción para eso, así que se
     oculta su `span` del nombre con un selector. --}}
<flux:dropdown position="bottom" align="start" {{ $attributes }}>
    
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        :class="$hideNameOnMobile ? 'max-lg:[&>span]:hidden' : ''"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />
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
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
