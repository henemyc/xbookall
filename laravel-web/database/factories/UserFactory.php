<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
// Phase 5 safe user factory for isolated SQLite tests.
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => '9' . fake()->unique()->numerify('#########'),
            'type' => 'admin',
            'password' => Hash::make('password'),
            'parent_id' => 0,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }
}
