<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A weekly timetable slot: "Spinning, Tuesdays 18:00, Studio A, Dawit". */
#[Fillable([
    'organization_id', 'branch_id', 'class_type_id', 'trainer_id', 'room_id', 'weekday', 'start_time', 'duration_min',
    'capacity', 'valid_from', 'valid_until', 'is_active',
])]
class ClassSchedule extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer', 'duration_min' => 'integer', 'capacity' => 'integer', 'is_active' => 'boolean',
            'valid_from' => 'date:Y-m-d', 'valid_until' => 'date:Y-m-d',
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

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->ulid,
            'class_type' => $this->classType?->present(),
            'trainer' => $this->trainer ? ['id' => $this->trainer->ulid, 'name' => $this->trainer->name] : null,
            'room' => $this->room?->present(),
            'weekday' => $this->weekday,
            'start_time' => substr((string) $this->start_time, 0, 5),
            'duration_min' => $this->duration_min,
            'capacity' => $this->capacity,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'is_active' => $this->is_active,
        ];
    }
}
