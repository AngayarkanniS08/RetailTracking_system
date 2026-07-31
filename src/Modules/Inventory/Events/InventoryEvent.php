<?php
declare(strict_types=1);

namespace Modules\Inventory\Events;

/**
 * InventoryEvent — base class for all inventory domain events.
 *
 * Emitted by InventoryService on every mutation; consumed by listeners
 * (notifications, audit, cache) without the service knowing who listens.
 */
abstract class InventoryEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly array $before = [],
        public readonly array $after = [],
        public readonly string $reason = '',
    ) {
    }

    abstract public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'before'  => $this->before,
            'after'   => $this->after,
            'reason'  => $this->reason,
        ];
    }
}
