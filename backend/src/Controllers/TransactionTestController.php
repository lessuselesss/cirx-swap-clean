<?php

namespace App\Controllers;

use App\Models\Transaction;
use App\Services\LoggerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Transaction Test Controller
 * 
 * Creates demo transactions for testing the status tracking system
 */
class TransactionTestController
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerService::getLogger('transaction_test');
    }

    /**
     * Create a demo transaction for testing
     */
    public function createDemoTransaction(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $phase = $data['phase'] ?? 'initiated';
            
            // Create a demo transaction
            $transactionId = Uuid::uuid4()->toString();
            $transaction = Transaction::create([
                'id' => $transactionId,
                'payment_tx_id' => '0x' . bin2hex(random_bytes(32)),
                'payment_chain' => 'ethereum',
                'cirx_recipient_address' => '0x' . bin2hex(random_bytes(32)),
                'amount_paid' => '1000.00',
                'payment_token' => 'USDC',
                'swap_status' => $this->getStatusFromPhase($phase),
                'retry_count' => 0,
                'is_test_transaction' => true,
            ]);

            $this->logger->info('Demo transaction created', [
                'transaction_id' => $transactionId,
                'phase' => $phase,
                'status' => $transaction->swap_status
            ]);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'phase' => $phase,
                    'status' => $transaction->swap_status,
                    'message' => 'Demo transaction created successfully'
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create demo transaction', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Failed to create demo transaction',
                'code' => 'DEMO_CREATE_ERROR'
            ], 500);
        }
    }

    /**
     * Update demo transaction to next phase
     */
    public function updateDemoTransaction(Request $request, Response $response, array $args): Response
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

            // Get next phase
            $currentPhase = $this->getPhaseFromStatus($transaction->swap_status);
            $nextPhase = $this->getNextPhase($currentPhase);
            
            if ($nextPhase) {
                $transaction->swap_status = $this->getStatusFromPhase($nextPhase);
                $transaction->save();
                
                $this->logger->info('Demo transaction updated', [
                    'transaction_id' => $transactionId,
                    'from_phase' => $currentPhase,
                    'to_phase' => $nextPhase,
                    'status' => $transaction->swap_status
                ]);

                return $this->jsonResponse($response, [
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transactionId,
                        'previous_phase' => $currentPhase,
                        'current_phase' => $nextPhase,
                        'status' => $transaction->swap_status,
                        'message' => "Transaction moved from {$currentPhase} to {$nextPhase}"
                    ]
                ]);
            } else {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => 'Transaction is already at final phase',
                    'code' => 'ALREADY_FINAL'
                ], 400);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to update demo transaction', [
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
     * Get transaction status from phase
     */
    private function getStatusFromPhase(string $phase): string
    {
        return match ($phase) {
            'initiated' => Transaction::STATUS_INITIATED,
            'awaiting_payment' => Transaction::STATUS_PAYMENT_PENDING,
            'verifying_payment' => Transaction::STATUS_PENDING_PAYMENT_VERIFICATION,
            'payment_confirmed' => Transaction::STATUS_PAYMENT_VERIFIED,
            'preparing_transfer' => Transaction::STATUS_CIRX_TRANSFER_PENDING,
            'transferring_cirx' => Transaction::STATUS_CIRX_TRANSFER_INITIATED,
            'completed' => Transaction::STATUS_COMPLETED,
            'payment_failed' => Transaction::STATUS_FAILED_PAYMENT_VERIFICATION,
            'transfer_failed' => Transaction::STATUS_FAILED_CIRX_TRANSFER,
            default => Transaction::STATUS_INITIATED
        };
    }

    /**
     * Get phase from transaction status
     */
    private function getPhaseFromStatus(string $status): string
    {
        return match ($status) {
            Transaction::STATUS_INITIATED => 'initiated',
            Transaction::STATUS_PAYMENT_PENDING => 'awaiting_payment',
            Transaction::STATUS_PENDING_PAYMENT_VERIFICATION => 'verifying_payment',
            Transaction::STATUS_PAYMENT_VERIFIED => 'payment_confirmed',
            Transaction::STATUS_CIRX_TRANSFER_PENDING => 'preparing_transfer',
            Transaction::STATUS_CIRX_TRANSFER_INITIATED => 'transferring_cirx',
            Transaction::STATUS_COMPLETED => 'completed',
            Transaction::STATUS_FAILED_PAYMENT_VERIFICATION => 'payment_failed',
            Transaction::STATUS_FAILED_CIRX_TRANSFER => 'transfer_failed',
            default => 'unknown'
        };
    }

    /**
     * Get next phase in the flow
     */
    private function getNextPhase(string $currentPhase): ?string
    {
        $phases = [
            'initiated' => 'awaiting_payment',
            'awaiting_payment' => 'verifying_payment',
            'verifying_payment' => 'payment_confirmed',
            'payment_confirmed' => 'preparing_transfer',
            'preparing_transfer' => 'transferring_cirx',
            'transferring_cirx' => 'completed',
        ];

        return $phases[$currentPhase] ?? null;
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