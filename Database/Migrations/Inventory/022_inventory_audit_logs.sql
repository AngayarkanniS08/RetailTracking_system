-- ============================================
-- INVENTORY AUDIT LOGS (enterprise audit trail)
-- ============================================

-- Write-only, tenant-scoped audit trail for every inventory mutation
-- (create / update / restock / delete). Records who/what/when/why:
-- user, action, before & after JSONB snapshots, IP, user agent, reason.

CREATE TABLE IF NOT EXISTS public.inventory_audit_logs (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id      UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    action       VARCHAR(40) NOT NULL,
    entity_id    UUID,
    entity_type  VARCHAR(40) NOT NULL DEFAULT 'inventory_batch',
    before_data  JSONB,
    after_data   JSONB,
    ip_address   VARCHAR(45),
    user_agent   TEXT,
    reason       TEXT,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.inventory_audit_logs ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Users can access their own inventory audit logs" ON public.inventory_audit_logs;
CREATE POLICY "Users can access their own inventory audit logs"
    ON public.inventory_audit_logs
    FOR ALL
    USING (user_id = current_setting('app.current_user_id', true)::uuid);

CREATE INDEX IF NOT EXISTS idx_inventory_audit_user_time
    ON public.inventory_audit_logs (user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_inventory_audit_entity
    ON public.inventory_audit_logs (entity_id);
