<?php

namespace App\Models;

use App\Http\Resources\CheckInResource;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'class_session_id', 'member_id', 'membership_id', 'status', 'waitlist_position', 'booked_by',
    'booked_at', 'cancelled_at', 'late_cancel',
])]
class ClassBooking extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    /** Bookings that use a class credit. */
    public const USES_CREDIT = ['booked', 'attended', 'no_show'];

    protected function casts(): array
    {
        return ['booked_at' => 'datetime', 'cancelled_at' => 'datetime', 'late_cancel' => 'boolean', 'waitlist_position' => 'integer'];
    }

    /** @return BelongsTo<ClassSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status,
            'waitlist_position' => $this->waitlist_position,
            'late_cancel' => $this->late_cancel,
            'booked_at' => $this->booked_at->toIso8601String(),
            'member' => $this->relationLoaded('member') && $this->member ? CheckInResource::member($this->member) : null,
            'session' => $this->relationLoaded('session') && $this->session ? $this->session->present() : null,
        ];
    }
}
