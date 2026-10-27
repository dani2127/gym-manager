<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\Audit;
use App\Support\Settings\SettingsRepository;
use App\Support\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Organization settings (tax, feature toggles) and per-branch overrides.
 * Payloads use dotted keys: {"settings": {"tax.enabled": true}}.
 */
class SettingsController extends Controller
{
    public function __construct(private SettingsRepository $settings, private Tenant $tenant) {}

    public function show(): JsonResponse
    {
        return response()->json(['settings' => $this->settings->all($this->tenant->organization())]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $values = $this->validatedValues($request, nullable: false);
        $this->settings->put($this->tenant->organization(), $values);
        Audit::record('settings.updated', null, $values);

        return $this->show();
    }

    public function showBranch(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        return $this->branchResponse($branch);
    }

    /** A null value removes the branch override so the organization value applies again. */
    public function updateBranch(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('administer', $branch);

        $values = $this->validatedValues($request, nullable: true);
        $this->settings->put($this->tenant->organization(), $values, $branch);
        Audit::record('settings.branch_updated', $branch, $values);

        return $this->branchResponse($branch);
    }

    /** @return array<string, mixed> */
    private function validatedValues(Request $request, bool $nullable): array
    {
        $request->validate(['settings' => ['required', 'array'], ...$this->settings->rules($nullable)]);

        return array_intersect_key($request->input('settings'), $this->settings->definitions());
    }

    private function branchResponse(Branch $branch): JsonResponse
    {
        return response()->json([
            'settings' => $this->settings->all($this->tenant->organization(), $branch),
            'overridden' => $this->settings->overriddenKeys($branch),
        ]);
    }
}
