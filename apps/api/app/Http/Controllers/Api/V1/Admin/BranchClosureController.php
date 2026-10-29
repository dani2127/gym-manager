<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchClosure;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Holidays and special hours (e.g. Timkat, Meskel) for one branch. */
class BranchClosureController extends Controller
{
    public function index(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        return response()->json([
            'data' => $branch->closures()->whereDate('date', '>=', today())->get(['id', 'date', 'opens_at', 'closes_at', 'reason']),
        ]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', Rule::unique('branch_closures')->where('branch_id', $branch->id)],
            'opens_at' => ['nullable', 'required_with:closes_at', 'date_format:H:i'],
            'closes_at' => ['nullable', 'required_with:opens_at', 'date_format:H:i', 'after:opens_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $closure = $branch->closures()->create($data);
        Audit::record('branch.closure_added', $branch, $data);

        return response()->json(['data' => $closure->only('id', 'date', 'opens_at', 'closes_at', 'reason')], 201);
    }

    public function destroy(Branch $branch, BranchClosure $closure): JsonResponse
    {
        $this->authorize('update', $branch);
        abort_unless($closure->branch_id === $branch->id, 404);

        $closure->delete();
        Audit::record('branch.closure_removed', $branch, ['date' => $closure->date->toDateString()]);

        return response()->json(status: 204);
    }
}
