<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/** Where this account is signed in, and signing out everywhere else. */
class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentSession = $request->hasSession() ? $request->session()->getId() : null;
        $currentToken = $user->currentAccessToken();

        $sessions = config('session.driver') === 'database'
            ? DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()->map(fn ($s) => [
                'id' => substr(hash('sha256', $s->id), 0, 16),
                'kind' => 'browser',
                'device' => self::describe($s->user_agent),
                'ip' => $s->ip_address,
                'last_active' => Carbon::createFromTimestamp($s->last_activity)->toIso8601String(),
                'current' => $s->id === $currentSession,
            ])
            : collect();

        $tokens = $user->tokens()->latest('last_used_at')->get()->map(fn (PersonalAccessToken $t) => [
            'id' => 'token-'.$t->id,
            'kind' => 'app',
            'device' => $t->name,
            'ip' => null,
            'last_active' => ($t->last_used_at ?? $t->created_at)->toIso8601String(),
            'current' => $currentToken instanceof PersonalAccessToken && $currentToken->id === $t->id,
        ]);

        return response()->json(['data' => $sessions->concat($tokens)->values()]);
    }

    /** Signs out every other browser and app; this one stays signed in. */
    public function destroyOthers(Request $request): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();

        $count = self::signOutOthers($request);
        Audit::record('auth.signed_out_other_devices', $user, ['count' => $count]);

        return response()->json(['data' => ['signed_out' => $count]]);
    }

    /** Shared with password changes. Returns how many sessions and tokens were removed. */
    public static function signOutOthers(Request $request): int
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $count = $user->tokens()->when($token instanceof PersonalAccessToken, fn ($q) => $q->whereKeyNot($token->id))->delete();
        $count += DB::table('sessions')->where('user_id', $user->id)
            ->when($request->hasSession(), fn ($q) => $q->where('id', '!=', $request->session()->getId()))
            ->delete();

        return $count;
    }

    /** "Chrome on Android" from a user agent, without a library. */
    public static function describe(?string $agent): string
    {
        $agent ??= '';
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $os = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $os ? "{$browser} · {$os}" : $browser;
    }
}
