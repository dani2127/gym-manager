<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    /**
     * Liveness probe for load balancers and the web app.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Public, non-secret platform configuration the clients need on boot.
     */
    public function config(): JsonResponse
    {
        $notifications = config('gym.notifications');

        return response()->json([
            'locale' => app()->getLocale(),
            'locales' => config('gym.locales'),
            'timezone' => config('gym.timezone'),
            'currency' => config('gym.currency'),
            'tax' => config('gym.tax'),
            'features' => config('gym.features'),
            'notification_channels' => collect($notifications['channels'])
                ->filter(fn (string $channel) => $notifications[$channel]['enabled'])
                ->values(),
            'messages' => [
                'welcome' => __('gym.welcome'),
            ],
        ]);
    }
}
