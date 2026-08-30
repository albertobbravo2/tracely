<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentHistory>
 */
class ShipmentHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'status' => ShipmentStatus::Pendiente,
            'location' => fake()->city().', '.fake()->country(),
            'description' => fake()->sentence(),
            'recorded_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Evento con un estado concreto.
     */
    public function status(ShipmentStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Evento de envío en tránsito.
     */
    public function enTransito(): static
    {
        return $this->status(ShipmentStatus::EnTransito);
    }

    /**
     * Evento de envío retenido en aduana.
     */
    public function enAduana(): static
    {
        return $this->status(ShipmentStatus::EnAduana);
    }

    /**
     * Evento de entrega.
     */
    public function entregado(): static
    {
        return $this->status(ShipmentStatus::Entregado);
    }

    /**
     * Evento de incidencia.
     */
    public function incidencia(): static
    {
        return $this->status(ShipmentStatus::Incidencia);
    }

    /**
     * Evento de un envío ya existente.
     */
    public function forShipment(Shipment $shipment): static
    {
        return $this->state(fn (array $attributes) => [
            'shipment_id' => $shipment->id,
        ]);
    }
}
