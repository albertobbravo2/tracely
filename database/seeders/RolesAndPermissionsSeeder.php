<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Crear Roles y Permisos
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Eliminar caché actual de permisos y roles
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ROLES
        $agente = Role::create(['name' => 'agente']);
        $administrador = Role::create(['name' => 'administrador']);
        $superadministrador = Role::create(['name' => 'superadministrador']);

        $empleado = [$agente, $administrador, $superadministrador ];
        // PERMISOS

        // shipment
        Permission::create(['name' => 'crear pedido'])->syncRoles($empleado);
        // Permission::create(["name" => "ver pedido"])->syncRoles($todos);
        Permission::create(['name' => 'editar pedido'])->syncRoles($empleado);
        Permission::create(['name' => 'eliminar pedido'])->syncRoles($empleado);
        // shipment_history
        Permission::create(['name' => 'crear historial de pedido'])->syncRoles($empleado);
        // Permission::create(["name" => "ver historial de pedido"])->syncRoles($todos);
        // user
        Permission::create(['name' => 'crear usuario'])->syncRoles($empleado);
        // Permission::create(["name" => "ver usuario"])->syncRoles($todos);
        Permission::create(['name' => 'editar usuario'])->syncRoles($empleado);
        Permission::create(['name' => 'eliminar usuario'])->syncRoles($empleado);
        // role
        Permission::create(['name' => 'crear rol'])->syncRoles($administrador);
        Permission::create(['name' => 'ver rol'])->syncRoles($empleado);
        Permission::create(['name' => 'editar rol'])->syncRoles($administrador);
        Permission::create(['name' => 'eliminar rol'])->syncRoles($administrador);
        // document
        Permission::create(['name' => 'crear documento'])->syncRoles($empleado);
        // Permission::create(["name" => "ver documento"])->syncRoles($todos);
        Permission::create(['name' => 'editar documento'])->syncRoles($empleado);
        Permission::create(['name' => 'eliminar documento'])->syncRoles($empleado);
        // dashboard
        // Permission::create(["name" => "ver dashboard"])->syncRoles($cliente);

        // Asignar permisos a roles
        $agente->givePermissionTo('crear pedido');
        $administrador->givePermissionTo(Permission::all());

    }
}
