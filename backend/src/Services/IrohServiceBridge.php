<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * IROH Service Bridge
 * 
 * Provides PHP interface to communicate with the IROH bridge service
 * for distributed networking and real-time communication.
 */
class IrohServiceBridge
{
    private Client $httpClient;
    private string $bridgeUrl;
    private ?string $nodeId;
    private LoggerInterface $logger;
    private array $discoveredServices = [];

    public function __construct(
        ?string $bridgeUrl = null,
        ?Client $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        $this->bridgeUrl = $bridgeUrl ?? ($_ENV['IROH_BRIDGE_URL'] ?? 'http://localhost:9090');
        $this->httpClient = $httpClient ?? new Client(['timeout' => 30]);
        $this->logger = $logger ?? new \Psr\Log\NullLogger();
        $this->nodeId = null;
    }

    /**
     * Initialize connection to IROH bridge and get node information
     */
    public function initialize(): bool
    {
        try {
            $response = $this->httpClient->get($this->bridgeUrl . '/node/info');
            $data = json_decode($response->getBody()->getContents(), true);
            
            if ($data['success'] ?? false) {
                $this->nodeId = $data['data']['node_id'] ?? null;
                $this->logger->info('IROH bridge initialized', [
                    'node_id' => $this->nodeId,
                    'bridge_url' => $this->bridgeUrl
                ]);
                return true;
            }
            
            return false;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to initialize IROH bridge', [
                'error' => $e->getMessage(),
                'bridge_url' => $this->bridgeUrl
            ]);
            return false;
        }
    }

    /**
     * Check if IROH bridge is available and healthy
     */
    public function isHealthy(): bool
    {
        try {
            $response = $this->httpClient->get($this->bridgeUrl . '/health');
            $data = json_decode($response->getBody()->getContents(), true);
            return ($data['success'] ?? false) && ($data['data']['status'] ?? '') === 'healthy';
        } catch (GuzzleException $e) {
            $this->logger->debug('IROH bridge health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get current node ID
     */
    public function getNodeId(): ?string
    {
        if (!$this->nodeId && $this->initialize()) {
            return $this->nodeId;
        }
        return $this->nodeId;
    }

    /**
     * Discover services on the IROH network
     */
    public function discoverServices(string $serviceName, array $capabilities = [], int $maxResults = 10): array
    {
        try {
            $response = $this->httpClient->post($this->bridgeUrl . '/services/discover', [
                'json' => [
                    'service_name' => $serviceName,
                    'capabilities' => $capabilities,
                    'max_results' => $maxResults
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if ($data['success'] ?? false) {
                $services = $data['data'] ?? [];
                $this->discoveredServices[$serviceName] = $services;
                
                $this->logger->info('Discovered services', [
                    'service_name' => $serviceName,
                    'count' => count($services)
                ]);
                
                return $services;
            }
            
            return [];
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to discover services', [
                'service_name' => $serviceName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Send a direct message to another node
     */
    public function sendToNode(string $nodeId, array $payload): ?array
    {
        try {
            $response = $this->httpClient->post($this->bridgeUrl . "/send/{$nodeId}", [
                'json' => $payload
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if ($data['success'] ?? false) {
                return $data['data'] ?? null;
            }
            
            $this->logger->warning('Failed to send message to node', [
                'node_id' => $nodeId,
                'error' => $data['error'] ?? 'Unknown error'
            ]);
            
            return null;
        } catch (GuzzleException $e) {
            $this->logger->error('Error sending message to node', [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Broadcast a message to a specific topic
     */
    public function broadcastToTopic(string $topic, array $message): bool
    {
        try {
            $response = $this->httpClient->post($this->bridgeUrl . '/broadcast', [
                'json' => [
                    'topic' => $topic,
                    'message' => $message
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if ($data['success'] ?? false) {
                $this->logger->debug('Message broadcast successfully', [
                    'topic' => $topic,
                    'message_type' => $message['type'] ?? 'unknown'
                ]);
                return true;
            }
            
            return false;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to broadcast message', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Broadcast transaction status update
     */
    public function broadcastTransactionUpdate(string $transactionId, string $status, array $metadata = []): bool
    {
        $update = [
            'type' => 'TRANSACTION_STATUS_UPDATE',
            'transaction_id' => $transactionId,
            'status' => $status,
            'metadata' => $metadata,
            'timestamp' => time(),
            'node_id' => $this->getNodeId()
        ];

        return $this->broadcastToTopic('transaction-updates', $update);
    }

    /**
     * Broadcast CIRX transfer notification
     */
    public function broadcastCirxTransfer(string $transactionId, string $recipient, string $amount, string $txHash): bool
    {
        $notification = [
            'type' => 'CIRX_TRANSFER_COMPLETED',
            'transaction_id' => $transactionId,
            'recipient_address' => $recipient,
            'amount' => $amount,
            'tx_hash' => $txHash,
            'timestamp' => time(),
            'node_id' => $this->getNodeId()
        ];

        return $this->broadcastToTopic('cirx-notifications', $notification);
    }

    /**
     * Get information about discovered services
     */
    public function getDiscoveredServices(): array
    {
        return $this->discoveredServices;
    }

    /**
     * Register this backend service with the IROH network
     * Note: This is typically done by the IROH bridge on startup
     */
    public function registerBackendService(): bool
    {
        // This would be implemented by extending the IROH bridge
        // to accept service registration requests from the backend
        return true;
    }

    /**
     * Get network statistics and peer information
     */
    public function getNetworkInfo(): array
    {
        try {
            $response = $this->httpClient->get($this->bridgeUrl . '/node/peers');
            $data = json_decode($response->getBody()->getContents(), true);
            
            return $data['data'] ?? [];
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to get network info', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Check if IROH integration is enabled and available
     */
    public function isEnabled(): bool
    {
        $irohEnabled = ($_ENV['IROH_ENABLED'] ?? 'false') === 'true';
        return $irohEnabled && $this->isHealthy();
    }
}