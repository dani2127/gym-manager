<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Rules\EthiopianPhone;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Branch::class);

        return BranchResource::collection($request->user()->accessibleBranches()->with('openingHours')->orderBy('name')->get());
    }

    public function store(Request $request): BranchResource
    {
        $this->authorize('create', Branch::class);

        $branch = Branch::create($this->validated($request));
        Audit::record('branch.created', $branch, $branch->only('name', 'code'));

        return new BranchResource($branch->load('openingHours'));
    }

    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch->load('openingHours'));
    }

    public function update(Request $request, Branch $branch): BranchResource
    {
        $this->authorize('update', $branch);

        $data = $this->validated($request, $branch);

        if (array_key_exists('status', $data)) {
            $this->authorize('administer', $branch);
        }

        $branch->fill($data);
        $changes = $branch->getDirty();
        $branch->save();

        if ($changes) {
            Audit::record('branch.updated', $branch, $changes);
        }

        return new BranchResource($branch->load('openingHours'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Branch $branch = null): array
    {
        $required = $branch ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'code' => [
                $required, 'string', 'max:16', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('branches')->where('organization_id', $request->user()->organization_id)->ignore($branch?->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', new EthiopianPhone],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        if (! empty($data['phone'])) {
            $data['phone'] = Phone::normalize($data['phone']);
        }

        return $data;
    }
}
