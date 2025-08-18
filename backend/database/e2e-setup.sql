-- E2E Testing Database Setup
-- This script initializes the database for E2E testing

-- Create database if it doesn't exist
SELECT 'CREATE DATABASE cirx_e2e_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'cirx_e2e_test')\gexec

-- Connect to the E2E test database
\c cirx_e2e_test;

-- Create necessary extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Create tables for E2E testing
CREATE TABLE IF NOT EXISTS transactions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    transaction_id VARCHAR(255) UNIQUE NOT NULL,
    payment_chain VARCHAR(50) NOT NULL,
    payment_token VARCHAR(10) NOT NULL,
    payment_amount DECIMAL(20, 8) NOT NULL,
    payment_address VARCHAR(255) NOT NULL,
    cirx_recipient_address VARCHAR(255) NOT NULL,
    cirx_amount DECIMAL(20, 8) NOT NULL,
    discount_percentage INTEGER DEFAULT 0,
    swap_type VARCHAR(20) DEFAULT 'liquid',
    status VARCHAR(50) DEFAULT 'pending_payment_verification',
    payment_tx_hash VARCHAR(255),
    payment_received BOOLEAN DEFAULT FALSE,
    payment_verified_at TIMESTAMP,
    cirx_transferred BOOLEAN DEFAULT FALSE,
    cirx_transfer_tx_hash VARCHAR(255),
    cirx_transferred_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create payment tracking table
CREATE TABLE IF NOT EXISTS payment_tracking (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    transaction_id VARCHAR(255) NOT NULL,
    blockchain_tx_hash VARCHAR(255) NOT NULL,
    block_number BIGINT,
    confirmations INTEGER DEFAULT 0,
    amount_received DECIMAL(20, 8),
    token_received VARCHAR(10),
    verified BOOLEAN DEFAULT FALSE,
    verification_attempts INTEGER DEFAULT 0,
    last_verification_attempt TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id)
);

-- Create CIRX transfer tracking table
CREATE TABLE IF NOT EXISTS cirx_transfers (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    transaction_id VARCHAR(255) NOT NULL,
    recipient_address VARCHAR(255) NOT NULL,
    amount DECIMAL(20, 8) NOT NULL,
    transfer_tx_hash VARCHAR(255),
    status VARCHAR(50) DEFAULT 'pending',
    attempts INTEGER DEFAULT 0,
    last_attempt TIMESTAMP,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id)
);

-- Create worker job tracking table
CREATE TABLE IF NOT EXISTS worker_jobs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    job_type VARCHAR(100) NOT NULL,
    reference_id VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    attempts INTEGER DEFAULT 0,
    max_attempts INTEGER DEFAULT 3,
    payload JSON,
    error_message TEXT,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON transactions(created_at);
CREATE INDEX IF NOT EXISTS idx_payment_tracking_tx_hash ON payment_tracking(blockchain_tx_hash);
CREATE INDEX IF NOT EXISTS idx_payment_tracking_verified ON payment_tracking(verified);
CREATE INDEX IF NOT EXISTS idx_cirx_transfers_status ON cirx_transfers(status);
CREATE INDEX IF NOT EXISTS idx_worker_jobs_status ON worker_jobs(status);
CREATE INDEX IF NOT EXISTS idx_worker_jobs_scheduled ON worker_jobs(scheduled_at);

-- Insert test data for E2E testing
INSERT INTO transactions (
    transaction_id,
    payment_chain,
    payment_token,
    payment_amount,
    payment_address,
    cirx_recipient_address,
    cirx_amount,
    discount_percentage,
    swap_type,
    status
) VALUES 
(
    'e2e-test-tx-001',
    'sepolia',
    'ETH',
    0.001,
    '0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3',
    '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
    2.16,
    0,
    'liquid',
    'pending_payment_verification'
),
(
    'e2e-test-tx-002',
    'sepolia',
    'USDC',
    1000.00,
    '0x742d35Cc6635C0532925a3b8D10C6c2EE5c2B9a3',
    '0xbb9dbe8b94ae940016e89837574e84e2651f7f10da7809fff0728cc419514370',
    2268.00,
    5,
    'otc',
    'pending_payment_verification'
);

