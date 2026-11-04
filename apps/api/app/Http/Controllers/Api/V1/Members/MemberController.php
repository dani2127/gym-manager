<?php

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Branch;
use App\Models\Member;
use App\Policies\MemberPolicy;
use App\Rules\EthiopianPhone;
use App\Support\Audit;
use App\Support\MemberPhotos;
use App\Support\Phone;
use App\Support\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Member::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:26'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'banned'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $branchId = isset($filters['branch']) ? Branch::where('ulid', $filters['branch'])->value('id') : null;

        $members = Member::query()
            ->with('homeBranch')
            ->search($filters['search'] ?? null)
            ->when($branchId, fn ($q) => $q->where('home_branch_id', $branchId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            // Searches rank exact number matches first; plain lists are newest first.
            ->when(
                filled($filters['search'] ?? null),
                fn ($q) => $q->orderByRaw('member_no = ? desc', [(int) preg_replace('/\D/', '', $filters['search'])])->orderBy('first_name')->orderBy('last_name'),
                fn ($q) => $q->latest('id'),
            )
            ->paginate($filters['per_page'] ?? 25);

        return MemberResource::collection($members);
    }

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', Member::class);

        $data = $this->validated($request);
        $branch = Branch::where('ulid', $data['home_branch'])->firstOrFail();

        abort_unless(app(MemberPolicy::class)->registersAt($request->user(), $branch), 403, __('members.branch_not_allowed'));

        $this->guardDuplicatePhone($data);

        $member = DB::transaction(function () use ($data, $branch, $tenant, $request) {
            return Member::create([
                ...collect($data)->except(['home_branch', 'confirm_duplicate'])->all(),
                'home_branch_id' => $branch->id,
                'member_no' => $tenant->organization()->takeMemberNumber(),
                'created_by' => $request->user()->id,
            ]);
        });

        Audit::record('member.created', $member, ['member_no' => $member->code, 'name' => $member->full_name]);

        return (new MemberResource($member->load('homeBranch', 'groups.members')))->response()->setStatusCode(201);
    }

    public function show(Member $member): MemberResource
    {
        $this->authorize('view', $member);

        return new MemberResource($member->load('homeBranch', 'groups.members', 'groups.primaryMember'));
    }

    public function update(Request $request, Member $member): MemberResource
    {
        $this->authorize('update', $member);

        $data = $this->validated($request, $member);

        if (isset($data['home_branch'])) {
            $branch = Branch::where('ulid', $data['home_branch'])->firstOrFail();
            abort_unless(app(MemberPolicy::class)->registersAt($request->user(), $branch), 403, __('members.branch_not_allowed'));
            $data['home_branch_id'] = $branch->id;
        }

        // Only a changed number is re-checked; a shared phone confirmed at
        // registration must not block every later edit.
        if (array_key_exists('phone', $data) && $data['phone'] !== $member->phone) {
            $this->guardDuplicatePhone($data, $member);
        }

        $member->fill(collect($data)->except(['home_branch', 'confirm_duplicate'])->all());
        $changes = $member->getDirty();
        $member->save();

        if ($changes) {
            Audit::record('member.updated', $member, $changes);
        }

        return new MemberResource($member->load('homeBranch', 'groups.members', 'groups.primaryMember'));
    }

    public function destroy(Member $member, MemberPhotos $photos): JsonResponse
    {
        $this->authorize('delete', $member);

        DB::transaction(function () use ($member, $photos) {
            $photos->delete($member);
            $member->forceFill(['photo_path' => null])->save();
            $member->groups()->detach();
            $member->delete();
        });

        Audit::record('member.deleted', $member, ['member_no' => $member->code, 'name' => $member->full_name]);

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Member $member = null): array
    {
        $required = $member ? 'sometimes' : 'required';

        $data = $request->validate([
            'first_name' => [$required, 'string', 'max:60'],
            'last_name' => [$required, 'string', 'max:60'],
            'home_branch' => [$required, 'string', 'max:26'],
            'phone' => ['sometimes', 'nullable', 'string', new EthiopianPhone],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today', 'after:1900-01-01'],
            'emergency_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'emergency_phone' => ['sometimes', 'nullable', 'string', new EthiopianPhone],
            'status' => [$member ? 'sometimes' : 'prohibited', Rule::in(['active', 'inactive', 'banned'])],
            'joined_at' => ['sometimes', 'date', 'before_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ]);

        foreach (['phone', 'emergency_phone'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = Phone::normalize($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Family members often share one phone, so a repeated number is a warning
     * the desk can confirm, not a hard rule.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardDuplicatePhone(array $data, ?Member $ignore = null): void
    {
        if (empty($data['phone']) || ! empty($data['confirm_duplicate'])) {
            return;
        }

        $existing = Member::where('phone', $data['phone'])
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->first();

        if ($existing) {
            // Its own key so clients can offer "register anyway" instead of a format error.
            throw ValidationException::withMessages([
                'phone_duplicate' => __('members.duplicate_phone', ['name' => $existing->full_name, 'number' => $existing->code]),
            ]);
        }
    }
}
