<?php

namespace App\Http\Controllers\Api\V1\MemberApp;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\PtEnrollment;
use App\Models\TrainerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The member's personal training: packages, sessions left and coming up, and their trainer. */
class MemberTrainingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->attributes->get('member');
        $enrollments = PtEnrollment::where('member_id', $member->id)->whereIn('status', ['active', 'pending_payment', 'completed'])
            ->with('trainer', 'branch')->latest()->limit(5)->get();
        $profiles = TrainerProfile::whereIn('user_id', $enrollments->pluck('trainer_id'))->get()->keyBy('user_id');

        return response()->json(['data' => $enrollments->map(fn (PtEnrollment $e) => [
            ...$e->present(),
            'trainer_profile' => $e->trainer && ($profiles[$e->trainer_id]->is_public ?? true) ? TrainerProfile::presentFor($e->trainer, $profiles[$e->trainer_id] ?? null) : null,
            'upcoming' => $e->sessions()->where('status', 'scheduled')->where('starts_at', '>', now())->limit(5)->get()->map->present(),
        ])]);
    }
}
