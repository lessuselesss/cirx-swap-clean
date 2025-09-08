-- Supabase PostgreSQL Schema for CIRX OTC Platform
-- Migration from SQLite to PostgreSQL

-- Enable UUID extension for generating IDs
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Drop existing tables if they exist (for clean migration)
DROP TABLE IF EXISTS transactions CASCADE;

-- Create transactions table
CREATE TABLE transactions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    payment_tx_id VARCHAR(255) UNIQUE NOT NULL,
    payment_chain VARCHAR(50) NOT NULL,
    cirx_recipient_address VARCHAR(255) NOT NULL,
    sender_address VARCHAR(255),
    amount_paid DECIMAL(36,18) NOT NULL,
    payment_token VARCHAR(10) NOT NULL,
    swap_status VARCHAR(50) NOT NULL,
    cirx_transfer_tx_id VARCHAR(255),
    failure_reason TEXT,
    retry_count INTEGER DEFAULT 0,
    last_retry_at TIMESTAMPTZ,
    recovery_attempts INTEGER DEFAULT 0,
    last_recovery_at TIMESTAMPTZ,
    is_test_transaction BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Create indexes for performance
CREATE INDEX idx_payment_tx_id ON transactions(payment_tx_id);
CREATE INDEX idx_swap_status ON transactions(swap_status);
CREATE INDEX idx_sender_address ON transactions(sender_address);
CREATE INDEX idx_sender_status ON transactions(sender_address, swap_status);
CREATE INDEX idx_created_at ON transactions(created_at DESC);
CREATE INDEX idx_payment_chain ON transactions(payment_chain);
CREATE INDEX idx_is_test ON transactions(is_test_transaction);

-- Create function to automatically update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create trigger to automatically update updated_at
CREATE TRIGGER update_transactions_updated_at 
    BEFORE UPDATE ON transactions 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

-- Create view for recent transactions
CREATE OR REPLACE VIEW recent_transactions AS
SELECT 
    id,
    payment_tx_id,
    payment_chain,
    cirx_recipient_address,
    sender_address,
    amount_paid,
    payment_token,
    swap_status,
    cirx_transfer_tx_id,
    failure_reason,
    created_at,
    updated_at
FROM transactions
WHERE created_at > NOW() - INTERVAL '7 days'
ORDER BY created_at DESC;

-- Create view for transaction statistics
CREATE OR REPLACE VIEW transaction_stats AS
SELECT 
    payment_token,
    swap_status,
    COUNT(*) as count,
    SUM(amount_paid) as total_amount,
    AVG(amount_paid) as avg_amount,
    MAX(amount_paid) as max_amount,
    MIN(amount_paid) as min_amount
FROM transactions
WHERE is_test_transaction = FALSE
GROUP BY payment_token, swap_status;

-- Comments for documentation
COMMENT ON TABLE transactions IS 'Main transaction table for CIRX OTC swaps';
COMMENT ON COLUMN transactions.payment_tx_id IS 'Blockchain transaction hash for incoming payment';
COMMENT ON COLUMN transactions.payment_chain IS 'Blockchain network (ethereum, polygon, etc.)';
COMMENT ON COLUMN transactions.cirx_recipient_address IS 'Circular Protocol address to receive CIRX tokens';
COMMENT ON COLUMN transactions.sender_address IS 'Address that sent the payment';
COMMENT ON COLUMN transactions.amount_paid IS 'Amount of tokens paid (ETH, USDC, USDT)';
COMMENT ON COLUMN transactions.payment_token IS 'Token used for payment (ETH, USDC, USDT)';
COMMENT ON COLUMN transactions.swap_status IS 'Current status of the swap transaction';
COMMENT ON COLUMN transactions.cirx_transfer_tx_id IS 'Circular Protocol transaction ID for CIRX transfer';
COMMENT ON COLUMN transactions.failure_reason IS 'Reason for failure if transaction failed';
COMMENT ON COLUMN transactions.is_test_transaction IS 'Flag for test/demo transactions';