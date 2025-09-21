<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Enums\UserStatus;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $now = now();

        return [
            'employee_id'        => $this->faker->unique()->numerify('EMP####'),
            'name'               => $this->faker->name(),
            'email'              => $this->faker->unique()->safeEmail(),
            'mobile'             => $this->faker->numerify('017########'), // 10-digit number
            'email_verified_at'  => $now, // verified by default
            'is_verified'        => 1,
            'password'           => static::$password ??= Hash::make('password'),
            'current_status'     => UserStatus::OFFLINE,
            'max_limit'          => 1,
            'current_limit'      => 0,
            'account_status'     => 'active',
            'is_request'         => 0,
            'is_password_updated'=> 0,
            'role_id'            => 1, // default role
            'created_by'         => 1,
            'updated_by'         => 1,
            'deleted_by'         => null,
            'remember_token'     => Str::random(10),
            'created_at'         => $now,
            'updated_at'         => $now,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
