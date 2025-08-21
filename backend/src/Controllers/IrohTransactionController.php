<?php

namespace App\Controllers;

use App\Models\Transaction;
use App\Services\IrohServiceBridge;
use App\Services\LoggerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * IROH-enabled Transaction Controller
 * 
 * Extends transaction functionality with real-time updates via IROH networking
 */
class IrohTransactionController
{
    private IrohServiceBridge $irohBridge;

    public function __construct(?IrohServiceBridge $irohBridge = null)
    {
        $this->irohBridge = $irohBridge ?? new IrohServiceBridge();
    }

    /**
     * Get transaction status with real-time IROH integration
     */
    public function getTransactionStatusWithUpdates(Request $request, Response $response, array $args): Response
    {
        try {
            $transactionId = $args['id'] ?? null;
            
            if (!$transactionId) {
                return $this->errorResponse($response, 400, 'Transaction ID is required');
            }

            $transaction = Transaction::find($transactionId);
            if (!$transaction) {
                return $this->errorResponse($response, 404, 'Transaction not found');
            }

            // Get basic transaction data
            $responseData = [
                'transaction_id' => $transaction->id,
                'status' => $transaction->swap_status,
                'payment_tx_id' => $transaction->payment_tx_id,
                'amount_paid' => $transaction->amount_paid,
                'payment_token' => $transaction->payment_token,
                'cirx_recipient_address' => $transaction->cirx_recipient_address,
                'created_at' => $transaction->created_at?->toISOString(),
                'updated_at' => $transaction->updated_at?->toISOString(),
            ];

            // Add CIRX transfer info if available
            if ($transaction->cirx_transfer_tx_id) {
                $responseData['cirx_transfer_tx_id'] = $transaction->cirx_transfer_tx_id;
            }

            // Add IROH network information if available
            if ($this->irohBridge->isEnabled()) {
                $responseData['iroh'] = [
                    'node_id' => $this->irohBridge->getNodeId(),
                    'real_time_updates' => true,
                    'network_status' => 'connected'
                ];

                // Broadcast status request to get latest updates
                $this->broadcastStatusRequest($transactionId);
            } else {
                $responseData['iroh'] = [
                    'enabled' => false,
                    'real_time_updates' => false
                ];
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $responseData,
                'timestamp' => time()
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            LoggerService::getLogger('iroh')->error('Failed to get transaction status', [
                'transaction_id' => $transactionId ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse($response, 500, 'Failed to retrieve transaction status');
        }
    }

    /**
     * Broadcast transaction status update to the network
     */
    public function broadcastTransactionUpdate(string $transactionId, string $status, array $metadata = []): bool
    {
        if (!$this->irohBridge->isEnabled()) {
            return false;
        }

        try {
            return $this->irohBridge->broadcastTransactionUpdate($transactionId, $status, $metadata);
        } catch (\Exception $e) {
            LoggerService::getLogger('iroh')->error('Failed to broadcast transaction update', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Notify network of successful CIRX transfer
     */
    public function notifyCirxTransfer(string $transactionId, string $recipient, string $amount, string $txHash): bool
    {
        if (!$this->irohBridge->isEnabled()) {
            return false;
        }

        try {
            return $this->irohBridge->broadcastCirxTransfer($transactionId, $recipient, $amount, $txHash);
        } catch (\Exception $e) {
            LoggerService::getLogger('iroh')->error('Failed to notify CIRX transfer', [
                'transaction_id' => $transactionId,
                'recipient' => $recipient,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get network information and discovered services
     */
    public function getNetworkStatus(Request $request, Response $response): Response
    {
        try {
            if (!$this->irohBridge->isEnabled()) {
                $responseData = [
                    'iroh_enabled' => false,
                    'message' => 'IROH networking is not enabled'
                ];
            } else {
                $networkInfo = $this->irohBridge->getNetworkInfo();
                $discoveredServices = $this->irohBridge->getDiscoveredServices();

                $responseData = [
                    'iroh_enabled' => true,
                    'node_id' => $this->irohBridge->getNodeId(),
                    'network_info' => $networkInfo,
                    'discovered_services' => $discoveredServices,
                    'health_status' => $this->irohBridge->isHealthy() ? 'healthy' : 'unhealthy'
                ];
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $responseData,
                'timestamp' => time()
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            LoggerService::getLogger('iroh')->error('Failed to get network status', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 500, 'Failed to retrieve network status');
        }
    }

    /**
     * Discover and connect to other CIRX swap services on the network
     */
    public function discoverPeers(Request $request, Response $response): Response
    {
        try {
            if (!$this->irohBridge->isEnabled()) {
                return $this->errorResponse($response, 503, 'IROH networking is not enabled');
            }

            // Discover other CIRX swap backend services
            $backendServices = $this->irohBridge->discoverServices('cirx-swap-backend', [
                'swap-execution',
                'payment-verification'
            ]);

            // Discover frontend services if any
            $frontendServices = $this->irohBridge->discoverServices('cirx-swap-frontend', [
                'user-interface'
            ]);

            $responseData = [
                'backend_services' => $backendServices,
                'frontend_services' => $frontendServices,
                'discovery_timestamp' => time()
            ];

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $responseData,
                'timestamp' => time()
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            LoggerService::getLogger('iroh')->error('Failed to discover peers', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, 500, 'Failed to discover peer services');
        }
    }

    /**
     * Handle real-time transaction updates from the network
     */
    public function handleNetworkUpdate(array $updateData): bool
    {
        try {
            $transactionId = $updateData['transaction_id'] ?? null;
            $status = $updateData['status'] ?? null;
            $sourceNodeId = $updateData['node_id'] ?? null;

            if (!$transactionId || !$status) {
                return false;
            }

            // Don't process updates from our own node
            if ($sourceNodeId === $this->irohBridge->getNodeId()) {
                return false;
            }

            $transaction = Transaction::find($transactionId);
            if (!$transaction) {
                LoggerService::getLogger('iroh')->warning('Received update for unknown transaction', [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                    'source_node' => $sourceNodeId
                ]);
                return false;
            }

            // Update transaction if the status is newer/different
            if ($transaction->swap_status !== $status) {
                $oldStatus = $transaction->swap_status;
                $transaction->update(['swap_status' => $status]);

                LoggerService::getLogger('iroh')->info('Updated transaction from network', [
                    'transaction_id' => $transactionId,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                    'source_node' => $sourceNodeId
                ]);
            }

            return true;

        } catch (\Exception $e) {
            LoggerService::getLogger('iroh')->error('Failed to handle network update', [
                'update_data' => $updateData,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Broadcast a status request to get latest transaction info from network
     */
    private function broadcastStatusRequest(string $transactionId): void
    {
        try {
            $this->irohBridge->broadcastToTopic('transaction-updates', [
                'type' => 'TRANSACTION_STATUS_REQUEST',
                'transaction_id' => $transactionId,
                'requesting_node' => $this->irohBridge->getNodeId(),
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            LoggerService::getLogger('iroh')->debug('Failed to broadcast status request', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create error response
     */
    private function errorResponse(Response $response, int $statusCode, string $message): Response
    {
        $data = [
            'success' => false,
            'error' => $message,
            'timestamp' => time()
        ];

        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}