<?php

namespace App\Policies;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Models\User;

/** Staff management. Owners manage all staff; managers manage receptionists and trainers in their branches. */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isOwner() || $this->isManager($actor);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->is($target) || $this->manages($actor, $target);
    }

    public function create(User $actor): bool
    {
        return $actor->isOwner() || $this->isManager($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return ! $actor->is($target) && $this->manages($actor, $target);
    }

    public function disable(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    private function manages(User $actor, User $target): bool
    {
        if ($target->organization_id !== $actor->organization_id || $target->account_type !== AccountType::Staff) {
            return false;
        }

        if ($actor->isOwner()) {
            return true;
        }

        $targetRoles = $target->branches()->get();

        return $targetRoles->isNotEmpty()
            && $targetRoles->every(fn ($branch) => $branch->pivot->role !== BranchRole::Manager)
            && $targetRoles->contains(fn ($branch) => $actor->managesBranch($branch));
    }

    private function isManager(User $actor): bool
    {
        return $actor->branches()->wherePivot('role', BranchRole::Manager->value)->exists();
    }
}
