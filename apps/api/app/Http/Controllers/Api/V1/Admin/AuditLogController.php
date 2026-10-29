<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->isOwner(), 403);

        $request->validate(['action' => ['nullable', 'string', 'max:64']]);

        return AuditLogResource::collection(
            AuditLog::with(['user', 'branch'])
                ->when($request->input('action'), fn ($q, $action) => $q->where('action', 'like', $action.'%'))
                ->latest('id')
                ->paginate(50)
        );
    }
}
