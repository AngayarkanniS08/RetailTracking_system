<?php

namespace Modules\Notification\Service;

use Modules\Notification\DTO\NotificationDTO;
use Modules\Notification\Provider\Contract\NotificationProviderInterface;
use Modules\Notification\Provider\InventoryAlertProvider;
use Modules\Notification\Repository\NotificationReadRepository;

/**
 * NotificationService — aggregates providers into the canonical payload.
 *
 * Responsibilities (ALL backend-computed; the frontend renders only):
 *   - run every registered provider for the user
 *   - merge alerts and apply read state from notification_reads
 *   - compute the summary totals (total / unread / critical / warning / info)
 *   - mark alerts as read (individual or all)
 *
 * Adding a new notification category = append a provider to $this->providers.
 */
class NotificationService
{
    /** @var NotificationProviderInterface[] */
    private array $providers;

    private NotificationReadRepository $reads;

    /**
     * @param NotificationProviderInterface[] $providers
     */
    public function __construct(array $providers = [], ?NotificationReadRepository $reads = null)
    {
        $this->providers = $providers !== []
            ? $providers
            : [new InventoryAlertProvider()];
        $this->reads = $reads ?? new NotificationReadRepository();
    }

    /**
     * Build the full {summary, alerts} payload for a user (uncached).
     *
     * @return array{summary: array<string,int>, alerts: array<int,array<string,mixed>>}
     */
    public function buildPayload(string $userId): array
    {
        $notifications = $this->collectForUser($userId);
        $alerts = array_map(static fn (NotificationDTO $n) => $n->toArray(), $notifications);

        $summary = [
            'total'    => count($alerts),
            'unread'   => 0,
            'critical' => 0,
            'warning'  => 0,
            'info'     => 0,
        ];

        foreach ($notifications as $notification) {
            if (!$notification->read) {
                $summary['unread']++;
            }
            if (isset($summary[$notification->severity])) {
                $summary[$notification->severity]++;
            }
        }

        return ['summary' => $summary, 'alerts' => $alerts];
    }

    /**
     * Mark a specific set of alerts as read at their current signatures.
     *
     * @param string[] $keys
     */
    public function markRead(string $userId, array $keys): int
    {
        if ($keys === []) {
            return 0;
        }
        $keys = array_values(array_unique(array_filter($keys, 'is_string')));

        $signatureMap = $this->currentSignatureMap($userId);
        $target = [];
        foreach ($keys as $key) {
            if (isset($signatureMap[$key])) {
                $target[$key] = $signatureMap[$key];
            }
        }

        return $this->reads->markRead($userId, $target);
    }

    /**
     * Mark every currently-active alert as read (used by "Mark all as read").
     */
    public function markAllRead(string $userId): int
    {
        return $this->reads->markRead($userId, $this->currentSignatureMap($userId));
    }

    /**
     * @return NotificationDTO[]
     */
    private function collectForUser(string $userId): array
    {
        $readMap = $this->reads->getReadMap($userId);
        $collected = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->getNotifications($userId) as $notification) {
                if (isset($readMap[$notification->key]) && $readMap[$notification->key] === $notification->signature) {
                    $notification->read = true;
                }
                $collected[] = $notification;
            }
        }

        return $this->sort($collected);
    }

    /**
     * @return array<string, string> key => signature for all active notifications
     */
    private function currentSignatureMap(string $userId): array
    {
        $map = [];
        foreach ($this->collectForUser($userId) as $notification) {
            $map[$notification->key] = $notification->signature;
        }
        return $map;
    }

    /**
     * @param NotificationDTO[] $notifications
     * @return NotificationDTO[]
     */
    private function sort(array $notifications): array
    {
        $order = [
            NotificationDTO::SEVERITY_CRITICAL => 0,
            NotificationDTO::SEVERITY_WARNING  => 1,
            NotificationDTO::SEVERITY_INFO     => 2,
        ];
        usort($notifications, static function (NotificationDTO $a, NotificationDTO $b) use ($order): int {
            $bySeverity = ($order[$a->severity] ?? 9) <=> ($order[$b->severity] ?? 9);
            if ($bySeverity !== 0) {
                return $bySeverity;
            }
            return $a->read <=> $b->read;
        });
        return $notifications;
    }
}
