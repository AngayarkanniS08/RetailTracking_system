<?php
declare(strict_types=1);

namespace Modules\Inventory\Events;

/**
 * InventoryEventDispatcher — synchronous in-process event bus.
 *
 * InventoryService dispatches InventoryEvent objects here; listeners are
 * registered at composition time (see InventoryService). The service never
 * knows which listeners exist — this keeps coupling one-directional.
 */
final class InventoryEventDispatcher
{
    /** @var InventoryEventListenerInterface[] */
    private array $listeners = [];

    /**
     * @param InventoryEventListenerInterface[] $listeners
     */
    public function __construct(array $listeners = [])
    {
        foreach ($listeners as $listener) {
            $this->addListener($listener);
        }
    }

    public function addListener(InventoryEventListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function dispatch(InventoryEvent $event): void
    {
        foreach ($this->listeners as $listener) {
            try {
                $listener->handle($event);
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[%s] listener %s failed: %s',
                    $event->name(),
                    get_class($listener),
                    $e->getMessage()
                ));
            }
        }
    }
}
