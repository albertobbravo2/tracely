<?php

namespace Database\Seeders;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(RolesAndPermissionsSeeder::class);

        User::create([
            'name' => 'Super Administrador',
            'email' => 'superadministrador@tracely.com',
            'password' => 'superadministrador',
            'email_verified_at' => now(),
        ])->assignRole('superadministrador');

        User::create([
            'name' => 'Agente',
            'email' => 'agente@tracely.com',
            'password' => 'agente',
            'email_verified_at' => now(),
        ])->assignRole('agente');

        User::create([
            'name' => 'cliente',
            'email' => 'cliente@tracely.com',
            'password' => 'cliente',
            'email_verified_at' => now(),
        ]);
        /*
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            ]);
            */

        Shipment::factory(10)->create();

        $shipment = Shipment::create([
            'tracking_number' => '1234', // pon el tuyo
            'sender_id' => 1, // id del User remitente (Admin, Agente o cliente)
            'receiver_name' => 'Nombre destinatario',
            'origin' => 'Ciudad de origen',
            'destination' => 'Ciudad de destino',
            'estimated_delivery_date' => now()->addDays(5)->toDateString(),
            'status' => ShipmentStatus::Pendiente,
        ]);

        // línea de tiempo del envío anterior, para probar el histórico en la API
        $shipment->histories()->createMany([
            [
                'status' => ShipmentStatus::Pendiente,
                'location' => 'Guadalajara, JAL',
                'description' => 'Paquete recogido',
                'recorded_at' => now()->subDays(3),
            ],
            [
                'status' => ShipmentStatus::EnTransito,
                'location' => 'Zapopan, JAL',
                'description' => 'En centro de distribución',
                'recorded_at' => now()->subDays(2),
            ],
            [
                'status' => ShipmentStatus::EnTransito,
                'location' => 'Monterrey, NL',
                'description' => 'En ruta de entrega',
                'recorded_at' => now()->subDay(),
            ],
        ]);
    }
}
