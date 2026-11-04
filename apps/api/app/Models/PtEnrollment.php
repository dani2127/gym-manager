<?php

namespace App\Models;

use App\Http\Resources\CheckInResource;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A member's PT package with one trainer: sessions left and until when. */
#[Fillable([
    'organization_id', 'branch_id', 'member_id', 'trainer_id', 'pt_package_id', 'invoice_id', 'package_snapshot',
    'sessions_total', 'sessions_remaining', 'starts_on', 'ends_on', 'status', 'sold_by',
])]
class PtEnrollment extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'package_snapshot' => 'array', 'sessions_total' => 'integer', 'sessions_remaining' => 'integer',
            'starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return HasMany<PtSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(PtSession::class)->orderBy('starts_at');
    }

    public function packageName(?string $locale = null): string
    {
        $names = $this->package_snapshot['name'] ?? [];
        $locale ??= app()->getLocale();

        return (string) (($names[$locale] ?? null) ?: ($names['en'] ?? ''));
    }

    /** Sessions already on the calendar that haven't happened yet. */
    public function scheduledCount(): int
    {
        return $this->sessions()->where('status', 'scheduled')->count();
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->ulid,
            'package' => $this->packageName(),
            'trainer' => $this->trainer ? ['id' => $this->trainer->ulid, 'name' => $this->trainer->name] : null,
            'member' => $this->relationLoaded('member') && $this->member ? CheckInResource::member($this->member) : null,
            'branch' => $this->relationLoaded('branch') && $this->branch ? ['id' => $this->branch->ulid, 'name' => $this->branch->name] : null,
            'sessions_total' => $this->sessions_total,
            'sessions_remaining' => $this->sessions_remaining,
            'sessions_scheduled' => $this->scheduledCount(),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status,
            'invoice' => $this->relationLoaded('invoice') && $this->invoice ? ['id' => $this->invoice->ulid, 'number' => $this->invoice->number, 'balance_cents' => $this->invoice->balanceCents()] : null,
        ];
    }
}
