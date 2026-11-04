<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'branch_id', 'name', 'capacity'])]
class Room extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return ['id' => $this->ulid, 'name' => $this->name, 'capacity' => $this->capacity];
    }
}
