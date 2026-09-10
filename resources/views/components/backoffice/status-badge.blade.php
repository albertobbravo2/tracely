@use('App\Enums\ShipmentStatus')

@props([
    'status',
])

@php
    // Mapeo cerrado de `ShipmentStatus` a los tokens del diseño (design.md →
    // Estados de envío). Este es el ÚNICO sitio donde vive: las tablas y las
    // tarjetas del backoffice lo consumen a través de este componente.
    //
    // Ojo: `en_transito` y `en_aduana` son dos colores distintos. La paleta
    // vieja los pintaba igual (los dos en azul); el diseño los separa.
    $case = ShipmentStatus::tryFrom((string) $status);

    $tone = match ($case) {
        ShipmentStatus::Entregado => 'ok',
        ShipmentStatus::EnTransito => 'info',
        ShipmentStatus::EnAduana => 'aduana',
        ShipmentStatus::Incidencia => 'danger',
        default => 'idle',
    };
@endphp

<x-backoffice.tone-badge :tone="$tone" {{ $attributes }}>
    {{ $case?->label() ?? __('Estado desconocido') }}
</x-backoffice.tone-badge>
