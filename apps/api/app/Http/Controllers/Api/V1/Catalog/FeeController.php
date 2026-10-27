<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeeResource;
use App\Models\Fee;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Registration and other one-off fees. Owners set them; the desk charges them. */
class FeeController extends Controller
{
    use TranslatedName;

    public function index(): AnonymousResourceCollection
    {
        return FeeResource::collection(Fee::orderBy('kind')->orderBy('amount_cents')->get());
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $fee = Fee::create($this->validated($request));
        Audit::record('fee.created', $fee, ['name' => $fee->name['en'], 'amount_cents' => $fee->amount_cents]);

        return (new FeeResource($fee))->response()->setStatusCode(201);
    }

    public function update(Request $request, Fee $fee): FeeResource
    {
        abort_unless($request->user()->isOwner(), 403);

        $fee->fill($this->validated($request, $fee));
        $changes = $fee->getDirty();
        $fee->save();

        if ($changes) {
            Audit::record('fee.updated', $fee, $changes);
        }

        return new FeeResource($fee);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Fee $fee = null): array
    {
        $required = $fee ? 'sometimes' : 'required';

        return $request->validate([
            ...$this->translatedRules('name', ! $fee),
            'kind' => [$required, Rule::in(['registration', 'card_replacement', 'other'])],
            'amount_cents' => [$required, 'integer', 'min:1', 'max:100000000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
