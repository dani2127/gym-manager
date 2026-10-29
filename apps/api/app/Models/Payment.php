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

    /** Methods the desk records directly (Chapa arrives in M10). */
    public const DESK_METHODS = ['cash', 'pos_card', 'telebirr', 'bank_transfer'];

    /** Methods that need a reference number (FT, Telebirr transaction or slip). */
    public const REFERENCE_REQUIRED = ['telebirr', 'bank_transfer'];

    /** What members can send from the app for the desk to check. */
    public const TRANSFER_METHODS = ['telebirr', 'bank_transfer'];

    /** References are compared without spaces and case: "ft 23.." and "FT23.." are the same transfer. */
    public static function normalizeReference(?string $reference): ?string
    {
        $clean = strtoupper(preg_replace('/\s+/', '', (string) $reference));

        return $clean === '' ? null : $clean;
    }

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'verified_at' => 'datetime'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
