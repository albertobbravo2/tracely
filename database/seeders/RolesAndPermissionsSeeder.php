<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;


//Crear Roles y Permisos
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //Eliminar caché actual de permisos y roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        //Permisos
        Permission::create(["name" => "gestionar estado pedido"]);

        //
        $cliente = Role::create(["name" => "cliente"]);
        $agente = Role::create(["name" => "agente"]);
        $admin = Role::create(["name" => "admin"]);

        //Asignar permisos a roles
        $agente->givePermissionTo("gestionar estado pedido");
        $admin->givePermissionTo(Permission::all());

    }
}
