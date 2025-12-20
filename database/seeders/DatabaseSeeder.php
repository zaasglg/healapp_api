<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Enums\UserType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed roles and permissions first
        $this->call(RolesSeeder::class);

        // 2. Create test organization owner
        $owner = User::create([
            'first_name' => 'Иван',
            'last_name' => 'Владелец',
            'phone' => '79001111111',
            'password' => Hash::make('password'),
            'type' => UserType::ORGANIZATION->value,
            'phone_verified_at' => now(),
        ]);

        // 3. Create test organization
        $organization = Organization::create([
            'owner_id' => $owner->id,
            'name' => 'Тестовый пансионат',
            'type' => OrganizationType::BOARDING_HOUSE->value,
            'phone' => '79001111111',
            'address' => 'г. Москва, ул. Тестовая, д. 1',
        ]);

        // 4. Assign owner to organization and role
        $owner->organization_id = $organization->id;
        $owner->save();
        $owner->assignRole('owner');

        // 5. Create test employee (caregiver)
        $caregiver = User::create([
            'first_name' => 'Мария',
            'last_name' => 'Сиделка',
            'phone' => '79002222222',
            'password' => Hash::make('password'),
            'type' => UserType::CLIENT->value,
            'organization_id' => $organization->id,
            'phone_verified_at' => now(),
        ]);
        $caregiver->assignRole('caregiver');

        // 6. Create test doctor
        $doctor = User::create([
            'first_name' => 'Петр',
            'last_name' => 'Врач',
            'phone' => '79003333333',
            'password' => Hash::make('password'),
            'type' => UserType::CLIENT->value,
            'organization_id' => $organization->id,
            'phone_verified_at' => now(),
        ]);
        $doctor->assignRole('doctor');

        // 7. Create test client
        $client = User::create([
            'first_name' => 'Анна',
            'last_name' => 'Клиент',
            'phone' => '79004444444',
            'password' => Hash::make('password'),
            'type' => UserType::CLIENT->value,
            'phone_verified_at' => now(),
        ]);

        // 8. Create test private caregiver
        $privateCaregiver = User::create([
            'first_name' => 'Ольга',
            'last_name' => 'Частная',
            'phone' => '79005555555',
            'password' => Hash::make('password'),
            'type' => UserType::PRIVATE_CAREGIVER->value,
            'phone_verified_at' => now(),
        ]);

        $this->command->info('Test users created:');
        $this->command->table(
            ['Phone', 'Password', 'Role'],
            [
                ['79001111111', 'password', 'owner'],
                ['79002222222', 'password', 'caregiver'],
                ['79003333333', 'password', 'doctor'],
                ['79004444444', 'password', 'client'],
                ['79005555555', 'password', 'private_caregiver'],
            ]
        );
    }
}
