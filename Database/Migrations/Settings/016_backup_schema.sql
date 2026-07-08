-- Backup configuration and jobs tables

CREATE TABLE IF NOT EXISTS public.backup_config (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    gdrive_refresh_token TEXT,
    gdrive_backup_folder_id TEXT,
    gdrive_auth_email TEXT,
    schedule_enabled BOOLEAN NOT NULL DEFAULT false,
    schedule_time TIME NOT NULL DEFAULT '22:00',
    retention_daily INT NOT NULL DEFAULT 7,
    retention_weekly INT NOT NULL DEFAULT 4,
    retention_monthly INT NOT NULL DEFAULT 12,
    last_backup_at TIMESTAMP WITH TIME ZONE,
    last_backup_status VARCHAR(20) DEFAULT 'never',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id)
);

CREATE TABLE IF NOT EXISTS public.backup_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    job_type VARCHAR(10) NOT NULL CHECK (job_type IN ('backup', 'restore')),
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'dump', 'uploading', 'downloading', 'restoring', 'completed', 'failed')),
    file_name TEXT,
    file_size BIGINT,
    error_message TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP WITH TIME ZONE
);

ALTER TABLE public.backup_config ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.backup_jobs ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies
        WHERE schemaname = 'public' AND tablename = 'backup_config' AND policyname = 'Users can access their own backup config'
    ) THEN
        CREATE POLICY "Users can access their own backup config" ON public.backup_config
            USING (user_id = current_setting('app.current_user_id', true)::uuid);
    END IF;
END
$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies
        WHERE schemaname = 'public' AND tablename = 'backup_jobs' AND policyname = 'Users can access their own backup jobs'
    ) THEN
        CREATE POLICY "Users can access their own backup jobs" ON public.backup_jobs
            USING (user_id = current_setting('app.current_user_id', true)::uuid);
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS idx_backup_jobs_user_id ON backup_jobs(user_id);
CREATE INDEX IF NOT EXISTS idx_backup_jobs_status ON backup_jobs(status);
CREATE INDEX IF NOT EXISTS idx_backup_jobs_created_at ON backup_jobs(created_at DESC);
