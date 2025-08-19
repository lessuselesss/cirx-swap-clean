-- Add missing retry columns to transactions table
ALTER TABLE transactions ADD COLUMN retry_count INTEGER DEFAULT 0;
ALTER TABLE transactions ADD COLUMN last_retry_at DATETIME DEFAULT NULL;

-- Update existing records to have retry_count = 0
UPDATE transactions SET retry_count = 0 WHERE retry_count IS NULL;