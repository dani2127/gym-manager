<?php

namespace App\Classes;

use App\Models\ClassSchedule;
use App\Models\ClassSession;
use Illuminate\Support\Carbon;

/**
 * Turns the weekly timetable into dated sessions people can book, a few weeks
 * ahead. Safe to run any number of times: each slot makes one session per date.
 */
class Timetable
{
    public const DAYS_AHEAD = 28;

    /** @return int sessions created */
    public function generate(?ClassSchedule $only = null, int $days = self::DAYS_AHEAD): int
    {
        $schedules = $only ? collect([$only]) : ClassSchedule::withoutGlobalScopes()->where('is_active', true)->with('branch.organization')->get();
        $created = 0;

        foreach ($schedules as $schedule) {
            if (! $schedule->is_active) {
                continue;
            }
            $tz = $schedule->branch->organization->timezone ?: config('gym.timezone');
            $today = Carbon::now($tz)->startOfDay();

            for ($d = 0; $d <= $days; $d++) {
                $date = $today->copy()->addDays($d);
                if ($date->dayOfWeek !== $schedule->weekday
                    || $date->lt($schedule->valid_from->copy()->startOfDay())
                    || ($schedule->valid_until && $date->gt($schedule->valid_until))) {
                    continue;
                }
                $startsLocal = Carbon::parse($date->toDateString().' '.$schedule->start_time, $tz);
                if ($startsLocal->isPast()) {
                    continue;
                }
                $startsUtc = $startsLocal->copy()->setTimezone(config('app.timezone'));

                // One session per slot per day, even if a booked one kept an older time.
                $exists = ClassSession::withoutGlobalScopes()->where('schedule_id', $schedule->id)
                    ->whereBetween('starts_at', [$date->copy()->setTimezone(config('app.timezone')), $date->copy()->endOfDay()->setTimezone(config('app.timezone'))])
                    ->exists();
                if ($exists) {
                    continue;
                }

                ClassSession::withoutGlobalScopes()->create([
                    'organization_id' => $schedule->organization_id,
                    'branch_id' => $schedule->branch_id,
                    'class_type_id' => $schedule->class_type_id,
                    'schedule_id' => $schedule->id,
                    'trainer_id' => $schedule->trainer_id,
                    'room_id' => $schedule->room_id,
                    'starts_at' => $startsUtc,
                    'ends_at' => $startsUtc->copy()->addMinutes($schedule->duration_min),
                    'capacity' => $schedule->capacity,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * After the slot changes: future sessions nobody booked are remade from it;
     * booked ones keep their time but pick up the new trainer and room.
     */
    public function applyChanges(ClassSchedule $schedule): void
    {
        $future = ClassSession::where('schedule_id', $schedule->id)->where('status', 'scheduled')->where('starts_at', '>', now());

        (clone $future)->where('booked_count', 0)->where('waitlist_count', 0)->delete();
        (clone $future)->update(['trainer_id' => $schedule->trainer_id, 'room_id' => $schedule->room_id]);

        $this->generate($schedule->fresh(['branch.organization']));
    }
}
