<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['organization_id', 'account_type', 'name', 'phone', 'email', 'password', 'locale', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use BelongsToOrganization, HasApiTokens, HasFactory, HasPublicUlid, Notifiable;

    /** Mirrors the column defaults so freshly created models are complete. */
    protected $attributes = [
        'account_type' => 'member',
        'locale' => 'en',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsToMany<Branch, $this, BranchUser> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->using(BranchUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function isOwner(): bool
    {
        return $this->account_type === AccountType::Owner;
    }

    public function isStaff(): bool
    {
        return in_array($this->account_type, [AccountType::Owner, AccountType::Staff], true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Branches this user may work in: every branch for owners, assigned
     * branches for staff.
     *
     * @return Builder<Branch>
     */
    public function accessibleBranches(): Builder
    {
        $query = Branch::query()->where('organization_id', $this->organization_id);

        return $this->isOwner()
            ? $query
            : $query->whereIn('id', $this->branches()->select('branches.id'));
    }

    public function roleIn(Branch $branch): ?BranchRole
    {
        if ($branch->organization_id !== $this->organization_id) {
            return null;
        }

        $role = $this->branches()->whereKey($branch->id)->first()?->pivot->role;

        return $role instanceof BranchRole ? $role : null;
    }

    public function canAccessBranch(Branch $branch): bool
    {
        return $branch->organization_id === $this->organization_id
            && ($this->isOwner() || $this->roleIn($branch) !== null);
    }

    /** Owners manage every branch; managers manage the branches they are assigned to. */
    public function managesBranch(Branch $branch): bool
    {
        return $branch->organization_id === $this->organization_id
            && ($this->isOwner() || $this->roleIn($branch) === BranchRole::Manager);
    }

    public function telegramChatId(): ?string
    {
        return $this->notificationPreferences()
            ->where('channel', 'telegram')
            ->whereNotNull('verified_at')
            ->whereNull('opted_out_at')
            ->value('destination');
    }

    public function routeNotificationForTelegram(): ?string
    {
        return $this->telegramChatId();
    }

    public function preferredLocale(): string
    {
        return $this->locale ?? config('app.locale');
    }
}
