<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'account_type' => AccountType::Member,
            'name' => fake()->name(),
            'phone' => '+2519'.fake()->unique()->numerify('########'),
            'email' => null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(['account_type' => AccountType::Owner]);
    }

    /** Staff member with the given role in the given branch (same organization). */
    public function staffAt(Branch $branch, BranchRole $role): static
    {
        return $this->state([
            'organization_id' => $branch->organization_id,
            'account_type' => AccountType::Staff,
        ])->afterCreating(fn (User $user) => $user->branches()->attach($branch->id, ['role' => $role->value]));
    }

    public function disabled(): static
    {
        return $this->state(['status' => 'disabled']);
    }
}
