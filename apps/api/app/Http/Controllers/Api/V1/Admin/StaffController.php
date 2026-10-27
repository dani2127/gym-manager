<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Branch;
use App\Models\User;
use App\Rules\EthiopianPhone;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Staff accounts and their branch roles. Owners manage everyone; managers
 * manage receptionists and trainers, and only in branches they manage.
 */
class StaffController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();

        $staff = User::query()
            ->whereIn('account_type', [AccountType::Owner, AccountType::Staff])
            ->when(! $actor->isOwner(), fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereIn(
                'branches.id',
                $actor->branches()->wherePivot('role', BranchRole::Manager->value)->select('branches.id'),
            )))
            ->with('branches')
            ->orderBy('name')
            ->get();

        return StaffResource::collection($staff);
    }

    public function store(Request $request): StaffResource
    {
        $this->authorize('create', User::class);

        $actor = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new EthiopianPhone],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->where('organization_id', $actor->organization_id)],
            'password' => ['required', Password::min(8)],
            ...$this->assignmentRules(required: true),
        ]);

        $phone = $this->uniquePhone($data['phone'], $actor);
        $assignments = $this->resolveAssignments($actor, $data['assignments']);

        $user = DB::transaction(function () use ($data, $phone, $assignments) {
            $user = User::create([
                'account_type' => AccountType::Staff,
                'name' => $data['name'],
                'phone' => $phone,
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
            ]);
            $user->branches()->sync($assignments);

            return $user;
        });

        Audit::record('staff.created', $user, ['name' => $user->name, 'assignments' => $data['assignments']]);

        return new StaffResource($user->load('branches'));
    }

    public function show(User $staff): StaffResource
    {
        $this->authorize('view', $staff);

        return new StaffResource($staff->load('branches'));
    }

    public function update(Request $request, User $staff): StaffResource
    {
        $this->authorize('update', $staff);

        $actor = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'string', new EthiopianPhone],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users')->where('organization_id', $actor->organization_id)->ignore($staff->id)],
            'password' => ['sometimes', Password::min(8)],
            'status' => ['sometimes', 'in:active,disabled'],
            ...$this->assignmentRules(required: false),
        ]);

        if (isset($data['phone'])) {
            $data['phone'] = $this->uniquePhone($data['phone'], $actor, $staff);
        }

        $assignments = isset($data['assignments']) ? $this->resolveAssignments($actor, $data['assignments']) : null;

        DB::transaction(function () use ($staff, $data, $assignments, $actor) {
            $staff->fill(collect($data)->except('assignments')->all())->save();

            if ($assignments !== null) {
                // Managers only rewrite the branches they manage; other assignments stay.
                $kept = $actor->isOwner() ? [] : $staff->branches
                    ->reject(fn (Branch $branch) => $actor->managesBranch($branch))
                    ->mapWithKeys(fn (Branch $branch) => [$branch->id => ['role' => $branch->pivot->role->value]])
                    ->all();

                $staff->branches()->sync($assignments + $kept);
            }

            if (($data['status'] ?? null) === 'disabled' || isset($data['password'])) {
                $staff->tokens()->delete();
                DB::table('sessions')->where('user_id', $staff->id)->delete();
            }
        });

        Audit::record('staff.updated', $staff, collect($data)->except('password')->put('password_changed', isset($data['password']))->all());

        return new StaffResource($staff->load('branches'));
    }

    /** @return array<string, mixed> */
    private function assignmentRules(bool $required): array
    {
        return [
            'assignments' => [$required ? 'required' : 'sometimes', 'array', 'min:1'],
            'assignments.*.branch' => ['required', 'string', 'distinct'],
            'assignments.*.role' => ['required', Rule::enum(BranchRole::class)],
        ];
    }

    /**
     * Maps [{branch: ulid, role}] to sync() input, enforcing that the actor
     * manages each branch and that only owners hand out the manager role.
     *
     * @param  list<array{branch: string, role: string}>  $assignments
     * @return array<int, array{role: string}>
     */
    private function resolveAssignments(User $actor, array $assignments): array
    {
        /** @var Collection<string, Branch> $branches */
        $branches = Branch::whereIn('ulid', array_column($assignments, 'branch'))->get()->keyBy('ulid');
        $resolved = [];

        foreach ($assignments as $index => $assignment) {
            $branch = $branches->get($assignment['branch']);

            if (! $branch || ! $actor->managesBranch($branch)) {
                throw ValidationException::withMessages(["assignments.$index.branch" => __('auth.branch_not_manageable')]);
            }

            if ($assignment['role'] === BranchRole::Manager->value && ! $actor->isOwner()) {
                throw ValidationException::withMessages(["assignments.$index.role" => __('auth.role_not_assignable')]);
            }

            $resolved[$branch->id] = ['role' => $assignment['role']];
        }

        return $resolved;
    }

    private function uniquePhone(string $input, User $actor, ?User $ignore = null): string
    {
        $phone = Phone::normalize($input);

        $taken = User::withoutGlobalScopes()
            ->where('organization_id', $actor->organization_id)
            ->where('phone', $phone)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['phone' => __('validation.unique', ['attribute' => 'phone'])]);
        }

        return $phone;
    }
}
