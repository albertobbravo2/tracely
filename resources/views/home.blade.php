<x-layouts::app :title="__('Home')">
    <div class="mx-auto flex max-w-md flex-col items-center gap-6 py-12 text-center">
        <flux:heading size="xl">Tracely</flux:heading>

        <flux:input placeholder="Buscar..." class="w-full">
            <x-slot name="iconTrailing">
                <flux:button size="sm" variant="subtle" icon="magnifying-glass" class="-mr-1" />
            </x-slot>
        </flux:input>
    </div>
</x-layouts::app>
