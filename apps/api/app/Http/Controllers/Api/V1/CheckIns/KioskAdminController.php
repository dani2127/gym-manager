<?php

namespace App\Http\Controllers\Api\V1\CheckIns;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\KioskDevice;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Entrance kiosks: owners and branch managers add, pair and remove them. */
class KioskAdminController extends Controller
{
    public function index(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->managesBranch($branch), 403);

        return response()->json(['data' => KioskDevice::where('branch_id', $branch->id)->orderBy('name')->get()->map(fn (KioskDevice $k) => $this->present($k))]);
    }

    /** Adds a kiosk and returns its pairing code (shown once, valid 15 minutes). */
    public function store(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->managesBranch($branch), 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $kiosk = KioskDevice::create(['branch_id' => $branch->id, 'name' => $data['name']]);
        $code = $kiosk->startPairing();
        Audit::record('kiosk.added', $branch, ['name' => $kiosk->name]);

        return response()->json(['data' => [...$this->present($kiosk), 'pairing_code' => $code]], 201);
    }

    /** New pairing code; the tablet currently paired is signed out. */
    public function pair(Request $request, Branch $branch, KioskDevice $kiosk): JsonResponse
    {
        abort_unless($request->user()->managesBranch($branch) && $kiosk->branch_id === $branch->id, 404);

        $code = $kiosk->startPairing();
        Audit::record('kiosk.repaired', $branch, ['name' => $kiosk->name]);

        return response()->json(['data' => [...$this->present($kiosk), 'pairing_code' => $code]]);
    }

    public function destroy(Request $request, Branch $branch, KioskDevice $kiosk): JsonResponse
    {
        abort_unless($request->user()->managesBranch($branch) && $kiosk->branch_id === $branch->id, 404);

        $kiosk->delete();
        Audit::record('kiosk.removed', $branch, ['name' => $kiosk->name]);

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function present(KioskDevice $kiosk): array
    {
        return [
            'id' => $kiosk->ulid,
            'name' => $kiosk->name,
            'status' => $kiosk->status(),
            'paired_at' => $kiosk->paired_at,
            'pairing_expires_at' => $kiosk->pairing_expires_at,
            'last_seen_at' => $kiosk->last_seen_at,
        ];
    }
}
