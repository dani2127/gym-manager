<?php

namespace App\Http\Requests\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    /** Checked when no user matches, so unknown phones take as long as wrong passwords. */
    private const DUMMY_HASH = '$2y$12$oj6dqmpEH.pCVHp4nSeegObhwEhIXkA.oGN5gIK5Q1pYT91nyZf2C';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organization' => ['required', 'string', 'max:63'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
            // Present for mobile / API clients that want a bearer token.
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Checks the credentials within the organization, rate-limited per
     * organization + phone + IP. Throws a validation error on failure.
     */
    public function authenticate(): User
    {
        $key = 'login:'.mb_strtolower($this->input('organization')).'|'.$this->input('phone').'|'.$this->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'phone' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $organization = Organization::where('slug', mb_strtolower($this->input('organization')))->first();

        $user = $organization ? User::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('phone', Phone::normalize($this->input('phone')))
            ->first() : null;

        $passwordMatches = Hash::check($this->input('password'), $user->password ?? self::DUMMY_HASH);

        if (! $user || ! $passwordMatches) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }

        if (! $organization->isActive()) {
            throw ValidationException::withMessages(['phone' => __('auth.organization_suspended')]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['phone' => __('auth.account_disabled')]);
        }

        RateLimiter::clear($key);

        return $user;
    }
}
