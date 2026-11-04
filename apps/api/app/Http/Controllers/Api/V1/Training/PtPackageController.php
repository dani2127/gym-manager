<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Http\Controllers\Api\V1\Catalog\TranslatedName;
use App\Http\Controllers\Controller;
use App\Models\PtPackage;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Personal-training packages. Everyone reads them; owners change them. */
class PtPackageController extends Controller
{
    use TranslatedName;

    public function index(): JsonResponse
    {
        return response()->json(['data' => PtPackage::with('trainer')->orderByDesc('is_active')->orderBy('sort')->orderBy('id')->get()->map->present()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $package = PtPackage::create($this->validated($request, true));
        Audit::record('pt_package.added', $package, ['name' => $package->name, 'price_cents' => $package->price_cents]);

        return response()->json(['data' => $package->load('trainer')->present()], 201);
    }

    public function update(Request $request, PtPackage $ptPackage): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $ptPackage->update($this->validated($request, false));
        Audit::record('pt_package.updated', $ptPackage, $ptPackage->getChanges());

        return response()->json(['data' => $ptPackage->load('trainer')->present()]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            ...$this->translatedRules('name', $creating),
            'sessions_count' => [$req, 'integer', 'min:1', 'max:200'],
            'price_cents' => [$req, 'integer', 'min:0', 'max:100000000'],
            'validity_days' => [$req, 'integer', 'min:1', 'max:730'],
            'trainer' => ['sometimes', 'nullable', 'string', 'max:26'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('trainer', $data)) {
            $data['trainer_id'] = $data['trainer'] ? (User::where('ulid', $data['trainer'])->whereIn('account_type', ['owner', 'staff'])->value('id')
                ?? throw ValidationException::withMessages(['trainer' => __('classes.trainer_not_at_branch')])) : null;
            unset($data['trainer']);
        }

        return $data;
    }
}
