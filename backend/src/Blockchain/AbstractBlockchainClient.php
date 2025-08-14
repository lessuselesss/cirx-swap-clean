<?php

namespace App\Blockchain;

use App\Blockchain\Exceptions\BlockchainException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Abstract Blockchain Client
 * 
 * Provides common functionality for all blockchain clients
 */
abstract class AbstractBlockchainClient implements BlockchainClientInterface
{
    protected Client $httpClient;
    protected LoggerInterface $logger;
    protected string $rpcUrl;
    protected ?string $backupRpcUrl;
    protected int $timeout;
    protected int $retryAttempts;
    protected float $retryDelay;

    public function __construct(
        string $rpcUrl,
        ?string $backupRpcUrl = null,
        ?LoggerInterface $logger = null,
        int $timeout = 30,
        int $retryAttempts = 3,
        float $retryDelay = 1.0
    ) {
        $this->rpcUrl = $rpcUrl;
        $this->backupRpcUrl = $backupRpcUrl;
        $this->logger = $logger ?? new \Monolog\Logger('blockchain');
        $this->timeout = $timeout;
        $this->retryAttempts = $retryAttempts;
        $this->retryDelay = $retryDelay;

        $this->httpClient = new Client([
            'timeout' => $this->timeout,
            'connect_timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Execute JSON-RPC call with retry logic
     */
    protected function rpcCall(string $method, array $params = [], bool $useBackup = false): array
    {
        $url = $useBackup && $this->backupRpcUrl ? $this->backupRpcUrl : $this->rpcUrl;
        $requestId = uniqid();

        $payload = [
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => $method,
            'params' => $params
        ];

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryAttempts) {
            try {
                $this->logger->debug("RPC call attempt " . ($attempt + 1), [
                    'method' => $method,
                    'url' => $url,
                    'params' => $params
                ]);

                $response = $this->httpClient->post($url, [
                    'json' => $payload
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw BlockchainException::invalidResponse(
                        $this->getNetworkName(),
                        $method,
                        'Invalid JSON response: ' . json_last_error_msg()
                    );
                }

                if (isset($data['error'])) {
                    throw BlockchainException::invalidResponse(
                        $this->getNetworkName(),
                        $method,
                        'RPC error: ' . ($data['error']['message'] ?? 'Unknown error')
                    );
                }

                if (!isset($data['result'])) {
                    throw BlockchainException::invalidResponse(
                        $this->getNetworkName(),
                        $method,
                        'Missing result field in response'
                    );
                }

                $this->logger->debug("RPC call successful", [
                    'method' => $method,
                    'attempt' => $attempt + 1
                ]);

                return $data;

            } catch (GuzzleException $e) {
                $lastException = BlockchainException::connectionFailed(
                    $this->getNetworkName(),
                    $url,
                    $e->getMessage(),
                    $e
                );

                $this->logger->warning("RPC call failed", [
                    'method' => $method,
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                    'url' => $url
                ]);

                // Try backup URL on first failure
                if ($attempt === 0 && $this->backupRpcUrl && !$useBackup) {
                    $url = $this->backupRpcUrl;
                    $this->logger->info("Switching to backup RPC URL", ['url' => $url]);
                }

                $attempt++;
                if ($attempt < $this->retryAttempts) {
                    usleep((int)($this->retryDelay * 1000000 * $attempt)); // Exponential backoff
                }
            }
        }

        throw $lastException ?? new BlockchainException("Unknown error occurred during RPC call");
    }

    /**
     * Check if the blockchain client is healthy
     */
    public function isHealthy(): bool
    {
        try {
            $this->getBlockNumber();
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Health check failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Convert hex string to decimal string
     */
    protected function hexToDec(string $hex): string
    {
        if (str_starts_with($hex, '0x')) {
            $hex = substr($hex, 2);
        }
        return (string)hexdec($hex);
    }

    /**
     * Convert decimal to hex string
     */
    protected function decToHex(string $dec): string
    {
        return '0x' . dechex((int)$dec);
    }

    /**
     * Convert Wei to Ether (or similar 18-decimal conversion)
     */
    protected function weiToEther(string $wei): string
    {
        return bcdiv($wei, '1000000000000000000', 18);
    }

    /**
     * Convert Ether to Wei (or similar 18-decimal conversion)
     */
    protected function etherToWei(string $ether): string
    {
        return bcmul($ether, '1000000000000000000', 0);
    }

    /**
     * Parse token amount considering decimals
     */
    protected function parseTokenAmount(string $rawAmount, int $decimals): string
    {
        $divisor = bcpow('10', (string)$decimals, 0);
        return bcdiv($rawAmount, $divisor, $decimals);
    }

    /**
     * Format token amount for transaction (considering decimals)
     */
    protected function formatTokenAmount(string $amount, int $decimals): string
    {
        $multiplier = bcpow('10', (string)$decimals, 0);
        return bcmul($amount, $multiplier, 0);
    }
}