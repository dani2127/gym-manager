<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    private const FIRST = ['Hana', 'Selam', 'Meron', 'Bethlehem', 'Tigist', 'Mahlet', 'Ruth', 'Sara', 'Dawit', 'Yonas', 'Abel', 'Samuel', 'Kidus', 'Nahom', 'Biniyam', 'Henok'];

    private const LAST = ['Tesfaye', 'Bekele', 'Alemu', 'Haile', 'Girma', 'Kebede', 'Tadesse', 'Mekonnen', 'Wolde', 'Gebre', 'Assefa', 'Abebe'];

    private static int $number = 1;

    public function definition(): array
    {
        return [
            'home_branch_id' => Branch::factory(),
            'organization_id' => fn (array $attributes) => Branch::withoutGlobalScopes()->find($attributes['home_branch_id'])->organization_id,
            'member_no' => self::$number++,
            'first_name' => fake()->randomElement(self::FIRST),
            'last_name' => fake()->randomElement(self::LAST),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => '+2519'.fake()->unique()->numerify('########'),
            'joined_at' => fake()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    public function at(Branch $branch): static
    {
        return $this->state(['home_branch_id' => $branch->id, 'organization_id' => $branch->organization_id]);
    }
}
