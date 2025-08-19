<?php

namespace App\Controllers;

use App\Models\Transaction;
use App\Services\LoggerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Transaction Status Controller
 * 
 * Provides real-time transaction status updates for frontend polling
 */
class TransactionStatusController
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerService::getLogger('transaction_status');
    }

    /**
     * Get all transactions in table format for status dashboard
     */
    public function getAllTransactionsTable(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $limit = (int)($queryParams['limit'] ?? 50);
            $offset = (int)($queryParams['offset'] ?? 0);
            $status = $queryParams['status'] ?? null;

            $query = Transaction::orderBy('created_at', 'desc');
            
            if ($status) {
                $query->where('swap_status', $status);
            }
            
            $transactions = $query->offset($offset)->limit($limit)->get();
            $totalCount = Transaction::count();
            
            $tableData = $transactions->map(function ($transaction) {
                return $this->buildTableRow($transaction);
            });

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'transactions' => $tableData,
                    'pagination' => [
                        'total' => $totalCount,
                        'limit' => $limit,
                        'offset' => $offset,
                        'hasMore' => ($offset + $limit) < $totalCount
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get transactions table', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Get transaction status by ID
     */
    public function getStatus(Request $request, Response $response, array $args): Response
    {
        $transactionId = $args['id'] ?? '';
        
        try {
            $transaction = Transaction::find($transactionId);
            
            if (!$transaction) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => 'Transaction not found',
                    'code' => 'TRANSACTION_NOT_FOUND'
                ], 404);
            }

            $statusData = $this->buildStatusResponse($transaction);
            
            $this->logger->info('Transaction status retrieved', [
                'transaction_id' => $transactionId,
                'status' => $transaction->swap_status,
                'phase' => $statusData['phase']
            ]);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $statusData
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get transaction status', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Build comprehensive status response
     */
    private function buildStatusResponse(Transaction $transaction): array
    {
        $phase = $this->getTransactionPhase($transaction->swap_status);
        $progress = $this->getProgressPercentage($transaction->swap_status);
        
        return [
            'transaction_id' => $transaction->id,
            'status' => $transaction->swap_status,
            'phase' => $phase,
            'progress' => $progress,
            'message' => $this->getStatusMessage($transaction->swap_status),
            'payment_info' => [
                'payment_tx_id' => $transaction->payment_tx_id,
                'payment_chain' => $transaction->payment_chain,
                'amount_paid' => $transaction->amount_paid,
                'payment_token' => $transaction->payment_token,
            ],
            'recipient_info' => [
                'cirx_recipient_address' => $transaction->cirx_recipient_address,
                'cirx_transfer_tx_id' => $transaction->cirx_transfer_tx_id,
            ],
            'timestamps' => [
                'created_at' => $transaction->created_at?->toISOString(),
                'updated_at' => $transaction->updated_at?->toISOString(),
                'last_retry_at' => $transaction->last_retry_at?->toISOString(),
            ],
            'failure_info' => $transaction->failure_reason ? [
                'reason' => $transaction->failure_reason,
                'retry_count' => $transaction->retry_count ?? 0,
            ] : null,
            'estimated_completion' => $this->getEstimatedCompletion($transaction->swap_status),
        ];
    }

    /**
     * Get user-friendly transaction phase
     */
    private function getTransactionPhase(string $status): string
    {
        return match ($status) {
            Transaction::STATUS_INITIATED => 'initiated',
            Transaction::STATUS_PAYMENT_PENDING => 'awaiting_payment',
            Transaction::STATUS_PENDING_PAYMENT_VERIFICATION => 'verifying_payment',
            Transaction::STATUS_PAYMENT_VERIFIED => 'payment_confirmed',
            Transaction::STATUS_TRANSFER_PENDING,
            Transaction::STATUS_CIRX_TRANSFER_PENDING => 'preparing_transfer',
            Transaction::STATUS_CIRX_TRANSFER_INITIATED,
            Transaction::STATUS_TRANSFER_INITIATED => 'transferring_cirx',
            Transaction::STATUS_COMPLETED => 'completed',
            Transaction::STATUS_FAILED_PAYMENT_VERIFICATION => 'payment_failed',
            Transaction::STATUS_FAILED_CIRX_TRANSFER => 'transfer_failed',
            default => 'unknown'
        };
    }

    /**
     * Get progress percentage (0-100)
     */
    private function getProgressPercentage(string $status): int
    {
        return match ($status) {
            Transaction::STATUS_INITIATED => 10,
            Transaction::STATUS_PAYMENT_PENDING => 20,
            Transaction::STATUS_PENDING_PAYMENT_VERIFICATION => 40,
            Transaction::STATUS_PAYMENT_VERIFIED => 60,
            Transaction::STATUS_TRANSFER_PENDING,
            Transaction::STATUS_CIRX_TRANSFER_PENDING => 70,
            Transaction::STATUS_CIRX_TRANSFER_INITIATED,
            Transaction::STATUS_TRANSFER_INITIATED => 85,
            Transaction::STATUS_COMPLETED => 100,
            Transaction::STATUS_FAILED_PAYMENT_VERIFICATION,
            Transaction::STATUS_FAILED_CIRX_TRANSFER => 0, // Reset to 0 for failed states
            default => 0
        };
    }

    /**
     * Get user-friendly status message
     */
    private function getStatusMessage(string $status): string
    {
        return match ($status) {
            Transaction::STATUS_INITIATED => 'Transaction initiated. Please complete your payment.',
            Transaction::STATUS_PAYMENT_PENDING => 'Waiting for your payment to be sent.',
            Transaction::STATUS_PENDING_PAYMENT_VERIFICATION => 'Verifying your payment on the blockchain...',
            Transaction::STATUS_PAYMENT_VERIFIED => 'Payment confirmed! Preparing CIRX transfer...',
            Transaction::STATUS_TRANSFER_PENDING,
            Transaction::STATUS_CIRX_TRANSFER_PENDING => 'Preparing to send CIRX tokens to your address...',
            Transaction::STATUS_CIRX_TRANSFER_INITIATED,
            Transaction::STATUS_TRANSFER_INITIATED => 'Sending CIRX tokens to your address...',
            Transaction::STATUS_COMPLETED => 'Transaction completed! CIRX tokens have been sent.',
            Transaction::STATUS_FAILED_PAYMENT_VERIFICATION => 'Payment verification failed. Please check your transaction.',
            Transaction::STATUS_FAILED_CIRX_TRANSFER => 'CIRX transfer failed. Our team has been notified.',
            default => 'Unknown transaction status'
        };
    }

    /**
     * Get estimated completion time (in minutes)
     */
    private function getEstimatedCompletion(string $status): ?int
    {
        return match ($status) {
            Transaction::STATUS_INITIATED,
            Transaction::STATUS_PAYMENT_PENDING => null, // Depends on user action
            Transaction::STATUS_PENDING_PAYMENT_VERIFICATION => 5,
            Transaction::STATUS_PAYMENT_VERIFIED,
            Transaction::STATUS_TRANSFER_PENDING,
            Transaction::STATUS_CIRX_TRANSFER_PENDING => 3,
            Transaction::STATUS_CIRX_TRANSFER_INITIATED,
            Transaction::STATUS_TRANSFER_INITIATED => 2,
            Transaction::STATUS_COMPLETED => 0,
            default => null
        };
    }

    /**
     * Build a single table row for transaction display
     */
    private function buildTableRow(Transaction $transaction): array
    {
        // Generate Etherscan URL for payment transaction
        $etherscanUrl = null;
        if ($transaction->payment_tx_id && $transaction->payment_chain) {
            $etherscanUrl = $this->getEtherscanUrl($transaction->payment_tx_id, $transaction->payment_chain);
        }

        // Generate Circular explorer URL for CIRX transaction
        $circularExplorerUrl = null;
        if ($transaction->cirx_transfer_tx_id) {
            $circularExplorerUrl = $this->getCircularExplorerUrl($transaction->cirx_transfer_tx_id);
        }

        // Calculate CIRX amount (with platform fee deducted)
        $grossCirx = $this->calculateGrossCirxAmount($transaction);
        $netCirx = max(0, $grossCirx - 4.0); // Subtract 4 CIRX platform fee

        return [
            'id' => $transaction->id,
            'created_at' => $transaction->created_at?->toISOString(),
            'status' => $transaction->swap_status,
            'status_display' => $this->getStatusDisplayName($transaction->swap_status),
            'is_test_transaction' => $transaction->is_test_transaction ?? false,
            'payment' => [
                'tx_hash' => $transaction->payment_tx_id,
                'chain' => $transaction->payment_chain,
                'amount' => $transaction->amount_paid,
                'token' => $transaction->payment_token,
                'etherscan_url' => $etherscanUrl
            ],
            'cirx' => [
                'tx_hash' => $transaction->cirx_transfer_tx_id,
                'recipient' => $transaction->cirx_recipient_address,
                'amount' => number_format($netCirx, 1, '.', ''),
                'circular_explorer_url' => $circularExplorerUrl
            ],
            'progress' => $this->getProgressPercentage($transaction->swap_status),
            'retry_info' => [
                'retry_count' => $transaction->retry_count ?? 0,
                'last_retry_at' => $transaction->last_retry_at
            ]
        ];
    }

    /**
     * Get Etherscan URL for a transaction hash
     */
    private function getEtherscanUrl(string $txHash, string $chain): string
    {
        $baseUrls = [
            'ethereum' => 'https://etherscan.io/tx/',
            'eth' => 'https://etherscan.io/tx/',
            'mainnet' => 'https://etherscan.io/tx/',
            'sepolia' => 'https://sepolia.etherscan.io/tx/',
            'goerli' => 'https://goerli.etherscan.io/tx/',
            'polygon' => 'https://polygonscan.com/tx/',
            'matic' => 'https://polygonscan.com/tx/',
            'bsc' => 'https://bscscan.com/tx/',
            'binance' => 'https://bscscan.com/tx/',
            'arbitrum' => 'https://arbiscan.io/tx/',
            'optimism' => 'https://optimistic.etherscan.io/tx/',
            'avalanche' => 'https://snowtrace.io/tx/',
            'avax' => 'https://snowtrace.io/tx/'
        ];

        $chainKey = strtolower($chain);
        $baseUrl = $baseUrls[$chainKey] ?? $baseUrls['ethereum']; // Default to Ethereum

        return $baseUrl . $txHash;
    }

    /**
     * Get Circular Protocol explorer URL for a transaction hash
     */
    private function getCircularExplorerUrl(string $txHash): string
    {
        // Determine environment for correct explorer
        $environment = $_ENV['APP_ENV'] ?? 'development';
        
        switch ($environment) {
            case 'production':
                return "https://explorer.circular.net/tx/{$txHash}";
            case 'staging':
                return "https://staging-explorer.circular.net/tx/{$txHash}";
            default:
                return "https://sandbox-explorer.circular.net/tx/{$txHash}";
        }
    }

    /**
     * Get user-friendly status display name
     */
    private function getStatusDisplayName(string $status): string
    {
        return match ($status) {
            Transaction::STATUS_INITIATED => 'Initiated',
            Transaction::STATUS_PAYMENT_PENDING => 'Awaiting Payment',
            Transaction::STATUS_PENDING_PAYMENT_VERIFICATION => 'Verifying Payment',
            Transaction::STATUS_PAYMENT_VERIFIED => 'Payment Confirmed',
            Transaction::STATUS_TRANSFER_PENDING,
            Transaction::STATUS_CIRX_TRANSFER_PENDING => 'Preparing Transfer',
            Transaction::STATUS_CIRX_TRANSFER_INITIATED,
            Transaction::STATUS_TRANSFER_INITIATED => 'Transferring CIRX',
            Transaction::STATUS_COMPLETED => 'Completed',
            Transaction::STATUS_FAILED_PAYMENT_VERIFICATION => 'Payment Failed',
            Transaction::STATUS_FAILED_CIRX_TRANSFER => 'Transfer Failed',
            default => 'Unknown'
        };
    }

    /**
     * Calculate gross CIRX amount before platform fee deduction
     */
    private function calculateGrossCirxAmount(Transaction $transaction): float
    {
        // Convert payment to USD
        $tokenPrices = [
            'ETH' => 2700.0,   // $2,700 per ETH
            'USDC' => 1.0,     // $1 per USDC
            'USDT' => 1.0,     // $1 per USDT
            'SOL' => 100.0,    // $100 per SOL
            'BNB' => 300.0,    // $300 per BNB
            'MATIC' => 0.80,   // $0.80 per MATIC
        ];

        $paymentAmount = floatval($transaction->amount_paid);
        $tokenPrice = $tokenPrices[strtoupper($transaction->payment_token)] ?? 1.0;
        $usdAmount = $paymentAmount * $tokenPrice;
        
        // Base CIRX rate: $2.50 per CIRX
        $baseCirxAmount = $usdAmount / 2.5;
        
        // Apply discount for large amounts (OTC logic)
        if ($usdAmount >= 50000) {
            $baseCirxAmount *= 1.12; // 12% discount
        } elseif ($usdAmount >= 10000) {
            $baseCirxAmount *= 1.08; // 8% discount
        } elseif ($usdAmount >= 1000) {
            $baseCirxAmount *= 1.05; // 5% discount
        }
        
        return $baseCirxAmount;
    }

    /**
     * Helper method to format JSON response
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}