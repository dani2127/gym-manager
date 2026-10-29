<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Branch;
use App\Models\Plan;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Membership plans. Every staff member can read them; only owners change prices. */
class PlanController extends Controller
{
    use TranslatedName;

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['active' => ['sometimes', 'boolean']]);

        $plans = Plan::query()
            ->with('branches')
            ->when($request->boolean('active'), fn ($q) => $q->where('is_active', true))
            ->ordered()
            ->get();

        return PlanResource::collection($plans);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $data = $this->validated($request);
        $plan = DB::transaction(function () use ($data) {
            $plan = Plan::create(collect($data)->except('branches')->all());
            $plan->branches()->sync($data['branches'] ?? []);

            return $plan;
        });

        Audit::record('plan.created', $plan, ['name' => $plan->name['en'], 'price_cents' => $plan->price_cents]);

        return (new PlanResource($plan->load('branches')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Plan $plan): PlanResource
    {
        abort_unless($request->user()->isOwner(), 403);

        $data = $this->validated($request, $plan);
        $plan->fill(collect($data)->except('branches')->all());
        $changes = $plan->getDirty();

        DB::transaction(function () use ($plan, $data) {
            $plan->save();
            if (array_key_exists('branches', $data) || $plan->access_scope !== 'selected') {
                $plan->branches()->sync($plan->access_scope === 'selected' ? ($data['branches'] ?? $plan->branches->pluck('id')->all()) : []);
            }
        });

        if ($changes) {
            Audit::record('plan.updated', $plan, $changes);
        }

        return new PlanResource($plan->load('branches'));
    }

    /** Archives the plan; members who bought it keep their snapshot. */
    public function destroy(Request $request, Plan $plan): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $plan->delete();
        Audit::record('plan.archived', $plan, ['name' => $plan->name['en']]);

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $required = $plan ? 'sometimes' : 'required';
        $type = $request->input('type', $plan?->type);
        $scope = $request->input('access_scope', $plan?->access_scope ?? 'home_branch');

        $data = $request->validate([
            ...$this->translatedRules('name', ! $plan),
            ...$this->translatedRules('description', false, 300),
            'description' => ['sometimes', 'nullable', 'array:en,am'],
            'type' => [$required, Rule::in(['time', 'visits', 'time_visits'])],
            'duration_days' => [
                Rule::requiredIf(in_array($type, ['time', 'time_visits'], true) && (! $plan || $request->has('type'))),
                'nullable', 'integer', 'min:1', 'max:1095',
            ],
            'visit_count' => [
                Rule::requiredIf(in_array($type, ['visits', 'time_visits'], true) && (! $plan || $request->has('type'))),
                'nullable', 'integer', 'min:1', 'max:1000',
            ],
            'price_cents' => [$required, 'integer', 'min:0', 'max:100000000'],
            'access_scope' => ['sometimes', Rule::in(['home_branch', 'all_branches', 'selected'])],
            'branches' => [Rule::requiredIf($scope === 'selected' && (! $plan || $request->has('access_scope'))), 'array', 'min:1'],
            'branches.*' => ['string', 'max:26'],
            'freeze_allowed' => ['sometimes', 'boolean'],
            'max_freeze_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'max_freezes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],
            'class_credits' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        // Fields that make no sense for the type are cleared, not kept stale.
        if ($type === 'time') {
            $data['visit_count'] = null;
        }

        if (isset($data['branches'])) {
            $ids = Branch::whereIn('ulid', $data['branches'])->pluck('id');
            if ($ids->count() !== count(array_unique($data['branches']))) {
                throw ValidationException::withMessages(['branches' => __('billing.unknown_branch')]);
            }
            $data['branches'] = $ids->all();
        }

        return $data;
    }
}
