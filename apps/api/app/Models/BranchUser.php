<?php

namespace App\Models;

use App\Enums\BranchRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BranchUser extends Pivot
{
    protected $table = 'branch_user';

    protected function casts(): array
    {
        return ['role' => BranchRole::class];
    }
}
