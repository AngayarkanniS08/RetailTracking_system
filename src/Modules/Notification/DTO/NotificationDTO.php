<?php

namespace Modules\Notification\DTO;

/**
 * NotificationDTO — canonical notification shape produced by every provider.
 *
 * Providers never touch the HTTP layer; they return a list of these DTOs and
 * NotificationService is responsible for merging, sorting and marking read state.
 */
class NotificationDTO
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING  = 'warning';
    public const SEVERITY_INFO     = 'info';

    public function __construct(
        /** Stable identity, e.g. "inventory:low_stock:<product_id>" */
        public readonly string $key,
        /** Domain, e.g. "inventory" */
        public readonly string $type,
        /** critical | warning | info */
        public readonly string $severity,
        public readonly string $title,
        public readonly string $description,
        /** e.g. "product", "customer", "vendor" */
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $actionUrl,
        public readonly string $icon,
        /** Opaque state fingerprint used to re-surface re-occurring alerts */
        public readonly string $signature,
        public readonly array $metadata = [],
        public readonly ?string $expiresAt = null,
        public bool $read = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->key,
            'key'         => $this->key,
            'type'        => $this->type,
            'severity'    => $this->severity,
            'title'       => $this->title,
            'description' => $this->description,
            'entity_type' => $this->entityType,
            'entity_id'   => $this->entityId,
            'action_url'  => $this->actionUrl,
            'icon'        => $this->icon,
            'read'        => $this->read,
            'created_at'  => date('c'),
            'expires_at'  => $this->expiresAt,
            'metadata'    => $this->metadata,
        ];
    }
}
