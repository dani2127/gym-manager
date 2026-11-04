<?php

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Couples, families and corporate groups, used for group discounts in M3. */
class MemberGroupController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Member::class);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(MemberGroup::SIZES))],
            'name' => ['required', 'string', 'max:120'],
            'members' => ['required', 'array'],
            'members.*' => ['required', 'string', 'distinct'],
            'primary_member' => ['nullable', 'string'],
        ]);

        $members = $this->resolveMembers($request, $data['members'], $data['type']);

        $group = DB::transaction(function () use ($data, $members) {
            $group = MemberGroup::create([
                'type' => $data['type'],
                'name' => $data['name'],
                'primary_member_id' => $members->firstWhere('ulid', $data['primary_member'] ?? null)?->id ?? $members->first()->id,
            ]);
            $group->members()->sync($members->pluck('id'));

            return $group;
        });

        Audit::record('member_group.created', $group, ['type' => $group->type, 'members' => $members->pluck('ulid')]);

        return response()->json(['data' => $this->present($group)], 201);
    }

    public function update(Request $request, MemberGroup $memberGroup): JsonResponse
    {
        $this->authorize('create', Member::class);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'members' => ['sometimes', 'array'],
            'members.*' => ['required', 'string', 'distinct'],
            'primary_member' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $data, $memberGroup) {
            if (isset($data['members'])) {
                $members = $this->resolveMembers($request, $data['members'], $memberGroup->type, $memberGroup);
                $memberGroup->members()->sync($members->pluck('id'));
                $primary = $members->firstWhere('ulid', $data['primary_member'] ?? null) ?? $members->firstWhere('id', $memberGroup->primary_member_id) ?? $members->first();
                $memberGroup->primary_member_id = $primary->id;
            }
            if (isset($data['name'])) {
                $memberGroup->name = $data['name'];
            }
            $memberGroup->save();
        });

        Audit::record('member_group.updated', $memberGroup, collect($data)->only('name', 'members')->all());

        return response()->json(['data' => $this->present($memberGroup->refresh())]);
    }

    public function destroy(MemberGroup $memberGroup): JsonResponse
    {
        $this->authorize('create', Member::class);

        $memberGroup->delete();
        Audit::record('member_group.deleted', $memberGroup, ['name' => $memberGroup->name]);

        return response()->json(status: 204);
    }

    /**
     * @param  list<string>  $ulids
     * @return Collection<int, Member>
     */
    private function resolveMembers(Request $request, array $ulids, string $type, ?MemberGroup $group = null): Collection
    {
        [$min, $max] = MemberGroup::SIZES[$type];
        if (count($ulids) < $min || count($ulids) > $max) {
            throw ValidationException::withMessages(['members' => __('members.group_size', ['min' => $min, 'max' => $max])]);
        }

        $members = Member::with('groups', 'homeBranch')->whereIn('ulid', $ulids)->get();
        if ($members->count() !== count($ulids)) {
            throw ValidationException::withMessages(['members' => __('members.group_unknown_member')]);
        }

        foreach ($members as $member) {
            $this->authorize('update', $member);
            $current = $member->group();
            if ($current && (! $group || $current->id !== $group->id)) {
                throw ValidationException::withMessages(['members' => __('members.group_taken', ['name' => $member->full_name, 'group' => $current->name])]);
            }
        }

        return $members;
    }

    /** @return array<string, mixed> */
    private function present(MemberGroup $group): array
    {
        $group->load('members', 'primaryMember');

        return [
            'id' => $group->ulid,
            'type' => $group->type,
            'name' => $group->name,
            'primary_member_id' => $group->primaryMember?->ulid,
            'members' => $group->members->map(fn (Member $m) => ['id' => $m->ulid, 'full_name' => $m->full_name, 'member_no' => $m->code]),
        ];
    }
}
