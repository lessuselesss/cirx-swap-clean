-- Clear existing transactions
DELETE FROM transactions;

-- Insert obvious demo transactions with different statuses and standout amounts
INSERT INTO transactions (
    id,
    payment_tx_id,
    payment_chain,
    cirx_recipient_address,
    amount_paid,
    payment_token,
    swap_status,
    cirx_transfer_tx_id,
    failure_reason,
    created_at,
    updated_at
) VALUES 
-- 1. DEMO: Payment pending verification
(
    '11111111-1111-1111-1111-111111111111',
    '0xdemo1111111111111111111111111111111111111111111111111111111111111111',
    'ethereum',
    '0xDEMO1111111111111111111111111111111111111111111111111111111111111111',
    '1.111000000000000000',
    'ETH',
    'pending_payment_verification',
    NULL,
    NULL,
    datetime('now', '-2 hours'),
    datetime('now', '-2 hours')
),
-- 2. DEMO: Awaiting payment verification (stuck in verification)
(
    '22222222-2222-2222-2222-222222222222',
    '0xdemo2222222222222222222222222222222222222222222222222222222222222222',
    'ethereum',
    '0xDEMO2222222222222222222222222222222222222222222222222222222222222222',
    '2.222000000000000000',
    'ETH',
    'pending_payment_verification',
    NULL,
    NULL,
    datetime('now', '-1 hour'),
    datetime('now', '-1 hour')
),
-- 3. DEMO: Payment verified, preparing transfer
(
    '33333333-3333-3333-3333-333333333333',
    '0xdemo3333333333333333333333333333333333333333333333333333333333333333',
    'ethereum',
    '0xDEMO3333333333333333333333333333333333333333333333333333333333333333',
    '3.333000000000000000',
    'ETH',
    'payment_verified',
    NULL,
    NULL,
    datetime('now', '-30 minutes'),
    datetime('now', '-30 minutes')
),
-- 4. DEMO: CIRX transfer pending
(
    '44444444-4444-4444-4444-444444444444',
    '0xdemo4444444444444444444444444444444444444444444444444444444444444444',
    'ethereum',
    '0xDEMO4444444444444444444444444444444444444444444444444444444444444444',
    '4.444000000000000000',
    'ETH',
    'cirx_transfer_pending',
    NULL,
    NULL,
    datetime('now', '-15 minutes'),
    datetime('now', '-15 minutes')
),
-- 5. DEMO: CIRX transfer initiated (almost done)
(
    '55555555-5555-5555-5555-555555555555',
    '0xdemo5555555555555555555555555555555555555555555555555555555555555555',
    'ethereum',
    '0xDEMO5555555555555555555555555555555555555555555555555555555555555555',
    '5.555000000000000000',
    'ETH',
    'cirx_transfer_initiated',
    '0xCIRX5555555555555555555555555555555555555555555555555555555555555555',
    NULL,
    datetime('now', '-5 minutes'),
    datetime('now', '-5 minutes')
),
-- 6. DEMO: Completed transaction
(
    '66666666-6666-6666-6666-666666666666',
    '0xdemo6666666666666666666666666666666666666666666666666666666666666666',
    'ethereum',
    '0xDEMO6666666666666666666666666666666666666666666666666666666666666666',
    '6.666000000000000000',
    'ETH',
    'completed',
    '0xCIRX6666666666666666666666666666666666666666666666666666666666666666',
    NULL,
    datetime('now', '-1 minute'),
    datetime('now', '-1 minute')
),
-- 7. DEMO: Failed payment verification
(
    '77777777-7777-7777-7777-777777777777',
    '0xdemo7777777777777777777777777777777777777777777777777777777777777777',
    'ethereum',
    '0xDEMO7777777777777777777777777777777777777777777777777777777777777777',
    '7.777000000000000000',
    'ETH',
    'failed_payment_verification',
    NULL,
    'Payment transaction not found on blockchain after 24 hours',
    datetime('now', '-3 hours'),
    datetime('now', '-3 hours')
),
-- 8. DEMO: Failed CIRX transfer
(
    '88888888-8888-8888-8888-888888888888',
    '0xdemo8888888888888888888888888888888888888888888888888888888888888888',
    'ethereum',
    '0xDEMO8888888888888888888888888888888888888888888888888888888888888888',
    '8.888000000000000000',
    'ETH',
    'failed_cirx_transfer',
    NULL,
    'Insufficient CIRX balance in platform wallet',
    datetime('now', '-4 hours'),
    datetime('now', '-4 hours')
),
-- 9. DEMO: Large USDC transaction (completed)
(
    '99999999-9999-9999-9999-999999999999',
    '0xdemo9999999999999999999999999999999999999999999999999999999999999999',
    'ethereum',
    '0xDEMO9999999999999999999999999999999999999999999999999999999999999999',
    '9999.990000000000000000',
    'USDC',
    'completed',
    '0xCIRX9999999999999999999999999999999999999999999999999999999999999999',
    NULL,
    datetime('now', '-6 hours'),
    datetime('now', '-6 hours')
),
-- 10. DEMO: Small USDT transaction (pending verification)
(
    '00000000-0000-0000-0000-000000000000',
    '0xdemo0000000000000000000000000000000000000000000000000000000000000000',
    'ethereum',
    '0xDEMO0000000000000000000000000000000000000000000000000000000000000000',
    '100.000000000000000000',
    'USDT',
    'pending_payment_verification',
    NULL,
    NULL,
    datetime('now', '-10 minutes'),
    datetime('now', '-10 minutes')
);