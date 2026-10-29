<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'subject_type' => $this->subject_type,
            'changes' => $this->changes,
            'ip' => $this->ip,
            'created_at' => $this->created_at,
            'user' => $this->user ? ['id' => $this->user->ulid, 'name' => $this->user->name] : null,
            'branch' => $this->branch ? ['id' => $this->branch->ulid, 'name' => $this->branch->name] : null,
        ];
    }
}
