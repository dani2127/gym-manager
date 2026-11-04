<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningHoursController extends Controller
{
    /** Replaces the whole week. Each day is closed, or open with opens_at < closes_at. */
    public function update(Request $request, Branch $branch): BranchResource
    {
        $this->authorize('update', $branch);

        $data = $request->validate([
            'days' => ['required', 'array', 'size:7'],
            'days.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.is_closed' => ['required', 'boolean'],
            'days.*.opens_at' => ['exclude_if:days.*.is_closed,true', 'required', 'date_format:H:i'],
            'days.*.closes_at' => ['exclude_if:days.*.is_closed,true', 'required', 'date_format:H:i'],
        ]);

        foreach ($data['days'] as $index => $day) {
            if (! $day['is_closed'] && $day['opens_at'] >= $day['closes_at']) {
                throw ValidationException::withMessages(["days.$index.closes_at" => __('validation.after', ['attribute' => 'closes_at', 'date' => 'opens_at'])]);
            }
        }

        DB::transaction(function () use ($branch, $data) {
            foreach ($data['days'] as $day) {
                $branch->openingHours()->updateOrCreate(
                    ['weekday' => $day['weekday']],
                    [
                        'is_closed' => $day['is_closed'],
                        'opens_at' => $day['is_closed'] ? null : $day['opens_at'],
                        'closes_at' => $day['is_closed'] ? null : $day['closes_at'],
                    ],
                );
            }
        });

        Audit::record('branch.hours_updated', $branch, ['days' => $data['days']]);

        return new BranchResource($branch->load('openingHours'));
    }
}
