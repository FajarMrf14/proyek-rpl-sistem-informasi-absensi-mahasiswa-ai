<?php

namespace App\Models;

use App\Core\Model;

class AuditLog extends Model
{
    protected string $table = 'audit_logs';
    protected string $primaryKey = 'id';

    /**
     * Catat log aktivitas sistem
     */
    public static function logActivity(?int $userId, string $action, ?string $tableName = null, ?int $recordId = null, ?array $before = null, ?array $after = null): int|string
    {
        $instance = new static();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown';

        return $instance->insert([
            'user_id'        => $userId,
            'action'         => $action,
            'table_name'     => $tableName,
            'record_id'      => $recordId,
            'ip_address'     => $ip,
            'user_agent'     => $agent,
            'payload_before' => $before ? json_encode($before) : null,
            'payload_after'  => $after ? json_encode($after) : null,
        ]);
    }
}
