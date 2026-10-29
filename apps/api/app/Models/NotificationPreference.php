<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'user_id', 'channel', 'destination', 'is_preferred', 'verified_at', 'opted_out_at'])]
class NotificationPreference extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'is_preferred' => 'boolean',
            'verified_at' => 'datetime',
            'opted_out_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
