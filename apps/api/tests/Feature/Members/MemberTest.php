<?php

namespace Tests\Feature\Members;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private Branch $piassa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->piassa = Branch::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function owner(): User
    {
        return User::factory()->owner()->create(['organization_id' => $this->organization->id]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'first_name' => 'Hana',
            'last_name' => 'Tesfaye',
            'home_branch' => $this->bole->ulid,
            'phone' => '0911 223 344',
            'gender' => 'female',
            ...$overrides,
        ];
    }

    public function test_receptionist_registers_a_member_with_sequential_bib_numbers(): void
    {
        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create());

        $this->postJson('/api/v1/members', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.member_no', '0001')
            ->assertJsonPath('data.phone', '+251911223344')
            ->assertJsonPath('data.home_branch.id', $this->bole->ulid)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonMissingPath('data.qr_secret');

        $this->postJson('/api/v1/members', $this->payload(['first_name' => 'Dawit', 'phone' => '0911000111']))
            ->assertJsonPath('data.member_no', '0002');

        $member = Member::first();
        $this->assertNotEmpty($member->qr_secret);
        $this->assertNotSame($member->qr_secret, $member->getRawOriginal('qr_secret'), 'qr_secret must be stored encrypted');
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.created', 'subject_id' => $member->id]);
    }

    public function test_numbers_are_per_organization(): void
    {
        $other = Branch::factory()->create();
        Sanctum::actingAs(User::factory()->owner()->create(['organization_id' => $other->organization_id]));
        $this->postJson('/api/v1/members', $this->payload(['home_branch' => $other->ulid]))->assertJsonPath('data.member_no', '0001');

        Sanctum::actingAs($this->owner());
        $this->postJson('/api/v1/members', $this->payload())->assertJsonPath('data.member_no', '0001');
    }

    public function test_duplicate_phone_needs_confirmation(): void
    {
        Sanctum::actingAs($this->owner());
        $this->postJson('/api/v1/members', $this->payload())->assertCreated();

        $this->postJson('/api/v1/members', $this->payload(['first_name' => 'Ruth']))
            ->assertJsonValidationErrors(['phone_duplicate' => 'Hana Tesfaye (#0001)']);

        $this->postJson('/api/v1/members', $this->payload(['first_name' => 'Ruth', 'confirm_duplicate' => true]))
            ->assertCreated();
    }

    public function test_editing_a_member_who_shares_a_phone_does_not_ask_again(): void
    {
        Sanctum::actingAs($this->owner());
        $this->postJson('/api/v1/members', $this->payload())->assertCreated();
        $id = $this->postJson('/api/v1/members', $this->payload(['first_name' => 'Ruth', 'confirm_duplicate' => true]))->json('data.id');

        $this->patchJson("/api/v1/members/{$id}", ['phone' => '0911 223 344', 'notes' => 'Shares with her mother'])->assertOk();
        $this->patchJson("/api/v1/members/{$id}", ['phone' => '0911 000 999'])->assertOk();
        $this->patchJson("/api/v1/members/{$id}", ['phone' => '0911 223 344'])->assertJsonValidationErrors('phone_duplicate');
    }

    public function test_search_by_name_words_number_and_phone(): void
    {
        Sanctum::actingAs($this->owner());
        Member::factory()->at($this->bole)->create(['first_name' => 'Hana', 'last_name' => 'Tesfaye', 'member_no' => 427, 'phone' => '+251911223344']);
        Member::factory()->at($this->piassa)->create(['first_name' => 'Hanna', 'last_name' => 'Bekele', 'member_no' => 12, 'phone' => '+251922000000']);

        $names = fn (string $q) => collect($this->getJson('/api/v1/members?search='.urlencode($q))->assertOk()->json('data'))->pluck('full_name')->all();

        $this->assertSame(['Hana Tesfaye', 'Hanna Bekele'], $names('han'));
        $this->assertSame(['Hana Tesfaye'], $names('hana tes'));
        $this->assertSame(['Hana Tesfaye'], $names('0427'));
        $this->assertSame(['Hana Tesfaye'], $names('0911 223 344'));
        $this->assertSame([], $names('zzz'));
    }

    public function test_filters_by_branch_and_status(): void
    {
        Sanctum::actingAs($this->owner());
        Member::factory()->at($this->bole)->create();
        Member::factory()->at($this->piassa)->create(['status' => 'inactive']);

        $this->getJson("/api/v1/members?branch={$this->piassa->ulid}")->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/members?status=active')->assertJsonCount(1, 'data');
    }

    public function test_trainers_can_view_but_not_register(): void
    {
        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Trainer)->create());
        $member = Member::factory()->at($this->bole)->create();

        $this->getJson("/api/v1/members/{$member->ulid}")->assertOk();
        $this->postJson('/api/v1/members', $this->payload())->assertForbidden();
        $this->patchJson("/api/v1/members/{$member->ulid}", ['first_name' => 'X'])->assertForbidden();
    }

    public function test_receptionist_only_edits_members_of_their_branch(): void
    {
        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create());
        $piassaMember = Member::factory()->at($this->piassa)->create();

        $this->getJson("/api/v1/members/{$piassaMember->ulid}")->assertOk();
        $this->patchJson("/api/v1/members/{$piassaMember->ulid}", ['first_name' => 'X'])->assertForbidden();
        $this->postJson('/api/v1/members', $this->payload(['home_branch' => $this->piassa->ulid]))->assertForbidden();
    }

    public function test_update_status_and_move_branch(): void
    {
        Sanctum::actingAs($this->owner());
        $member = Member::factory()->at($this->bole)->create();

        $this->patchJson("/api/v1/members/{$member->ulid}", ['status' => 'banned', 'home_branch' => $this->piassa->ulid])
            ->assertOk()
            ->assertJsonPath('data.status', 'banned')
            ->assertJsonPath('data.home_branch.id', $this->piassa->ulid);
    }

    public function test_status_cannot_be_set_on_create(): void
    {
        Sanctum::actingAs($this->owner());

        $this->postJson('/api/v1/members', $this->payload(['status' => 'banned']))->assertJsonValidationErrors('status');
    }

    public function test_only_managers_and_owners_delete_and_deleted_members_disappear(): void
    {
        $member = Member::factory()->at($this->bole)->create();

        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create());
        $this->deleteJson("/api/v1/members/{$member->ulid}")->assertForbidden();

        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Manager)->create());
        $this->deleteJson("/api/v1/members/{$member->ulid}")->assertNoContent();

        $this->getJson("/api/v1/members/{$member->ulid}")->assertNotFound();
        $this->assertSoftDeleted($member);
    }

    public function test_members_of_other_gyms_are_invisible(): void
    {
        $foreign = Member::factory()->create();
        Sanctum::actingAs($this->owner());

        $this->getJson('/api/v1/members')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/members/{$foreign->ulid}")->assertNotFound();
        $this->getJson("/api/v1/members/{$foreign->ulid}/photo")->assertNotFound();
        $this->postJson('/api/v1/members', $this->payload(['home_branch' => $foreign->homeBranch->ulid]))->assertNotFound();
    }

    public function test_photo_is_cropped_stored_privately_and_served_only_to_staff(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->owner());
        $member = Member::factory()->at($this->bole)->create();

        $url = $this->post("/api/v1/members/{$member->ulid}/photo", ['photo' => UploadedFile::fake()->image('me.jpg', 1200, 800)], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data.photo_url');

        $path = $member->fresh()->photo_path;
        Storage::disk('local')->assertExists($path);
        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame([512, 512], [$width, $height]);

        $this->get('/api/v1'.$url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Replacing the photo removes the old file.
        $this->post("/api/v1/members/{$member->ulid}/photo", ['photo' => UploadedFile::fake()->image('new.png', 600, 600)], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($path);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1'.$url)->assertUnauthorized();
    }

    public function test_photo_upload_validation(): void
    {
        Sanctum::actingAs($this->owner());
        $member = Member::factory()->at($this->bole)->create();

        $this->post("/api/v1/members/{$member->ulid}/photo", ['photo' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('photo');
        $this->post("/api/v1/members/{$member->ulid}/photo", ['photo' => UploadedFile::fake()->image('tiny.jpg', 50, 50)], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('photo');
    }
}
