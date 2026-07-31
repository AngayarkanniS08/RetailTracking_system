<?php
declare(strict_types=1);

namespace Modules\Inventory\Audit;

use PDO;
use Config\Database;

/**
 * InventoryAuditLogger — immutable audit trail for every inventory mutation.
 *
 * Records who/what/when/why: user, action, before & after values, IP address,
 * user agent and an optional reason. This is a write-only store (RLS protects
 * reads to the owning user).
 */
final class InventoryAuditLogger
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function log(
        string $userId,
        string $action,
        array $before = [],
        array $after = [],
        string $reason = '',
    ): void {
        try {
            $entityId = $after['id'] ?? $before['id'] ?? null;
            $stmt = $this->db->prepare("
                INSERT INTO public.inventory_audit_logs (
                    user_id, action, entity_id, entity_type, before_data, after_data,
                    ip_address, user_agent, reason
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $userId,
                $action,
                $entityId,
                'inventory_batch',
                $before !== [] ? json_encode($before) : null,
                $after !== [] ? json_encode($after) : null,
                $this->clientIp(),
                $this->clientUserAgent(),
                $reason !== '' ? $reason : null,
            ]);
        } catch (\Throwable $e) {
            error_log('InventoryAuditLogger::log failed - ' . $e->getMessage());
        }
    }

    private function clientIp(): ?string
    {
        return ($_SERVER['REMOTE_ADDR'] ?? '') !== '' ? (string)$_SERVER['REMOTE_ADDR'] : null;
    }

    private function clientUserAgent(): ?string
    {
        return ($_SERVER['HTTP_USER_AGENT'] ?? '') !== '' ? (string)$_SERVER['HTTP_USER_AGENT'] : null;
    }
}
