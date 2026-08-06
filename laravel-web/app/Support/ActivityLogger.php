<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ActivityLogger
{
    public static function log(
        string $module,
        string $action,
        ?string $recordType = null,
        $recordId = null,
        ?string $description = null,
        $before = null,
        $after = null,
        ?Request $request = null,
        ?User $actor = null,
        ?int $parentId = null
    ): void {
        try {
            if (!Schema::hasTable('activity_logs')) {
                return;
            }

            $request = $request ?: request();
            $actor = $actor ?: $request->user();
            $parentId = $parentId ?: self::resolveParentId($actor);

            ActivityLog::create([
                'parent_id' => $parentId ?: 0,
                'user_id' => $actor?->id,
                'user_type' => $actor?->type,
                'module' => $module,
                'action' => $action,
                'record_type' => $recordType,
                'record_id' => $recordId ? (int) $recordId : null,
                'description' => $description,
                'before_json' => self::normalizePayload($before),
                'after_json' => self::normalizePayload($after),
                'ip' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 1000) : null,
            ]);
        } catch (\Throwable $e) {
            // Audit logging must never break business flow.
        }
    }

    private static function resolveParentId(?User $actor): int
    {
        if (!$actor) return 0;
        if (in_array($actor->type, ['admin', 'owner'])) return (int) $actor->id;
        return (int) ($actor->parent_id ?: 0);
    }

    private static function normalizePayload($payload): ?array
    {
        if ($payload === null) return null;
        if (is_array($payload)) return $payload;
        if ($payload instanceof \Illuminate\Database\Eloquent\Model) return $payload->toArray();
        if ($payload instanceof \Illuminate\Support\Collection) return $payload->toArray();
        return ['value' => $payload];
    }
}
