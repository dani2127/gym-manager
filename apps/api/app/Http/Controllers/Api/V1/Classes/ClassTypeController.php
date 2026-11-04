<?php

namespace App\Http\Controllers\Api\V1\Classes;

use App\Http\Controllers\Api\V1\Catalog\TranslatedName;
use App\Http\Controllers\Controller;
use App\Models\ClassType;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kinds of class the gym runs (Spinning, Yoga…). Everyone reads them; owners change them. */
class ClassTypeController extends Controller
{
    use TranslatedName;

    public function index(): JsonResponse
    {
        return response()->json(['data' => ClassType::orderByDesc('is_active')->orderBy('id')->get()->map->present()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $type = ClassType::create($this->validated($request, true));
        Audit::record('class_type.added', $type, ['name' => $type->name]);

        return response()->json(['data' => $type->present()], 201);
    }

    public function update(Request $request, ClassType $classType): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $classType->update($this->validated($request, false));
        Audit::record('class_type.updated', $classType, $classType->getChanges());

        return response()->json(['data' => $classType->present()]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            ...$this->translatedRules('name', $creating),
            ...$this->translatedRules('description', false, 300),
            'description' => ['sometimes', 'nullable', 'array:en,am'],
            'color' => [$req, Rule::in(ClassType::COLORS)],
            'default_duration_min' => [$req, 'integer', 'min:10', 'max:240'],
            'default_capacity' => [$req, 'integer', 'min:1', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
