<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One dated class people book, made from the timetable or added once. Times are stored in UTC. */
#[Fillable([
    'organization_id', 'branch_id', 'class_type_id', 'schedule_id', 'trainer_id', 'room_id', 'starts_at', 'ends_at',
    'capacity', 'booked_count', 'waitlist_count', 'status', 'cancel_reason',
])]
class ClassSession extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    protected $attributes = ['status' => 'scheduled', 'booked_count' => 0, 'waitlist_count' => 0];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'capacity' => 'integer',
            'booked_count' => 'integer', 'waitlist_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<ClassType, $this> */
    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return HasMany<ClassBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(ClassBooking::class);
    }

    public function spotsLeft(): int
    {
        return max(0, $this->capacity - $this->booked_count);
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->ulid,
            'class_type' => $this->classType?->present(),
            'trainer' => $this->trainer ? ['id' => $this->trainer->ulid, 'name' => $this->trainer->name] : null,
            'room' => $this->room?->present(),
            'branch' => $this->relationLoaded('branch') && $this->branch ? ['id' => $this->branch->ulid, 'name' => $this->branch->name] : null,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'capacity' => $this->capacity,
            'booked_count' => $this->booked_count,
            'waitlist_count' => $this->waitlist_count,
            'spots_left' => $this->spotsLeft(),
            'status' => $this->status,
            'cancel_reason' => $this->cancel_reason,
            'from_timetable' => $this->schedule_id !== null,
        ];
    }
}
