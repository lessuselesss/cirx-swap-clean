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