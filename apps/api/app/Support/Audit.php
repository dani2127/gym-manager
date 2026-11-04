<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Writes an audit_logs row for a security- or money-relevant action. */
final class Audit
{
    /** @param  array<string, mixed>  $changes */
    public static function record(string $action, ?Model $subject = null, array $changes = [], ?User $actor = null): void
    {
        $actor ??= auth()->user();
        $organizationId = $actor?->organization_id ?? $subject?->getAttribute('organization_id');

        if ($organizationId === null) {
            return;
        }

        AuditLog::create([
            'organization_id' => $organizationId,
            'branch_id' => $subject instanceof Branch ? $subject->id : $subject?->getAttribute('branch_id'),
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
