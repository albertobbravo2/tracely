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
     *
     * Idempotente a propósito: usa firstOrCreate y syncRoles, así que se puede
     * relanzar sobre una base ya sembrada para incorporar permisos nuevos sin
     * migrate:fresh. Con create() reventaba en el primer rol ya existente.
     */
    public function run(): void
    {
        // Eliminar caché actual de permisos y roles
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ROLES
        $agente = Role::firstOrCreate(['name' => 'agente']);
        $administrador = Role::firstOrCreate(['name' => 'administrador']);
        $superadministrador = Role::firstOrCreate(['name' => 'superadministrador']);

        $empleado = [$agente, $administrador, $superadministrador];
        $admins = [$administrador, $superadministrador];
        // Gestionar empresas es una acción de plataforma, no de una empresa
        // concreta: solo el superadministrador.
        $superadmin = [$superadministrador];
        // PERMISOS

        // shipment
        // "ver pedido" cubre también los usuarios vinculados a un envío:
        // consultarlos es leer el envío, no gestionar cuentas.
        Permission::firstOrCreate(['name' => 'crear pedido'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'ver pedido'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'editar pedido'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'eliminar pedido'])->syncRoles($empleado);

        // shipment_history
        Permission::firstOrCreate(['name' => 'crear historial de pedido'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'ver historial de pedido'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'editar historial de pedido'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'eliminar historial de pedido'])->syncRoles($empleado);

        // user
        Permission::firstOrCreate(['name' => 'crear usuario'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'ver usuario'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'editar usuario'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'eliminar usuario'])->syncRoles($empleado);

        Permission::firstOrCreate(['name' => 'ver pedidos usuario'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'ver empresa usuario'])->syncRoles($empleado);

        // company
        Permission::firstOrCreate(['name' => 'crear empresa'])->syncRoles($superadmin);
        Permission::firstOrCreate(['name' => 'ver empresas'])->syncRoles($superadmin);
        Permission::firstOrCreate(['name' => 'editar empresa'])->syncRoles($superadmin);
        Permission::firstOrCreate(['name' => 'eliminar empresa'])->syncRoles($superadmin);
        Permission::firstOrCreate(['name' => 'ver usuarios empresa'])->syncRoles($superadmin);
        // document
        // Los documentos no son públicos como el seguimiento: solo empleados.
        Permission::firstOrCreate(['name' => 'crear documento'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'ver documento'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'editar documento'])->syncRoles($empleado);
        Permission::firstOrCreate(['name' => 'eliminar documento'])->syncRoles($empleado);

        // El superadministrador es el único rol sin restricciones: se le concede
        // todo lo declarado arriba, incluida la gestión de empresas.
        // El administrador se queda con lo que le hayan dado los syncRoles().
        $superadministrador->givePermissionTo(Permission::all());

    }
}
