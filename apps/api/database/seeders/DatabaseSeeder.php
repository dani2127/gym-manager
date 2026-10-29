<?php

namespace Database\Seeders;

use App\Billing\Ledger;
use App\Billing\Pricing;
use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\DiscountRule;
use App\Models\Fee;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use App\Support\Tenancy\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Demo data for local development. Sign in with organization "demo-gym",
 * password "password" and one of the phones below.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::create(['name' => 'Demo Gym', 'slug' => 'demo-gym']);
        app(Tenant::class)->set($organization);

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

        $members = $this->seedMembers($organization, [$bole, $piassa]);
        $this->seedCatalogAndSales($organization, $bole, $members, User::where('phone', '+251911000003')->first());
    }

    /**
     * @param  list<Branch>  $branches
     * @return list<Member>
     */
    private function seedMembers(Organization $organization, array $branches): array
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

        return $members;
    }

    /** Plans, fees and discounts at Addis prices, plus a few sales so screens have history. */
    /** @param  list<Member>  $members */
    private function seedCatalogAndSales(Organization $organization, Branch $bole, array $members, User $reception): void
    {
        $plan = fn (array $attributes) => Plan::create(['organization_id' => $organization->id, ...$attributes]);

        $monthly = $plan(['name' => ['en' => 'Monthly', 'am' => 'ወርሃዊ'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 250000, 'freeze_allowed' => true, 'max_freeze_days' => 7, 'max_freezes' => 1, 'sort' => 1]);
        $quarter = $plan(['name' => ['en' => '3 months', 'am' => 'የ3 ወር'], 'type' => 'time', 'duration_days' => 90, 'price_cents' => 650000, 'freeze_allowed' => true, 'max_freeze_days' => 14, 'max_freezes' => 2, 'sort' => 2]);
        $plan(['name' => ['en' => 'Annual, all branches', 'am' => 'ዓመታዊ፣ ሁሉም ቅርንጫፎች'], 'type' => 'time', 'duration_days' => 365, 'price_cents' => 2200000, 'access_scope' => 'all_branches', 'freeze_allowed' => true, 'max_freeze_days' => 30, 'max_freezes' => 3, 'sort' => 3]);
        $tenVisits = $plan(['name' => ['en' => '10-visit pass', 'am' => 'የ10 ጉብኝት'], 'type' => 'time_visits', 'duration_days' => 60, 'visit_count' => 10, 'price_cents' => 150000, 'sort' => 4]);
        $dayPass = $plan(['name' => ['en' => 'Day pass', 'am' => 'የአንድ ቀን'], 'type' => 'time_visits', 'duration_days' => 1, 'visit_count' => 1, 'price_cents' => 30000, 'sort' => 5]);

        $registration = Fee::create(['organization_id' => $organization->id, 'name' => ['en' => 'Registration', 'am' => 'ምዝገባ'], 'kind' => 'registration', 'amount_cents' => 50000]);
        Fee::create(['organization_id' => $organization->id, 'name' => ['en' => 'Card replacement', 'am' => 'ካርድ መተኪያ'], 'kind' => 'card_replacement', 'amount_cents' => 10000]);

        $discount = fn (array $attributes) => DiscountRule::create(['organization_id' => $organization->id, ...$attributes]);
        $student = $discount(['name' => ['en' => 'Student', 'am' => 'ተማሪ'], 'kind' => 'student', 'value_type' => 'percent', 'value' => 15, 'requires_proof' => true]);
        $couple = $discount(['name' => ['en' => 'Couple', 'am' => 'ጥንዶች'], 'kind' => 'couple', 'value_type' => 'percent', 'value' => 10]);
        $discount(['name' => ['en' => 'Family of 3+', 'am' => 'ቤተሰብ (3+)'], 'kind' => 'family', 'value_type' => 'percent', 'value' => 15, 'min_group_size' => 3]);
        $discount(['name' => ['en' => 'Opening offer', 'am' => 'የመክፈቻ ቅናሽ'], 'kind' => 'promo', 'value_type' => 'fixed', 'value' => 50000, 'promo_code' => 'BOLE500', 'valid_until' => now()->addMonths(2)->toDateString()]);

        // Demo gym charges 15% VAT, included in its prices.
        app(SettingsRepository::class)->put($organization, ['tax.enabled' => true, 'tax.name' => 'VAT', 'tax.rate' => 15, 'tax.prices_inclusive' => true, 'freeze.enabled' => true]);

        // Sales spread over recent weeks, rung up as the desk would: member,
        // plan, discount, days ago, payment method, amount paid (null = all).
        $ledger = app(Ledger::class);
        $pricing = app(Pricing::class);
        $sales = [
            [0, $monthly, $couple, 26, 'cash', null],
            [1, $monthly, $couple, 26, 'pos_card', null],
            [2, $quarter, null, 40, 'cash', null],
            [3, $tenVisits, $student, 5, 'cash', null],
            [5, $quarter, null, 3, 'pos_card', null],
            [6, $monthly, null, 45, 'cash', null],
            [9, $dayPass, null, 0, 'cash', null],
            [4, $monthly, null, 0, 'cash', 150000],
        ];
        $now = now();
        foreach (collect($sales)->sortByDesc(3)->values() as $i => [$index, $salePlan, $saleDiscount, $daysAgo, $method, $amount]) {
            // Numbers follow time, as they would at a real desk.
            Carbon::setTestNow($daysAgo ? $now->copy()->subDays($daysAgo)->setTime(8, 10 * $i) : $now->copy()->subMinutes(10 * (count($sales) - $i)));
            $member = $members[$index];
            $quote = $pricing->quote($bole, $salePlan, $pricing->suggestedFees($member), $saleDiscount, $organization->today());
            $ledger->sell($quote, $bole, $member, $reception, ['method' => $method, 'amount_cents' => $amount ?? $quote->totalCents()]);
            Carbon::setTestNow();
        }

        Artisan::call('memberships:expire');
    }
}
