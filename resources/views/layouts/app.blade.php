<x-layouts::app.header :title="$title ?? null">
    <flux:main container="true">
        {{ $slot }}
    </flux:main>
</x-layouts::app.header>
