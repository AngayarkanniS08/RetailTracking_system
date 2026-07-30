-- ============================================================
-- Module:          Health
-- Migration Name:  telemetry_schema
-- Description:     Telemetry metrics storage for health monitoring
-- Risk Level:      LOW
-- Transactional:   true
-- ============================================================

CREATE TABLE IF NOT EXISTS public.telemetry_metrics (
    id BIGSERIAL PRIMARY KEY,
    cpu_percent DOUBLE PRECISION,
    ram_percent DOUBLE PRECISION,
    ram_used_bytes BIGINT,
    ram_total_bytes BIGINT,
    swap_percent DOUBLE PRECISION,
    swap_used_bytes BIGINT,
    swap_total_bytes BIGINT,
    disk_percent DOUBLE PRECISION,
    disk_used_bytes BIGINT,
    disk_total_bytes BIGINT,
    disk_read_bytes BIGINT,
    disk_write_bytes BIGINT,
    db_latency_ms DOUBLE PRECISION,
    db_total_connections INT,
    db_active_connections INT,
    db_idle_connections INT,
    db_cache_hit_ratio DOUBLE PRECISION,
    db_size_bytes BIGINT,
    db_slow_queries INT,
    valkey_latency_ms DOUBLE PRECISION,
    valkey_hit_ratio DOUBLE PRECISION,
    api_latency_ms DOUBLE PRECISION,
    network_rx_bytes BIGINT,
    network_tx_bytes BIGINT,
    network_rx_packets BIGINT,
    network_tx_packets BIGINT,
    health_score DOUBLE PRECISION,
    health_status VARCHAR(20),
    load_1m DOUBLE PRECISION,
    load_5m DOUBLE PRECISION,
    load_15m DOUBLE PRECISION,
    uptime_seconds BIGINT,
    recorded_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_telemetry_metrics_recorded_at ON public.telemetry_metrics (recorded_at DESC);
CREATE INDEX IF NOT EXISTS idx_telemetry_metrics_status ON public.telemetry_metrics (health_status);

ALTER TABLE public.telemetry_metrics OWNER TO admin;
