<?php

namespace Database\Factories;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->boolean(30) ? fake()->firstName() : null,
            'phone' => fake()->unique()->numerify('79#########'),
            'phone_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'type' => UserType::CLIENT->value,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's phone should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Create an organization owner.
     */
    public function organizationOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::ORGANIZATION->value,
        ]);
    }

    /**
     * Create a private caregiver.
     */
    public function privateCaregiver(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::PRIVATE_CAREGIVER->value,
        ]);
    }

    /**
     * Create a client.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::CLIENT->value,
        ]);
    }
}
