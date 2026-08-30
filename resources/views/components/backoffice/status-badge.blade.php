@use('App\Enums\ShipmentStatus')

@props([
    'status',
])

@php
    // Misma correspondencia estado → paleta que ⚡searchfield y ⚡my-shipments:
    // `ok` entregado, `alerta` incidencia, `azul` en tránsito/aduana, `gris`
    // pendiente o cualquier estado que no reconozcamos.
    $case = ShipmentStatus::tryFrom((string) $status);

    $tone = match ($case) {
        ShipmentStatus::Entregado => 'ok',
        ShipmentStatus::EnTransito, ShipmentStatus::EnAduana => 'azul',
        ShipmentStatus::Incidencia => 'alerta',
        default => 'gris',
    };
@endphp

<x-backoffice.tone-badge :tone="$tone" {{ $attributes }}>
    {{ $case?->label() ?? __('Estado desconocido') }}
</x-backoffice.tone-badge>
