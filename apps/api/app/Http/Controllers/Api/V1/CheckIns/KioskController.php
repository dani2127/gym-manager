<?php

namespace App\Http\Controllers\Api\V1\CheckIns;

use App\CheckIns\CheckInGate;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckInResource;
use App\Models\KioskDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The self-service entrance tablet. It signs in with its own device token,
 * not a staff account, and can only scan codes at its branch.
 */
class KioskController extends Controller
{
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        $kiosk = KioskDevice::where('pairing_code_hash', KioskDevice::hash(KioskDevice::normalizeCode($data['code'])))
            ->where('pairing_expires_at', '>', now())
            ->first();

        if (! $kiosk) {
            throw ValidationException::withMessages(['code' => __('checkins.pairing_invalid')]);
        }

        $token = $kiosk->completePairing();

        return response()->json(['data' => ['token' => $token, ...$this->describe($kiosk)]]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->describe($request->attributes->get('kiosk'))]);
    }

    /**
     * What the member sees on the tablet: first name, number and what's left.
     * Full details (photo, reason codes, override) go to the desk over the channel.
     */
    public function scan(Request $request, CheckInGate $gate): JsonResponse
    {
        /** @var KioskDevice $kiosk */
        $kiosk = $request->attributes->get('kiosk');
        $data = $request->validate(['code' => ['required', 'string', 'max:120']]);

        $outcome = $gate->scan($kiosk->branch, $data['code'], 'kiosk', null);
        $membership = $outcome->checkIn?->membership;

        return response()->json(['data' => [
            'result' => $outcome->result,
            'reason' => $outcome->reason,
            'first_name' => $outcome->member?->first_name,
            'member_no' => $outcome->member?->code,
            'membership' => $membership && in_array($outcome->result, ['allowed', 'already_inside'], true) ? CheckInResource::membership($membership) : null,
        ]]);
    }

    /** @return array<string, mixed> */
    private function describe(KioskDevice $kiosk): array
    {
        $branch = $kiosk->branch()->withoutGlobalScopes()->with('organization')->first();

        return [
            'kiosk' => ['id' => $kiosk->ulid, 'name' => $kiosk->name],
            'branch' => ['id' => $branch->ulid, 'name' => $branch->name],
            'organization' => ['name' => $branch->organization->name],
        ];
    }
}
