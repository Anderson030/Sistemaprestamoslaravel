<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisosPrestamistasSeeder extends Seeder
{
    public function run()
    {
        // Crear permisos si no existen
        Permission::firstOrCreate(['name' => 'admin.prestamistas.index']);
        Permission::firstOrCreate(['name' => 'admin.prestamistas.detalle']);

        // Roles
        $admin = Role::where('name', 'ADMINISTRADOR')->first();
        $supervisor = Role::where('name', 'SUPERVISOR')->first();

        // Asignar permisos
        foreach ([$admin, $supervisor] as $role) {
            if ($role) {
                $role->givePermissionTo([
                    'admin.prestamistas.index',
                    'admin.prestamistas.detalle',
                ]);
            }
        }
    }
}
