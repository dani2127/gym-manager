<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Rules\EthiopianPhone;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Where reminders and receipts go: Telegram or WhatsApp. Telegram needs the
 * chat linked through the bot first; WhatsApp uses a phone number.
 */
class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->state($request)]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'channel' => ['required', Rule::in(['telegram', 'whatsapp'])],
            'whatsapp_number' => ['nullable', 'required_if:channel,whatsapp', 'string', new EthiopianPhone],
        ]);

        if ($data['channel'] === 'telegram' && ! $user->telegramChatId()) {
            throw ValidationException::withMessages(['channel' => __('auth.telegram_link_first')]);
        }

        DB::transaction(function () use ($user, $data) {
            if ($data['channel'] === 'whatsapp') {
                NotificationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'channel' => 'whatsapp'],
                    ['organization_id' => $user->organization_id, 'destination' => Phone::normalize($data['whatsapp_number'])],
                );
            }

            $user->notificationPreferences()->update(['is_preferred' => false]);
            $user->notificationPreferences()->where('channel', $data['channel'])->update(['is_preferred' => true]);
        });

        return response()->json(['data' => $this->state($request)]);
    }

    /** @return array<string, mixed> */
    private function state(Request $request): array
    {
        $prefs = $request->user()->notificationPreferences()->whereNull('opted_out_at')->get()->keyBy('channel');

        return [
            'channel' => $prefs->firstWhere('is_preferred', true)?->channel,
            'telegram_linked' => isset($prefs['telegram']) && $prefs['telegram']->verified_at !== null,
            'whatsapp_number' => $prefs['whatsapp']->destination ?? $request->user()->phone,
        ];
    }
}
