<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Сбрасываем кеш permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ========================================
        // PERMISSIONS
        // ========================================
        
        $permissions = [
            // Пациенты
            'patients.create',
            'patients.view',
            'patients.edit',
            'patients.delete',
            
            // Дневники
            'diaries.create',
            'diaries.view',
            'diaries.edit',
            'diaries.fill',
            
            // Маршрутные листы
            'tasks.create',
            'tasks.view',
            'tasks.edit',
            'tasks.complete',
            
            // Управление доступом
            'access.manage',
            
            // Сотрудники
            'employees.invite',
            'employees.manage',
            
            // Клиенты
            'clients.invite',
            
            // Организация
            'organization.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // ========================================
        // ROLES (организационные)
        // ========================================

        // Owner - полные права
        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->syncPermissions(Permission::all());

        // Admin - почти все права
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'patients.create', 'patients.view', 'patients.edit', 'patients.delete',
            'diaries.create', 'diaries.view', 'diaries.edit', 'diaries.fill',
            'tasks.create', 'tasks.view', 'tasks.edit', 'tasks.complete',
            'access.manage',
            'employees.invite', 'employees.manage',
            'clients.invite',
            'organization.edit',
        ]);

        // Doctor - ограниченные права
        $doctor = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $doctor->syncPermissions([
            'patients.view',
            'diaries.view', 'diaries.fill',
            'tasks.create', 'tasks.view', 'tasks.edit',
        ]);

        // Caregiver - минимальные права
        $caregiver = Role::firstOrCreate(['name' => 'caregiver', 'guard_name' => 'web']);
        $caregiver->syncPermissions([
            'patients.view',
            'diaries.view', 'diaries.fill',
            'tasks.view', 'tasks.complete',
        ]);

        // ========================================
        // SYSTEM ROLES
        // ========================================
        
        // Super admin (для внутреннего использования)
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());
    }
}
