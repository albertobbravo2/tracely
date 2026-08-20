<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
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
            'document_name' => fake()->randomElement([
                'Factura comercial',
                'Packing list',
                'Certificado de origen',
                'Despacho de aduana',
                'Conocimiento de embarque',
            ]).'.pdf',
            // No pasa por Storage: el disco `documents` guarda el fichero real solo
            // cuando se sube vía el controlador. Aquí basta un path con forma
            // plausible, ya que el modelo lo expone como oculto (ver `#[Hidden]`).
            'file_path' => fake()->uuid().'.pdf',
            'status' => 'pendiente',
        ];
    }

    /**
     * Documento aprobado.
     */
    public function aprobado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'aprobado',
        ]);
    }

    /**
     * Documento rechazado.
     */
    public function rechazado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rechazado',
        ]);
    }

    /**
     * Documento de un envío ya existente.
     */
    public function forShipment(Shipment $shipment): static
    {
        return $this->state(fn (array $attributes) => [
            'shipment_id' => $shipment->id,
        ]);
    }
}
