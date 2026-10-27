<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development. Sign in with organization "demo-gym",
 * password "password" and one of the phones below.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::create(['name' => 'Demo Gym', 'slug' => 'demo-gym']);

        $bole = Branch::create(['organization_id' => $organization->id, 'name' => 'Bole', 'code' => 'BOLE', 'city' => 'Addis Ababa']);
        $piassa = Branch::create(['organization_id' => $organization->id, 'name' => 'Piassa', 'code' => 'PIASSA', 'city' => 'Addis Ababa']);

        $people = [
            ['Owner Abebe', '+251911000001', AccountType::Owner, []],
            ['Manager Hana', '+251911000002', AccountType::Staff, [$bole->id => BranchRole::Manager]],
            ['Reception Selam', '+251911000003', AccountType::Staff, [$bole->id => BranchRole::Receptionist]],
            ['Trainer Dawit', '+251911000004', AccountType::Staff, [$bole->id => BranchRole::Trainer, $piassa->id => BranchRole::Trainer]],
        ];

        foreach ($people as [$name, $phone, $type, $roles]) {
            $user = User::create([
                'organization_id' => $organization->id,
                'account_type' => $type,
                'name' => $name,
                'phone' => $phone,
                'password' => 'password',
            ]);

            foreach ($roles as $branchId => $role) {
                $user->branches()->attach($branchId, ['role' => $role->value]);
            }
        }
    }
}
