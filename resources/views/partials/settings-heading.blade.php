{{-- Cabecera de la sección de ajustes. Mismo patrón que la cabecera de página
     del backoffice (design.md → Página de backoffice): el título no usa
     `<flux:heading>` porque su escala máxima se queda por debajo del diseño. --}}
<div class="mb-8 w-full">
    <h1 class="text-3xl font-bold tracking-[-0.03em] text-ink lg:text-[2.375rem] lg:leading-tight">
        {{ __('Ajustes') }}
    </h1>

    <flux:text class="mt-2 max-w-2xl leading-relaxed text-ink-2">
        {{ __('Gestiona tu perfil y los ajustes de tu cuenta') }}
    </flux:text>
</div>
