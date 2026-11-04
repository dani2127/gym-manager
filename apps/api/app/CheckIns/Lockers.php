<?php

namespace App\CheckIns;

use App\Events\CheckInChanged;
use App\Models\Branch;
use App\Models\CheckIn;
use App\Models\Locker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lockers handed out at the desk. A locker is in use while it is on an
 * open visit; checking out (or returning the key) frees it.
 */
class Lockers
{
    /** @return Collection<int, int> locker id => check-in id for lockers in use at the branch */
    public function inUse(Branch $branch): Collection
    {
        return CheckIn::where('branch_id', $branch->id)
            ->inside()
            ->whereNotNull('locker_id')
            ->pluck('id', 'locker_id');
    }

    /**
     * Gives the visit a locker: the one asked for, or the first free one in
     * the member's zone (then unisex). Lockers are numbered naturally: 2 before 10.
     */
    public function assign(CheckIn $checkIn, ?Locker $wanted = null): CheckIn
    {
        if (! $checkIn->isEntry() || $checkIn->checked_out_at !== null) {
            throw ValidationException::withMessages(['locker' => __('checkins.locker_not_inside')]);
        }

        DB::transaction(function () use ($checkIn, $wanted) {
            $branch = $checkIn->branch;
            // Lock the branch's lockers so two desks can't hand out the same one.
            $lockers = Locker::where('branch_id', $branch->id)->lockForUpdate()->get();
            $taken = $this->inUse($branch)->except([$checkIn->locker_id]);

            if ($wanted) {
                abort_unless($wanted->branch_id === $branch->id, 404);
                if ($wanted->status !== 'available' || $taken->has($wanted->id)) {
                    throw ValidationException::withMessages(['locker' => __('checkins.locker_taken', ['number' => $wanted->number])]);
                }
                $locker = $wanted;
            } else {
                $zone = in_array($checkIn->member->gender, ['male', 'female'], true) ? $checkIn->member->gender : null;
                $free = $lockers
                    ->filter(fn (Locker $l) => $l->status === 'available' && ! $taken->has($l->id))
                    ->sortBy(fn (Locker $l) => sprintf('%010s', $l->number));
                $locker = ($zone ? $free->firstWhere('zone', $zone) : null) ?? $free->firstWhere('zone', 'unisex');

                if (! $locker) {
                    throw ValidationException::withMessages(['locker' => __('checkins.no_free_locker')]);
                }
            }

            $checkIn->forceFill(['locker_id' => $locker->id])->save();
        });

        return $this->changed($checkIn);
    }

    public function release(CheckIn $checkIn): CheckIn
    {
        $checkIn->forceFill(['locker_id' => null])->save();

        return $this->changed($checkIn);
    }

    private function changed(CheckIn $checkIn): CheckIn
    {
        $checkIn->load('member.homeBranch', 'membership', 'branch', 'locker');
        CheckInChanged::dispatch($checkIn, 'locker');

        return $checkIn;
    }
}
