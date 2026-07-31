<?php

namespace Modules\Notification\Provider\Contract;

use Modules\Notification\DTO\NotificationDTO;

/**
 * NotificationProviderInterface — every alert domain implements this contract.
 *
 * The NotificationService knows nothing about tables or business rules; it only
 * aggregates whatever each provider returns. Adding a new notification category
 * (billing, customers, vendors, system health, security) is a new provider class
 * registered in one place — no architectural change required.
 */
interface NotificationProviderInterface
{
    /**
     * Return the live alerts for the given user.
     *
     * @return NotificationDTO[]
     */
    public function getNotifications(string $userId): array;

    /**
     * Domain name used for cache keys / invalidation (e.g. "inventory").
     */
    public function getDomain(): string;
}