-- Create test user sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    session_id VARCHAR(255) UNIQUE NOT NULL,
    user_identifier VARCHAR(255) NOT NULL,
    wallet_address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL '2 hours'),
    data JSON
);

-- Create API rate limiting table
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    identifier VARCHAR(255) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    attempts INTEGER DEFAULT 1,
    reset_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL '1 hour'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(identifier, endpoint)
);

-- Create test configuration table
CREATE TABLE IF NOT EXISTS e2e_config (
    key VARCHAR(255) PRIMARY KEY,
    value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert E2E configuration
INSERT INTO e2e_config (key, value, description) VALUES
('test_environment', 'e2e', 'Identifies this as E2E test environment'),
('blockchain_confirmations_required', '3', 'Number of confirmations needed for payment verification'),
('payment_timeout_minutes', '60', 'Maximum time to wait for payment before timeout'),
('cirx_transfer_timeout_minutes', '30', 'Maximum time to wait for CIRX transfer before timeout'),
('worker_batch_size', '10', 'Number of items to process in each worker batch'),
('rate_limit_max_requests', '100', 'Maximum requests per hour for rate limiting'),
('test_wallet_funding_threshold', '0.01', 'Minimum ETH balance required for test wallets');

-- Create function to update updated_at timestamps
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at
CREATE TRIGGER update_transactions_updated_at 
    BEFORE UPDATE ON transactions 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_e2e_config_updated_at 
    BEFORE UPDATE ON e2e_config 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Grant permissions to test user
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO cirx_test;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO cirx_test;

-- Create views for test monitoring
CREATE OR REPLACE VIEW transaction_summary AS
SELECT 
    status,
    COUNT(*) as count,
    AVG(EXTRACT(EPOCH FROM (updated_at - created_at))) as avg_processing_time_seconds
FROM transactions 
GROUP BY status;

CREATE OR REPLACE VIEW payment_verification_summary AS
SELECT 
    verified,
    COUNT(*) as count,
    AVG(verification_attempts) as avg_attempts
FROM payment_tracking 
GROUP BY verified;

CREATE OR REPLACE VIEW worker_job_summary AS
SELECT 
    job_type,
    status,
    COUNT(*) as count,
    AVG(attempts) as avg_attempts
FROM worker_jobs 
GROUP BY job_type, status;

-- Grant view permissions
GRANT SELECT ON transaction_summary TO cirx_test;
GRANT SELECT ON payment_verification_summary TO cirx_test;
GRANT SELECT ON worker_job_summary TO cirx_test;

-- Create cleanup procedure for test data
CREATE OR REPLACE FUNCTION cleanup_e2e_test_data()
RETURNS void AS $$
BEGIN
    DELETE FROM cirx_transfers WHERE transaction_id LIKE 'e2e-test-%';
    DELETE FROM payment_tracking WHERE transaction_id LIKE 'e2e-test-%';
    DELETE FROM transactions WHERE transaction_id LIKE 'e2e-test-%';
    DELETE FROM worker_jobs WHERE reference_id LIKE 'e2e-test-%';
    DELETE FROM user_sessions WHERE created_at < CURRENT_TIMESTAMP - INTERVAL '1 day';
    DELETE FROM api_rate_limits WHERE reset_at < CURRENT_TIMESTAMP;
END;
$$ LANGUAGE plpgsql;

-- Grant execute permission on cleanup function
GRANT EXECUTE ON FUNCTION cleanup_e2e_test_data() TO cirx_test;

-- Log successful setup
INSERT INTO e2e_config (key, value, description) VALUES
('setup_completed_at', EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)::text, 'Timestamp when E2E database setup was completed')
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = CURRENT_TIMESTAMP;

COMMIT;