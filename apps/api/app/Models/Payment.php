<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'branch_id', 'invoice_id', 'member_id', 'method', 'amount_cents', 'status', 'reference',
    'proof_path', 'received_by', 'verified_by', 'verified_at', 'rejection_reason',
])]
class Payment extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    /** Methods the desk records directly; transfers and Chapa arrive in M6 and M10. */
    public const DESK_METHODS = ['cash', 'pos_card'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'verified_at' => 'datetime'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
