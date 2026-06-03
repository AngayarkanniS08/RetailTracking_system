-- ============================================
-- GENERIC HELPER FUNCTIONS
-- ============================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- UUID v7 generation (time‑ordered)
CREATE OR REPLACE FUNCTION uuid_generate_v7()
RETURNS UUID
AS $$
DECLARE
    timestamp_ms BIGINT := (EXTRACT(EPOCH FROM clock_timestamp()) * 1000)::BIGINT;
    random_bytes BYTEA := gen_random_bytes(10);
    uuid_bytes BYTEA;
BEGIN
    uuid_bytes := 
        set_byte(set_byte(set_byte(set_byte(set_byte(set_byte('\x000000000000'::bytea,
            0, ((timestamp_ms >> 40) & 255)::int),
            1, ((timestamp_ms >> 32) & 255)::int),
            2, ((timestamp_ms >> 24) & 255)::int),
            3, ((timestamp_ms >> 16) & 255)::int),
            4, ((timestamp_ms >> 8) & 255)::int),
            5, (timestamp_ms & 255)::int) ||
        set_byte(set_byte('\x0000'::bytea, 0, ((7 << 4) | ((timestamp_ms >> 8) & 15))::int), 1, get_byte(random_bytes, 0)) ||
        substring(random_bytes, 2, 8);
    RETURN encode(uuid_bytes, 'hex')::UUID;
END;
$$ LANGUAGE plpgsql VOLATILE;