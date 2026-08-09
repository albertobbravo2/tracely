<x-layouts::app :title="__('Home')">
    <div class="px-6 py-16 text-center">
        <flux:heading size="xl" class="text-brand-navy dark:text-blanco !text-3xl">
            {{ __('Rastrea tu paquete') }}
        </flux:heading>

        <flux:text class="mt-2 text-azul-600 dark:text-azul-100">
            {{ __('Ingresa tu número de guía para ver el estado en tiempo real') }}
        </flux:text>

        <div class="mt-6 pb-10">
            <livewire:searchfield />
        </div>
    </div>
</x-layouts::app>
