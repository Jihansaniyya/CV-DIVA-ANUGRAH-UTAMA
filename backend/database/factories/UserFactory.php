<?php

namespace Database\Factories;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'role_id' => Role::firstOrCreate(['code' => RoleCode::QS->value], ['name' => 'Quantity Surveyor'])->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
            'is_active' => true,
        ];
    }

    public function role(RoleCode $role): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(['code' => $role->value], ['name' => $role->label()])->id,
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
