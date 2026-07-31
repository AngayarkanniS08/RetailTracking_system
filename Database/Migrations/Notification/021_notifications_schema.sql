-- 021_notifications_schema.sql
-- Notification Platform — read-state store.
--
-- Notifications themselves are COMPUTED on demand by NotificationModule providers
-- (live inventory health, billing, customers, vendors, ...). This table only
-- persists which (scope_key, signature) pairs the user has already seen so the
-- backend can compute a truthful "unread" count and list.
--
--   scope_key  : stable identity of a notification, e.g. "inventory:low_stock:<product_id>"
--   signature  : opaque state fingerprint (e.g. current stock quantity). When the
--                underlying state changes the signature changes and the alert
--                surfaces as unread again. See NotificationService::markCurrentAsRead().

CREATE TABLE IF NOT EXISTS notification_reads (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    scope_key  VARCHAR(255) NOT NULL,
    signature  VARCHAR(255) NOT NULL DEFAULT '',
    read_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (user_id, scope_key, signature)
);

CREATE INDEX IF NOT EXISTS idx_notif_reads_user ON notification_reads(user_id, read_at);
CREATE INDEX IF NOT EXISTS idx_notif_reads_scope ON notification_reads(user_id, scope_key);

ALTER TABLE notification_reads ENABLE ROW LEVEL SECURITY;

CREATE POLICY notification_reads_isolation ON notification_reads
    USING (user_id = current_setting('app.current_user_id', true)::uuid)
    WITH CHECK (user_id = current_setting('app.current_user_id', true)::uuid);
