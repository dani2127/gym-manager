<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberGroup;
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

        $this->seedMembers($organization, [$bole, $piassa]);
    }

    /** @param  list<Branch>  $branches */
    private function seedMembers(Organization $organization, array $branches): void
    {
        $people = [
            ['Hana', 'Tesfaye', 'female'], ['Dawit', 'Bekele', 'male'], ['Meron', 'Alemu', 'female'], ['Yonas', 'Haile', 'male'],
            ['Selam', 'Girma', 'female'], ['Abel', 'Kebede', 'male'], ['Tigist', 'Tadesse', 'female'], ['Samuel', 'Mekonnen', 'male'],
            ['Bethlehem', 'Wolde', 'female'], ['Kidus', 'Gebre', 'male'], ['Mahlet', 'Assefa', 'female'], ['Nahom', 'Abebe', 'male'],
            ['Ruth', 'Tesfaye', 'female'], ['Biniyam', 'Alemu', 'male'], ['Sara', 'Haile', 'female'], ['Henok', 'Girma', 'male'],
            ['Liya', 'Kebede', 'female'], ['Eyob', 'Tadesse', 'male'], ['Feven', 'Mekonnen', 'female'], ['Robel', 'Wolde', 'male'],
        ];

        $members = [];
        foreach ($people as $i => [$first, $last, $gender]) {
            $members[] = Member::create([
                'organization_id' => $organization->id,
                'home_branch_id' => $branches[$i % 3 === 2 ? 1 : 0]->id,
                'member_no' => $organization->takeMemberNumber(),
                'first_name' => $first,
                'last_name' => $last,
                'gender' => $gender,
                'phone' => '+25191200'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'joined_at' => now()->subDays(20 + $i * 17),
                'status' => $i === 7 ? 'inactive' : 'active',
            ]);
        }

        $couple = MemberGroup::create(['organization_id' => $organization->id, 'type' => 'couple', 'name' => 'Hana & Dawit', 'primary_member_id' => $members[0]->id]);
        $couple->members()->sync([$members[0]->id, $members[1]->id]);

        $family = MemberGroup::create(['organization_id' => $organization->id, 'type' => 'family', 'name' => 'Tesfaye family', 'primary_member_id' => $members[12]->id]);
        $family->members()->sync([$members[12]->id, $members[13]->id, $members[14]->id]);
    }
}
