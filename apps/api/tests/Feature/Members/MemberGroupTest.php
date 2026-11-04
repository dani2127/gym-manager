<?php

namespace Tests\Feature\Members;

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberGroupTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        Sanctum::actingAs(User::factory()->owner()->create(['organization_id' => $this->branch->organization_id]));
    }

    public function test_create_couple_and_see_it_on_both_profiles(): void
    {
        [$a, $b] = Member::factory()->count(2)->at($this->branch)->create();

        $id = $this->postJson('/api/v1/member-groups', ['type' => 'couple', 'name' => 'Hana & Dawit', 'members' => [$a->ulid, $b->ulid], 'primary_member' => $b->ulid])
            ->assertCreated()
            ->assertJsonPath('data.primary_member_id', $b->ulid)
            ->json('data.id');

        $this->getJson("/api/v1/members/{$a->ulid}")
            ->assertJsonPath('data.group.id', $id)
            ->assertJsonCount(2, 'data.group.members');
    }

    public function test_group_sizes_are_enforced(): void
    {
        $members = Member::factory()->count(3)->at($this->branch)->create();

        $this->postJson('/api/v1/member-groups', ['type' => 'couple', 'name' => 'Three', 'members' => $members->pluck('ulid')->all()])
            ->assertJsonValidationErrors('members');
        $this->postJson('/api/v1/member-groups', ['type' => 'family', 'name' => 'Solo', 'members' => [$members[0]->ulid]])
            ->assertJsonValidationErrors('members');
    }

    public function test_a_member_belongs_to_one_group_only(): void
    {
        $members = Member::factory()->count(3)->at($this->branch)->create();
        $this->postJson('/api/v1/member-groups', ['type' => 'couple', 'name' => 'First', 'members' => [$members[0]->ulid, $members[1]->ulid]])->assertCreated();

        $this->postJson('/api/v1/member-groups', ['type' => 'couple', 'name' => 'Second', 'members' => [$members[1]->ulid, $members[2]->ulid]])
            ->assertJsonValidationErrors(['members' => 'First']);
    }

    public function test_update_members_and_delete_group(): void
    {
        $members = Member::factory()->count(3)->at($this->branch)->create();
        $id = $this->postJson('/api/v1/member-groups', ['type' => 'family', 'name' => 'Family', 'members' => [$members[0]->ulid, $members[1]->ulid]])->json('data.id');

        $this->patchJson("/api/v1/member-groups/{$id}", ['members' => $members->pluck('ulid')->all()])
            ->assertOk()
            ->assertJsonCount(3, 'data.members');

        $this->deleteJson("/api/v1/member-groups/{$id}")->assertNoContent();
        $this->getJson("/api/v1/members/{$members[0]->ulid}")->assertJsonPath('data.group', null);
    }

    public function test_cannot_group_members_from_another_gym(): void
    {
        $mine = Member::factory()->at($this->branch)->create();
        $foreign = Member::factory()->create();

        $this->postJson('/api/v1/member-groups', ['type' => 'couple', 'name' => 'X', 'members' => [$mine->ulid, $foreign->ulid]])
            ->assertJsonValidationErrors('members');
    }
}
