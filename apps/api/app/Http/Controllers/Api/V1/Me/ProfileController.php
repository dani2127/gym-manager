<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Enums\BranchRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Http\Resources\OrganizationResource;
use App\Support\Audit;
use App\Support\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request, Tenant $tenant): JsonResponse
    {
        $user = $request->user();

        $branches = $user->isOwner()
            ? $user->accessibleBranches()->orderBy('name')->get()
            : $user->branches()->orderBy('name')->get();

        $managesAny = $user->isOwner() || $branches->contains(fn ($branch) => $branch->pivot?->role === BranchRole::Manager);

        return response()->json([
            'user' => [
                'id' => $user->ulid,
                'name' => $user->name,
                'phone' => $user->phone,
                'phone_verified' => $user->phone_verified_at !== null,
                'email' => $user->email,
                'locale' => $user->locale,
                'account_type' => $user->account_type,
                'telegram_linked' => $user->telegramChatId() !== null,
            ],
            'organization' => new OrganizationResource($tenant->organization()),
            'branches' => BranchResource::collection($branches),
            'abilities' => [
                'manage_branches' => $user->isOwner(),
                'manage_settings' => $user->isOwner(),
                'manage_staff' => $managesAny,
                'view_audit_log' => $user->isOwner(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => [
                'sometimes', 'nullable', 'email', 'max:255',
                Rule::unique('users')->where('organization_id', $user->organization_id)->ignore($user->id),
            ],
            'locale' => ['sometimes', 'in:en,am'],
        ]);

        $user->update($data);

        return response()->json(['message' => __('auth.profile_updated')]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $request->input('password')])->save();

        Audit::record('auth.password_changed', $user);

        return response()->json(['message' => __('auth.password_changed')]);
    }
}
