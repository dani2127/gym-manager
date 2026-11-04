<?php

return [
    'already_booked' => 'Already booked for this class.',
    'not_cancellable' => 'This booking can no longer be cancelled.',
    'not_on_roster' => 'Only people booked into the class can be marked.',
    'attendance_too_early' => 'Attendance opens 30 minutes before the class.',
    'capacity_below_booked' => 'The class already has :count people booked.',
    'trainer_not_at_branch' => 'This trainer doesn\'t work at this branch.',
    'reason' => [
        'member_banned' => 'This member is banned.',
        'session_cancelled' => 'This class was cancelled.',
        'session_started' => 'This class has already started.',
        'too_early' => 'Booking for this class hasn\'t opened yet.',
        'membership_needed' => 'A membership that is valid on that day is needed.',
        'wrong_branch' => 'The membership isn\'t valid at this branch.',
        'classes_not_in_plan' => 'Classes aren\'t included in this plan.',
        'no_class_credits' => 'No class credits left on this membership.',
    ],
    'notify_promoted' => 'Good news: a place opened up. You are booked for :class, :when at :branch.',
    'notify_session_cancelled' => ':class on :when at :branch is cancelled: :reason. Your credit is back.',
];
