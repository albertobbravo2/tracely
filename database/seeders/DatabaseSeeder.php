<?php

namespace Database\Seeders;

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
            "name" => "Admin",
            "email" => "admin@tracely.com",
            "password" => "admin",
            "email_verified_at" => now()
        ])->assignRole('administrador');

        User::create([
            "name" => "Agente",
            "email" => "agente@tracely.com",
            "password" => "agente",
            "email_verified_at" => now()
        ])->assignRole('agente');

        User::create([
            "name" => "cliente",
            "email" => "cliente@tracely.com",
            "password" => "cliente",
            "email_verified_at" => now()
        ])->assignRole('cliente');
        /*
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            ]);
            */
        }
}
