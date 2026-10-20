<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

// Cuentas iniciales de producción: los mismos usuarios que DatabaseSeeder, sin
// los envíos de prueba y con la contraseña de cada uno en una variable de
// entorno (las de DatabaseSeeder están escritas en el repositorio). Si falta la
// variable, esa cuenta no se crea.
//
// Idempotente a propósito: una cuenta que ya existe no se toca, así que el
// build puede lanzarlo en cada despliegue sin restablecer contraseñas que se
// hayan cambiado después.
class ProductionUsersSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Super Administrador', 'superadministrador@tracely.com', 'SEED_SUPERADMIN_PASSWORD', 'superadministrador'],
            ['Agente', 'agente@tracely.com', 'SEED_AGENTE_PASSWORD', 'agente'],
            ['cliente', 'cliente@tracely.com', 'SEED_CLIENTE_PASSWORD', null],
        ];

        foreach ($accounts as [$name, $email, $passwordVariable, $role]) {
            if (User::where('email', $email)->exists()) {
                $this->command?->line("{$email}: ya existe, no se toca.");

                continue;
            }

            $password = getenv($passwordVariable);
            if (! $password) {
                $this->command?->warn("{$email}: falta {$passwordVariable}, no se crea.");

                continue;
            }

            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            // email_verified_at no está en #[Fillable].
            $user->forceFill(['email_verified_at' => now()])->save();

            if ($role) {
                $user->assignRole($role);
            }

            $this->command?->info("{$email}: creado".($role ? " con rol {$role}." : '.'));
        }
    }
}
