<?php
declare(strict_types=1);

namespace Database\Engine;

/**
 * Lightweight lifecycle event dispatcher for the migration engine.
 *
 * Enables telemetry, Slack notifications, and plugin extensions without
 * coupling them to the core runner. Listeners are registered by event name.
 *
 * Available events:
 *   before.run          — before the full migration run begins
 *   after.run           — after all migrations complete successfully
 *   before.migration    — before each individual migration
 *   after.migration     — after each individual migration succeeds
 *   migration.failed    — when a migration throws an exception
 *   before.rollback     — before a rollback batch begins
 *   after.rollback      — after rollback completes
 *   before.seed         — before a seeder runs
 *   after.seed          — after a seeder completes
 *   migration.repaired  — when repair --approve is executed
 */
class EventDispatcher
{
    /** @var array<string, callable[]> */
    private array $listeners = [];

    /**
     * Register a listener for a named event.
     *
     * @param string   $event    Event name (e.g. 'after.migration')
     * @param callable $listener Receives event payload array
     */
    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Fire an event, calling all registered listeners in registration order.
     *
     * @param string               $event   Event name
     * @param array<string, mixed> $payload Data passed to every listener
     */
    public function dispatch(string $event, array $payload = []): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            try {
                $listener($payload);
            } catch (\Throwable $e) {
                // Listeners must never crash the engine
                MigrationLogger::warn(
                    "Event listener threw an exception: {$e->getMessage()}",
                    ['event' => $event]
                );
            }
        }
    }
}
