<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Creazione dei permessi di base
        $permissions = [
            // Gestione utenti
            'view users',
            'create users',
            'edit users',
            'delete users',
            'import users',

            // Gestione corsi
            'view courses',
            'create courses',
            'edit courses',
            'delete courses',
            'duplicate courses',
            'manage course content',

            // Gestione moduli
            'view modules',
            'create modules',
            'edit modules',
            'delete modules',

            // Gestione documenti
            'view documents',
            'upload documents',
            'delete documents',
            'manage document repository',

            // Gestione faculty
            'view faculty',
            'create faculty',
            'edit faculty',
            'delete faculty',
            'assign faculty to courses',

            // Report e attestati
            'view reports',
            'generate certificates',
            'manage attendance',

            // Impostazioni e configurazioni
            'manage settings',
            'manage job data',
            'view logs',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Creazione dei ruoli
        $superadmin = Role::create(['name' => 'superadmin']);
        $superadmin->givePermissionTo(Permission::all());

        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo([
            'view users', 'create users', 'edit users', 'import users',
            'view courses', 'create courses', 'edit courses', 'duplicate courses', 'manage course content',
            'view modules', 'create modules', 'edit modules',
            'view documents', 'upload documents', 'manage document repository',
            'view faculty', 'create faculty', 'edit faculty', 'assign faculty to courses',
            'view reports', 'generate certificates', 'manage attendance',
            'manage job data',
        ]);

        $docente = Role::create(['name' => 'docente']);
        $docente->givePermissionTo([
            'view courses',
            'view modules',
            'view documents', 'upload documents',
            'manage attendance',
            'view reports',
        ]);

        $tutor = Role::create(['name' => 'tutor']);
        $tutor->givePermissionTo([
            'view courses',
            'view modules',
            'view documents',
            'manage attendance',
            'view reports',
        ]);

        $user = Role::create(['name' => 'user']);
        $user->givePermissionTo([
            'view courses',
            'view modules',
        ]);
    }
}
