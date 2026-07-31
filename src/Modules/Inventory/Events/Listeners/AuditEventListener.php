<?php
declare(strict_types=1);

namespace Modules\Inventory\Events\Listeners;

use Modules\Inventory\Audit\InventoryAuditLogger;
use Modules\Inventory\Events\InventoryEvent;
use Modules\Inventory\Events\InventoryEventListenerInterface;

/**
 * AuditEventListener — writes an immutable audit record for every inventory
 * mutation (create / update / delete / restock).
 */
final class AuditEventListener implements InventoryEventListenerInterface
{
    private InventoryAuditLogger $audit;

    public function __construct(?InventoryAuditLogger $audit = null)
    {
        $this->audit = $audit ?? new InventoryAuditLogger();
    }

    public function handle(InventoryEvent $event): void
    {
        $this->audit->log(
            userId: $event->userId,
            action: $event->name(),
            before: $event->before,
            after: $event->after,
            reason: $event->reason,
        );
    }
}
