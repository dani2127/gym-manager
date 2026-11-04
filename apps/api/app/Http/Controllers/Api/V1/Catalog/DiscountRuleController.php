<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\DiscountRuleResource;
use App\Models\DiscountRule;
use App\Models\Plan;
use App\Support\Audit;
use App\Support\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Student, couple, family, company and promo-code discounts. Owners only:
 * staff see them at the desk through the sale quote.
 */
class DiscountRuleController extends Controller
{
    use TranslatedName;

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->isOwner(), 403);

        return DiscountRuleResource::collection(DiscountRule::with('plan')->orderBy('kind')->latest('id')->get());
    }

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $rule = DiscountRule::create($this->validated($request, $tenant));
        Audit::record('discount.created', $rule, ['name' => $rule->name['en'], 'kind' => $rule->kind, 'value' => $rule->value, 'value_type' => $rule->value_type]);

        return (new DiscountRuleResource($rule->load('plan')))->response()->setStatusCode(201);
    }

    public function update(Request $request, DiscountRule $discountRule, Tenant $tenant): DiscountRuleResource
    {
        abort_unless($request->user()->isOwner(), 403);

        $discountRule->fill($this->validated($request, $tenant, $discountRule));
        $changes = $discountRule->getDirty();
        $discountRule->save();

        if ($changes) {
            Audit::record('discount.updated', $discountRule, $changes);
        }

        return new DiscountRuleResource($discountRule->load('plan'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Tenant $tenant, ?DiscountRule $rule = null): array
    {
        $required = $rule ? 'sometimes' : 'required';
        $kind = $request->input('kind', $rule?->kind);
        $valueType = $request->input('value_type', $rule?->value_type);

        if ($request->filled('promo_code')) {
            $request->merge(['promo_code' => strtoupper(trim((string) $request->input('promo_code')))]);
        }

        $data = $request->validate([
            ...$this->translatedRules('name', ! $rule),
            'kind' => [$required, Rule::in(['student', 'couple', 'family', 'corporate', 'promo'])],
            'value_type' => [$required, Rule::in(['percent', 'fixed'])],
            'value' => [$required, 'integer', 'min:1', $valueType === 'percent' ? 'max:100' : 'max:100000000'],
            'min_group_size' => ['sometimes', 'nullable', 'integer', 'min:2', 'max:500'],
            'requires_proof' => ['sometimes', 'boolean'],
            'promo_code' => [
                Rule::requiredIf($kind === 'promo' && (! $rule || $request->has('kind'))),
                'nullable', 'string', 'max:40', 'alpha_dash:ascii',
                Rule::unique('discount_rules', 'promo_code')->where('organization_id', $tenant->id())->ignore($rule?->id),
            ],
            'plan' => ['sometimes', 'nullable', 'string', 'max:26'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($kind !== 'promo') {
            $data['promo_code'] = null;
        }
        if (! in_array($kind, DiscountRule::GROUP_KINDS, true)) {
            $data['min_group_size'] = null;
        }
        // Student discounts default to an ID check at the desk.
        if (! $rule && $kind === 'student' && ! array_key_exists('requires_proof', $data)) {
            $data['requires_proof'] = true;
        }

        if (array_key_exists('plan', $data)) {
            $data['plan_id'] = $data['plan'] ? Plan::where('ulid', $data['plan'])->firstOrFail()->id : null;
            unset($data['plan']);
        }

        return $data;
    }
}
