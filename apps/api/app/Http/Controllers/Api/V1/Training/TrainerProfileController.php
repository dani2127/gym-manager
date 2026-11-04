<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Enums\BranchRole;
use App\Http\Controllers\Controller;
use App\Models\TrainerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A trainer's own profile, shown to members in the app when public. */
class TrainerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->isTrainer($request), 403);

        return response()->json(['data' => TrainerProfile::presentFor($user, TrainerProfile::where('user_id', $user->id)->first())]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->isTrainer($request), 403);
        $data = $request->validate([
            'bio' => ['nullable', 'array:en,am'],
            'bio.en' => ['nullable', 'string', 'max:600'],
            'bio.am' => ['nullable', 'string', 'max:600'],
            'specialties' => ['nullable', 'array', 'max:8'],
            'specialties.*' => ['string', 'max:40'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $profile = TrainerProfile::updateOrCreate(['user_id' => $user->id], ['organization_id' => $user->organization_id, ...$data]);

        return response()->json(['data' => TrainerProfile::presentFor($user, $profile)]);
    }

    private function isTrainer(Request $request): bool
    {
        $user = $request->user();

        return $user->isOwner() || $user->branches()->wherePivot('role', BranchRole::Trainer->value)->exists();
    }
}
