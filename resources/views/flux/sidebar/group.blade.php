@props([
    'heading' => null,
])

{{-- Sobrescritura del grupo de sidebar de Flux. El componente original pinta
     el encabezado como un disclosure con chevron y sangra los items; el diseño
     (design.md → Sidebar) pide una etiqueta de sección en versalitas y los
     items alineados con ella, sin plegado. --}}
<div {{ $attributes->class('flex flex-col') }} data-flux-sidebar-group>
    @if ($heading)
        <div class="px-3 pb-1.5 pt-1">
            <div class="text-[11px] font-semibold uppercase leading-none tracking-[0.09em] text-ink-muted">
                {{ $heading }}
            </div>
        </div>
    @endif

    <div class="flex flex-col">
        {{ $slot }}
    </div>
</div>
