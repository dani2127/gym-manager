<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'kind', 'bank_name', 'account_name', 'account_number', 'is_active', 'sort'])]
class PaymentAccount extends Model
{
    use BelongsToOrganization, HasPublicUlid;

    protected $attributes = ['is_active' => true, 'sort' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->ulid,
            'kind' => $this->kind,
            'bank_name' => $this->bank_name,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'is_active' => $this->is_active,
        ];
    }
}
