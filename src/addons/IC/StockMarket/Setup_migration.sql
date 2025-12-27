-- Check if total_cost column exists in trade table
-- If not, we need to add it

-- For Position table (might be missing too)
ALTER TABLE xf_ic_sm_position 
ADD COLUMN IF NOT EXISTS total_cost DECIMAL(20,6) NOT NULL DEFAULT 0 
AFTER average_price;

-- For Trade table  
ALTER TABLE xf_ic_sm_trade
ADD COLUMN IF NOT EXISTS total_cost DECIMAL(20,6) NOT NULL DEFAULT 0
AFTER price;

-- Update existing position records to calculate total_cost
UPDATE xf_ic_sm_position 
SET total_cost = average_price * quantity
WHERE total_cost = 0;
