<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * @deprecated Используйте PermissionsSeeder вместо этого.
 * Роли организаций теперь хранятся в pivot-таблице organization_user.
 */
class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // DEPRECATED: Роли организаций (owner, admin, doctor, caregiver)
        // теперь хранятся в pivot-таблице organization_user, а не в Spatie roles.
        //
        // Используйте PermissionsSeeder для создания гранулярных permissions.
        //
        // См. App\Enums\OrganizationRole для списка ролей.

        $this->call(PermissionsSeeder::class);
    }
}
