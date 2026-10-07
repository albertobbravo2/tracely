@props([
    // Solo la parte pública (home y páginas legales) pinta el pie; el panel
    // y el backoffice se quedan sin él.
    'footer' => false,
])

<x-layouts::app.header :title="$title ?? null">
    <flux:main container="true">
        {{ $slot }}
    </flux:main>

    @if ($footer)
        <x-public-footer />
    @endif
</x-layouts::app.header>
