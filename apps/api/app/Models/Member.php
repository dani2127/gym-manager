<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id', 'home_branch_id', 'member_no', 'first_name', 'last_name', 'gender', 'date_of_birth',
    'phone', 'email', 'emergency_name', 'emergency_phone', 'status', 'joined_at', 'notes', 'created_by',
])]
#[Hidden(['qr_secret'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use BelongsToOrganization, HasFactory, HasPublicUlid, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            $member->qr_secret ??= Str::random(40);
            $member->joined_at ??= today();
        });
    }

    protected function casts(): array
    {
        return [
            'member_no' => 'integer',
            'date_of_birth' => 'date:Y-m-d',
            'joined_at' => 'date:Y-m-d',
            'qr_secret' => 'encrypted',
        ];
    }

    /** "0427": the bib number. Four digits until a gym passes 9,999 members. */
    protected function code(): Attribute
    {
        return Attribute::get(fn () => str_pad((string) $this->member_no, 4, '0', STR_PAD_LEFT));
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    /** @return BelongsTo<Branch, $this> */
    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'home_branch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return BelongsToMany<MemberGroup, $this> */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(MemberGroup::class, 'member_group_members')->withTimestamps();
    }

    public function group(): ?MemberGroup
    {
        return $this->groups->first();
    }

    /**
     * Desk search: each word must prefix the first or last name; digits also
     * match the member number exactly or anywhere in the phone number.
     *
     * @param  Builder<Member>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', $term);

        $query->where(function (Builder $q) use ($term, $digits) {
            $q->where(function (Builder $names) use ($term) {
                foreach (preg_split('/\s+/u', $term) as $word) {
                    $names->where(fn (Builder $w) => $w
                        ->where('first_name', 'like', $word.'%')
                        ->orWhere('last_name', 'like', $word.'%'));
                }
            });

            if ($digits !== '' && $digits === preg_replace('/[\s+#-]/', '', $term)) {
                $q->orWhere('member_no', (int) $digits);
                // Local numbers start with 0; stored numbers are +251…. Five
                // significant digits keep "0003" from matching every phone with a 3.
                $significant = ltrim($digits, '0');
                if (strlen($significant) >= 5) {
                    $q->orWhere('phone', 'like', '%'.$significant.'%');
                }
            }
        });
    }
}
