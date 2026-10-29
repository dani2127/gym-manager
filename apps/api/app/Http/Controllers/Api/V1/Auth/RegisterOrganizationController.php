<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Rules\EthiopianPhone;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/** Self-service sign-up: creates the organization, its first branch and the owner account. */
class RegisterOrganizationController extends Controller
{
    use IssuesAuthentication;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:120'],
            'organization_slug' => ['required', 'string', 'min:3', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:organizations,slug'],
            'branch_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new EthiopianPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'locale' => ['nullable', 'in:en,am'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $owner = DB::transaction(function () use ($data) {
            $organization = Organization::create([
                'name' => $data['organization_name'],
                'slug' => $data['organization_slug'],
                'default_locale' => $data['locale'] ?? 'en',
            ]);

            Branch::create([
                'organization_id' => $organization->id,
                'name' => $data['branch_name'],
                'code' => 'MAIN',
            ]);

            return User::create([
                'organization_id' => $organization->id,
                'account_type' => AccountType::Owner,
                'name' => $data['name'],
                'phone' => Phone::normalize($data['phone']),
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'locale' => $data['locale'] ?? 'en',
            ]);
        });

        Audit::record('organization.registered', $owner, [], $owner);

        return $this->issueAuthentication($request, $owner, 201);
    }
}
