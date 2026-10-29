<?php

namespace App\Policies;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Member;
use App\Models\User;

/**
 * Any staff member can find and view members (the desk serves members from
 * every branch). Registering and editing needs the owner, or a manager or
 * receptionist of the member's home branch. Deleting is owners and managers.
 */
class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Member $member): bool
    {
        return $user->isStaff() && $member->organization_id === $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->isOwner()
            || $user->branches()->wherePivotIn('role', [BranchRole::Manager->value, BranchRole::Receptionist->value])->exists();
    }

    public function update(User $user, Member $member): bool
    {
        return $this->registersAt($user, $member->homeBranch);
    }

    public function delete(User $user, Member $member): bool
    {
        return $user->managesBranch($member->homeBranch);
    }

    /** Whether the user may register or edit members whose home is this branch. */
    public function registersAt(User $user, Branch $branch): bool
    {
        if ($branch->organization_id !== $user->organization_id) {
            return false;
        }

        return $user->isOwner() || in_array($user->roleIn($branch), [BranchRole::Manager, BranchRole::Receptionist], true);
    }
}
