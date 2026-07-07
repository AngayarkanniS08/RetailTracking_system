-- Add original_quantity to inventory_batches for remaining% calculation
-- Needed for Old Stock classification: remainingPct >= 50%

ALTER TABLE public.inventory_batches
    ADD COLUMN IF NOT EXISTS original_quantity INT;

-- Backfill: set original_quantity = initial_qty for existing rows
UPDATE public.inventory_batches
    SET original_quantity = initial_qty
    WHERE original_quantity IS NULL;

-- Make it NOT NULL after backfill
ALTER TABLE public.inventory_batches
    ALTER COLUMN original_quantity SET NOT NULL;
