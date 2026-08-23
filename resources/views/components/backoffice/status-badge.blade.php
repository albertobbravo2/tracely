@use('App\Enums\ShipmentStatus')

@props([
    'status',
])

@php
    // Misma correspondencia estado → paleta que ⚡searchfield y ⚡my-shipments:
    // `ok` entregado, `alerta` incidencia, `azul` en tránsito/aduana, `gris`
    // pendiente o cualquier estado que no reconozcamos.
    $case = ShipmentStatus::tryFrom((string) $status);

    $classes = match ($case) {
        ShipmentStatus::Entregado => '!bg-ok-fondo !text-ok-fuerte',
        ShipmentStatus::EnTransito, ShipmentStatus::EnAduana => '!bg-azul-050 !text-azul-800',
        ShipmentStatus::Incidencia => '!bg-alerta-fondo !text-alerta-fuerte',
        default => '!bg-gris-050 !text-gris-600',
    };
@endphp

<flux:badge rounded size="sm" class="{{ $classes }}">
    {{ $case?->label() ?? __('Estado desconocido') }}
</flux:badge>
