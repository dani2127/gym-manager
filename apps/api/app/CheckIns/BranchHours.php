<?php

namespace App\CheckIns;

use App\Models\Branch;
use App\Models\BranchClosure;
use App\Models\OpeningHour;
use Illuminate\Support\Carbon;

/** Whether a branch is open at a moment, from its weekly hours and dated closures. */
class BranchHours
{
    public function isOpen(Branch $branch, Carbon $moment): bool
    {
        $local = $moment->copy()->setTimezone($branch->organization->timezone ?: config('gym.timezone'));
        $time = $local->format('H:i');

        $closure = BranchClosure::where('branch_id', $branch->id)->whereDate('date', $local->toDateString())->first();
        if ($closure) {
            return $closure->opens_at !== null && $this->within($time, $closure->opens_at, $closure->closes_at);
        }

        $hours = OpeningHour::where('branch_id', $branch->id)->where('weekday', $local->dayOfWeek)->first();
        if (! $hours) {
            return true;
        }

        return ! $hours->is_closed && $this->within($time, $hours->opens_at, $hours->closes_at);
    }

    /** Closing time on the given local date, as "HH:MM", or null when closed or unknown. */
    public function closingTime(Branch $branch, Carbon $localDate): ?string
    {
        $closure = BranchClosure::where('branch_id', $branch->id)->whereDate('date', $localDate->toDateString())->first();
        if ($closure) {
            return $closure->closes_at ? substr($closure->closes_at, 0, 5) : null;
        }

        $hours = OpeningHour::where('branch_id', $branch->id)->where('weekday', $localDate->dayOfWeek)->first();

        return $hours && ! $hours->is_closed && $hours->closes_at ? substr($hours->closes_at, 0, 5) : null;
    }

    private function within(string $time, ?string $opens, ?string $closes): bool
    {
        if ($opens === null || $closes === null) {
            return true;
        }
        $opens = substr($opens, 0, 5);
        $closes = substr($closes, 0, 5);

        // Hours that run past midnight (e.g. 18:00–02:00).
        return $closes > $opens ? ($time >= $opens && $time < $closes) : ($time >= $opens || $time < $closes);
    }
}
