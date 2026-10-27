<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id', 'branch_id', 'member_id', 'number', 'status', 'subtotal_cents', 'discount_cents', 'tax_cents',
    'total_cents', 'paid_cents', 'tax_name', 'tax_rate', 'tax_inclusive', 'currency', 'issued_at', 'created_by',
    'voided_at', 'voided_by', 'void_reason',
])]
class Invoice extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_cents' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function balanceCents(): int
    {
        return $this->status === 'void' ? 0 : max(0, $this->total_cents - $this->paid_cents);
    }

    /** Taxable amount: the total without tax, whichever way prices are quoted. */
    public function netCents(): int
    {
        return $this->total_cents - $this->tax_cents;
    }
}
