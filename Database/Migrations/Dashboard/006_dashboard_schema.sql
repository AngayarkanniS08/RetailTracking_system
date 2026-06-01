-- Dashboard & Inventory related tables
-- Including user_id to enforce RLS (Row Level Security)

-- Create sales table
CREATE TABLE IF NOT EXISTS public.sales (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create sales_items table
CREATE TABLE IF NOT EXISTS public.sales_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    sale_id UUID NOT NULL REFERENCES public.sales(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES public.products(id),
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create purchases table
CREATE TABLE IF NOT EXISTS public.purchases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    -- vendor_id UUID, -- Optional foreign key to vendors table
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    CONSTRAINT paid_amount_check CHECK (paid_amount <= total_amount),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create inventory_batches table
CREATE TABLE IF NOT EXISTS public.inventory_batches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
    batch_number VARCHAR(100) NOT NULL,
    initial_qty INT NOT NULL,
    remaining_qty INT NOT NULL,
    cost_price DECIMAL(10, 2) NOT NULL,
    selling_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create stock_list table (as requested by user)
CREATE TABLE IF NOT EXISTS public.stock_list (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES public.products(id),
    quantity INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Enable RLS on all tables
ALTER TABLE public.sales ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.sales_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.purchases ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.inventory_batches ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.stock_list ENABLE ROW LEVEL SECURITY;

-- Create policies for isolation
CREATE POLICY "Users can access their own sales" ON public.sales
    USING (user_id = current_setting('app.current_user_id', true)::uuid);
    
CREATE POLICY "Users can access their own sales_items" ON sales_items
    USING (EXISTS (SELECT 1 FROM sales WHERE sales.id = sale_id AND sales.user_id = current_setting('app.current_user_id', true)::uuid));

CREATE POLICY "Users can access their own purchases" ON public.purchases
    USING (user_id = current_setting('app.current_user_id', true)::uuid);
    
CREATE POLICY "Users can access their own inventory batches" ON public.inventory_batches
    USING (user_id = current_setting('app.current_user_id', true)::uuid);

CREATE POLICY "Users can access their own stock list" ON public.stock_list
    USING (user_id = current_setting('app.current_user_id', true)::uuid);

CREATE INDEX idx_sales_user_id ON sales(user_id);
CREATE INDEX idx_sales_created_at ON sales(created_at);
CREATE INDEX idx_sales_items_sale_id ON sales_items(sale_id);
CREATE INDEX idx_sales_items_product_id ON sales_items(product_id);
CREATE INDEX idx_purchases_user_id ON purchases(user_id);
CREATE INDEX idx_purchases_created_at ON purchases(created_at);
CREATE INDEX idx_inventory_batches_product_id ON inventory_batches(product_id);
CREATE INDEX idx_inventory_batches_user_id ON inventory_batches(user_id);
CREATE INDEX idx_stock_list_product_id ON stock_list(product_id);