<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->canAccessBranch($branch);
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    /** Name, contact details and opening hours. */
    public function update(User $user, Branch $branch): bool
    {
        return $user->managesBranch($branch);
    }

    /** Status changes and tax/feature overrides affect money, so owners only. */
    public function administer(User $user, Branch $branch): bool
    {
        return $user->isOwner() && $branch->organization_id === $user->organization_id;
    }
}
