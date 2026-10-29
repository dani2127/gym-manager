<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['branch_id', 'year', 'next_number'])]
#[WithoutTimestamps]
class InvoiceSequence extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    /**
     * Reserves the next invoice number for a branch and year. The row lock
     * holds until the surrounding transaction commits, so numbers are
     * gap-free: a failed sale rolls its number back with it.
     */
    public static function take(Branch $branch, int $year): int
    {
        $query = static::query()->where('branch_id', $branch->id)->where('year', $year);

        $row = (clone $query)->lockForUpdate()->first();
        if (! $row) {
            static::query()->insertOrIgnore(['branch_id' => $branch->id, 'year' => $year, 'next_number' => 1]);
            $row = (clone $query)->lockForUpdate()->firstOrFail();
        }

        $number = $row->next_number;
        (clone $query)->update(['next_number' => $number + 1]);

        return $number;
    }
}
