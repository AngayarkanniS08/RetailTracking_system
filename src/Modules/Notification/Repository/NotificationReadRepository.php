<?php

namespace Modules\Notification\Repository;

use PDO;
use Config\Database;

/**
 * NotificationReadRepository — persistence for the notification read-state.
 *
 * Stores which (scope_key, signature) pairs the user has already seen. The
 * signature fingerprint lets the same alert re-surface as unread whenever the
 * underlying state changes (e.g. stock drops again after being viewed).
 */
class NotificationReadRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @return array<string, string> map of scope_key => signature for the user
     */
    public function getReadMap(string $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT scope_key, signature FROM public.notification_reads WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(string) $row['scope_key']] = (string) $row['signature'];
        }
        return $map;
    }

    /**
     * Mark the given scope_keys as read at their CURRENT signatures.
     * Keys whose signature already matches are no-ops (ON CONFLICT DO NOTHING).
     *
     * @param array<string, string> $keySignatureMap scope_key => signature
     */
    public function markRead(string $userId, array $keySignatureMap): int
    {
        if ($keySignatureMap === []) {
            return 0;
        }

        $chunks = array_chunk($keySignatureMap, 200, true);
        $affected = 0;

        foreach ($chunks as $chunk) {
            $placeholders = [];
            $values = [];
            $i = 1;
            foreach ($chunk as $key => $signature) {
                $placeholders[] = "(:uid, :k{$i}, :s{$i})";
                $values["k{$i}"] = $key;
                $values["s{$i}"] = $signature;
                $i++;
            }
            $values['uid'] = $userId;

            $sql = "INSERT INTO public.notification_reads (user_id, scope_key, signature)
                    VALUES " . implode(', ', $placeholders) . "
                    ON CONFLICT (user_id, scope_key, signature) DO NOTHING";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            $affected += $stmt->rowCount();
        }

        return $affected;
    }
}
