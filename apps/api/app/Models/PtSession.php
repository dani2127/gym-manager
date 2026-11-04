<?php

namespace App\Models;

use App\Http\Resources\CheckInResource;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'pt_enrollment_id', 'trainer_id', 'member_id', 'branch_id', 'starts_at', 'ends_at', 'status', 'trainer_notes', 'created_by'])]
class PtSession extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    protected $attributes = ['status' => 'scheduled'];

    protected $hidden = ['trainer_notes'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return BelongsTo<PtEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(PtEnrollment::class, 'pt_enrollment_id');
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

    /**
     * @param  bool  $withNotes  only for the session's trainer and the owner
     * @return array<string, mixed>
     */
    public function present(bool $withNotes = false): array
    {
        return [
            'id' => $this->ulid,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'status' => $this->status,
            'trainer' => $this->trainer ? ['id' => $this->trainer->ulid, 'name' => $this->trainer->name] : null,
            'member' => $this->relationLoaded('member') && $this->member ? CheckInResource::member($this->member) : null,
            'branch' => $this->relationLoaded('branch') && $this->branch ? ['id' => $this->branch->ulid, 'name' => $this->branch->name] : null,
            'enrollment' => $this->relationLoaded('enrollment') && $this->enrollment ? ['id' => $this->enrollment->ulid, 'package' => $this->enrollment->packageName(), 'sessions_remaining' => $this->enrollment->sessions_remaining] : null,
            ...($withNotes ? ['trainer_notes' => $this->trainer_notes] : []),
        ];
    }
}
