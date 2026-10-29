<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The gym's Telebirr numbers and bank accounts that members pay into. Owners manage them. */
class PaymentAccountController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PaymentAccount::orderBy('sort')->orderBy('id')->get()->map->present()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $account = PaymentAccount::create($this->validated($request));
        Audit::record('payment_account.added', $account, $account->only('kind', 'bank_name', 'account_number'));

        return response()->json(['data' => $account->present()], 201);
    }

    public function update(Request $request, PaymentAccount $paymentAccount): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $paymentAccount->fill($this->validated($request, $paymentAccount));
        $changes = $paymentAccount->getDirty();
        $paymentAccount->save();
        if ($changes) {
            Audit::record('payment_account.updated', $paymentAccount, $changes);
        }

        return response()->json(['data' => $paymentAccount->present()]);
    }

    public function destroy(Request $request, PaymentAccount $paymentAccount): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $paymentAccount->delete();
        Audit::record('payment_account.removed', $paymentAccount, $paymentAccount->only('kind', 'account_number'));

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PaymentAccount $account = null): array
    {
        $required = $account ? 'sometimes' : 'required';
        $kind = $request->input('kind', $account?->kind);

        $data = $request->validate([
            'kind' => [$required, Rule::in(['telebirr', 'bank'])],
            'bank_name' => [Rule::requiredIf($kind === 'bank' && ! $account), 'nullable', 'string', 'max:80'],
            'account_name' => [$required, 'string', 'max:120'],
            'account_number' => [$required, 'string', 'max:40', 'regex:/^[0-9 +\-]+$/'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);
        if ($kind === 'telebirr') {
            $data['bank_name'] = null;
        }

        return $data;
    }
}
