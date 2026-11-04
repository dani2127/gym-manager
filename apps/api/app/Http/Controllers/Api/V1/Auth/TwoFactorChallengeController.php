<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Auth\TwoFactor;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Second step of login: the code from the authenticator app, or a recovery code. */
class TwoFactorChallengeController extends Controller
{
    use IssuesAuthentication;

    public function __invoke(Request $request, TwoFactor $twoFactor): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'max:2000'],
            'code' => ['nullable', 'string', 'max:10', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:40'],
        ]);

        [$user, $device] = $twoFactor->complete($data['challenge'], $data['code'] ?? null, $data['recovery_code'] ?? null);

        if ($device) {
            $request->merge(['device_name' => $device]);
        }

        Audit::record('auth.login', $user, ['client' => $device ? 'token' : 'session', 'two_factor' => filled($data['code'] ?? null) ? 'app' : 'recovery_code'], $user);

        return $this->issueAuthentication($request, $user);
    }
}
