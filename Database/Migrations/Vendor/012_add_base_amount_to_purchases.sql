-- Add base_amount column to track the pre-GST amount separately from total_amount
ALTER TABLE vendor_purchase_items ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES users(id) ON DELETE CASCADE;

-- Add user_id to existing rows once: it may already exist
UPDATE vendor_purchase_items SET user_id = vp.user_id
FROM vendor_purchases vp
WHERE vendor_purchase_items.purchase_id = vp.id
  AND vendor_purchase_items.user_id IS NULL;

ALTER TABLE vendor_purchase_items ALTER COLUMN user_id SET NOT NULL;

ALTER TABLE vendor_purchases ADD COLUMN IF NOT EXISTS base_amount DECIMAL(12,2) NOT NULL DEFAULT 0 CHECK (base_amount >= 0);

-- Existing rows: set base_amount = total_amount (which currently stores the pre-GST value)
UPDATE vendor_purchases SET base_amount = total_amount WHERE base_amount = 0;

-- Now add GST into total_amount for existing rows
UPDATE vendor_purchases vp SET total_amount = total_amount + COALESCE((
    SELECT SUM(pi.quantity * pi.unit_cost * pi.gst_rate / 100)
    FROM vendor_purchase_items pi WHERE pi.purchase_id = vp.id
), 0);

-- Drop the old incorrect constraint (amount_paid could exceed the old total_amount)
ALTER TABLE vendor_purchases DROP CONSTRAINT IF EXISTS vendor_purchases_amount_paid_check;

-- Add new constraint: amount_paid <= total_amount (now the full payable including GST)
ALTER TABLE vendor_purchases ADD CONSTRAINT vendor_purchases_amount_paid_check
    CHECK (amount_paid >= 0 AND amount_paid <= total_amount);
