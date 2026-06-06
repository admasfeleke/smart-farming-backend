<?php

namespace App\Services;

use App\Models\CaseAuditLog;
use App\Models\User;

class CaseAuditLogger
{
    public static function log(
        string $entityType,
        int $entityId,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $note = null,
        array $meta = [],
    ): void {
        $actor = auth()->user();
        $role = $actor instanceof User ? strtolower((string) optional($actor->role)->name) : null;
        $regionId = $actor instanceof User ? $actor->region_id : null;

        CaseAuditLog::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_user_id' => $actor instanceof User ? $actor->id : null,
            'actor_role' => $role,
            'actor_region_id' => $regionId,
            'note' => $note,
            'meta_json' => $meta === [] ? null : $meta,
        ]);
    }
}

