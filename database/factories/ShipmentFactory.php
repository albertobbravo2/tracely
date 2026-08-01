<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tracking_number' => 'TRC-'.fake()->unique()->numerify('##########'),
            'sender_id' => User::factory(),
            'receiver_name' => fake()->name(),
            'origin' => fake()->city().', '.fake()->country(),
            'destination' => fake()->city().', '.fake()->country(),
            'estimated_delivery_date' => fake()->dateTimeBetween('+2 days', '+30 days')->format('Y-m-d'),
            'status' => ShipmentStatus::Pendiente,
        ];
    }

    /**
     * Envío con un estado concreto.
     */
    public function status(ShipmentStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Envío en tránsito.
     */
    public function enTransito(): static
    {
        return $this->status(ShipmentStatus::EnTransito);
    }

    /**
     * Envío retenido en aduana.
     */
    public function enAduana(): static
    {
        return $this->status(ShipmentStatus::EnAduana);
    }

    /**
     * Envío ya entregado: la fecha estimada queda en el pasado.
     */
    public function entregado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShipmentStatus::Entregado,
            'estimated_delivery_date' => fake()->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
        ]);
    }

    /**
     * Envío con incidencia.
     */
    public function incidencia(): static
    {
        return $this->status(ShipmentStatus::Incidencia);
    }

    /**
     * Envío de un remitente ya existente.
     */
    public function forSender(User $sender): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => $sender->id,
        ]);
    }
}
