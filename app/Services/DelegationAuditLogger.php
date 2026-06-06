<?php

namespace App\Services;

use App\Models\DelegationAuditLog;

class DelegationAuditLogger
{
    public static function log(
        string $action,
        ?int $targetUserId,
        ?array $before = null,
        ?array $after = null,
        ?string $note = null
    ): void {
        DelegationAuditLog::query()->create([
            'actor_user_id' => auth()->id(),
            'target_user_id' => $targetUserId,
            'action' => $action,
            'before_json' => $before,
            'after_json' => $after,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
