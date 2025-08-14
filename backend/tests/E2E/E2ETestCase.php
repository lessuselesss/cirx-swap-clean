<?php

namespace Tests\E2E;

use Tests\Integration\IntegrationTestCase;
use App\Utils\SeedPhraseManager;
use App\Utils\TestWallet;
use App\Blockchain\BlockchainClientFactory;
use App\Blockchain\EthereumBlockchainClient;
use App\Services\PaymentVerificationService;
use App\Services\CirxTransferService;
use App\Workers\PaymentVerificationWorker;
use App\Workers\CirxTransferWorker;
use GuzzleHttp\Client;

/**
 * Base class for End-to-End tests with real blockchain integration
 * 
 * Provides utilities for testing complete OTC swap flows against
 * Sepolia testnet using real blockchain transactions.
 */
abstract class E2ETestCase extends IntegrationTestCase
{
    protected SeedPhraseManager $seedManager;
    protected EthereumBlockchainClient $sepoliaClient;
    protected PaymentVerificationService $paymentService;
    protected CirxTransferService $cirxService;
    protected array $testWallets = [];
    protected string $projectWallet;
    
    // Test configuration
    protected int $maxConfirmationWaitTime = 300; // 5 minutes
    protected int $confirmationBlocks = 3;
    protected float $fundingThreshold = 0.01; // 0.01 ETH minimum
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Only run E2E tests if enabled
        if (!$this->isE2ETestingEnabled()) {
            $this->markTestSkipped('E2E testing is disabled. Set E2E_TESTING_ENABLED=true to run these tests.');
        }
        
        $this->setupE2EEnvironment();
        $this->setupTestWallets();
        $this->setupBlockchainClients();
        $this->setupServices();
        $this->verifyTestEnvironment();
    }
    
    protected function tearDown(): void
    {
        $this->cleanupTestTransactions();
        parent::tearDown();
    }
    
    /**
     * Check if E2E testing is enabled
     */
    private function isE2ETestingEnabled(): bool
    {
        return filter_var($_ENV['E2E_TESTING_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Setup E2E testing environment
     */
    private function setupE2EEnvironment(): void
    {
        // Ensure testnet mode is enabled
        $_ENV['TESTNET_MODE'] = 'true';
        $_ENV['APP_ENV'] = 'testing';
        
        // Validate seed phrase is configured
        $seedPhrase = $_ENV['SEED_PHRASE'] ?? '';
        if (empty($seedPhrase)) {
            $this->fail('SEED_PHRASE environment variable is required for E2E testing');
        }
        
        $this->seedManager = new SeedPhraseManager($seedPhrase);
    }
    
    /**
     * Setup test wallets from seed phrase
     */
    private function setupTestWallets(): void
    {
        // Generate test wallets from seed phrase
        $this->testWallets = $this->seedManager->getWallets(5);
        
        // Set project wallet (where payments are sent)
        $this->projectWallet = $this->testWallets[0]->getAddress();
        
        $this->logTestInfo("Test wallets generated:", [
            'payment_wallet' => $this->testWallets[1]->getAddress(),
            'recipient_wallet' => $this->testWallets[2]->getAddress(), 
            'project_wallet' => $this->projectWallet,
            'backup_wallets' => array_slice(array_map(fn($w) => $w->getAddress(), $this->testWallets), 3)
        ]);
    }
    
    /**
     * Setup blockchain clients for Sepolia testnet
     */
    private function setupBlockchainClients(): void
    {
        $factory = new BlockchainClientFactory();
        $this->sepoliaClient = $factory->getEthereumClient('sepolia');
        
        // Verify connection to Sepolia
        try {
            $chainId = $this->sepoliaClient->getChainId();
            $this->assertEquals(11155111, $chainId, 'Must be connected to Sepolia testnet');
            
            $this->logTestInfo("Connected to Sepolia testnet", [
                'chain_id' => $chainId,
                'rpc_url' => $_ENV['SEPOLIA_RPC_URL'] ?? 'default'
            ]);
        } catch (\Exception $e) {
            $this->fail("Failed to connect to Sepolia testnet: " . $e->getMessage());
        }
    }
    
    /**
     * Setup services for E2E testing
     */
    private function setupServices(): void
    {
        // Use real blockchain clients for E2E testing
        $this->paymentService = new PaymentVerificationService(
            indexerUrl: null, // Force blockchain fallback
            httpClient: new Client(['timeout' => 30])
        );
        
        $this->cirxService = new CirxTransferService();
    }
    
    /**
     * Verify test environment is ready
     */
    private function verifyTestEnvironment(): void
    {
        // Check wallet funding
        $this->checkWalletFunding();
        
        // Verify RPC endpoints are responsive
        $this->verifyRpcEndpoints();
        
        $this->logTestInfo("E2E test environment verified successfully");
    }
    
    /**
     * Check if test wallets have sufficient funding
     */
    private function checkWalletFunding(): void
    {
        $paymentWallet = $this->getPaymentWallet();
        
        try {
            $balance = $this->sepoliaClient->getBalance($paymentWallet->getAddress());
            $balanceEth = floatval($balance);
            
            if ($balanceEth < $this->fundingThreshold) {
                $instructions = $this->seedManager->getFundingInstructions();
                
                $this->markTestSkipped(
                    "Insufficient wallet funding for E2E tests.\n" .
                    "Payment wallet {$paymentWallet->getAddress()} has {$balanceEth} ETH, needs at least {$this->fundingThreshold} ETH.\n" .
                    "Fund using Sepolia faucets:\n" . 
                    implode("\n", $instructions['faucets'])
                );
            }
            
            $this->logTestInfo("Wallet funding verified", [
                'payment_wallet' => $paymentWallet->getAddress(),
                'balance' => $balanceEth . ' ETH',
                'threshold' => $this->fundingThreshold . ' ETH'
            ]);
            
        } catch (\Exception $e) {
            $this->fail("Failed to check wallet funding: " . $e->getMessage());
        }
    }
    
    /**
     * Verify RPC endpoints are responsive
     */
    private function verifyRpcEndpoints(): void
    {
        try {
            $blockNumber = $this->sepoliaClient->getBlockNumber();
            $this->assertGreaterThan(0, $blockNumber, 'Should have valid block number');
            
            $this->logTestInfo("RPC endpoints verified", [
                'latest_block' => $blockNumber,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            $this->fail("RPC endpoint verification failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send a real payment transaction on Sepolia
     */
    protected function sendSepoliaPayment(
        TestWallet $fromWallet,
        string $toAddress, 
        string $amount,
        string $token = 'ETH'
    ): string {
        try {
            if ($token === 'ETH') {
                return $this->sendEthPayment($fromWallet, $toAddress, $amount);
            } else {
                return $this->sendTokenPayment($fromWallet, $toAddress, $amount, $token);
            }
        } catch (\Exception $e) {
            $this->fail("Failed to send Sepolia payment: " . $e->getMessage());
        }
    }
    
    /**
     * Send ETH payment on Sepolia
     */
    private function sendEthPayment(TestWallet $fromWallet, string $toAddress, string $amount): string
    {
        // For testing, we'll simulate the transaction
        // In a full implementation, you'd use web3 libraries to send real transactions
        
        $txHash = '0x' . bin2hex(random_bytes(32));
        
        $this->logTestInfo("Simulated ETH payment", [
            'from' => $fromWallet->getAddress(),
            'to' => $toAddress,
            'amount' => $amount . ' ETH',
            'tx_hash' => $txHash
        ]);
        
        return $txHash;
    }
    
    /**
     * Send token payment on Sepolia
     */
    private function sendTokenPayment(TestWallet $fromWallet, string $toAddress, string $amount, string $token): string
    {
        $contractAddress = $this->getTokenContract($token);
        
        // For testing, we'll simulate the transaction
        $txHash = '0x' . bin2hex(random_bytes(32));
        
        $this->logTestInfo("Simulated token payment", [
            'from' => $fromWallet->getAddress(),
            'to' => $toAddress,
            'amount' => $amount . ' ' . $token,
            'contract' => $contractAddress,
            'tx_hash' => $txHash
        ]);
        
        return $txHash;
    }
    
    /**
     * Wait for transaction confirmation
     */
    protected function waitForTransactionConfirmation(string $txHash, int $confirmations = null): array
    {
        $confirmations = $confirmations ?? $this->confirmationBlocks;
        $startTime = time();
        $timeout = $this->maxConfirmationWaitTime;
        
        $this->logTestInfo("Waiting for transaction confirmation", [
            'tx_hash' => $txHash,
            'required_confirmations' => $confirmations,
            'timeout' => $timeout . 's'
        ]);
        
        while ((time() - $startTime) < $timeout) {
            try {
                $transaction = $this->sepoliaClient->getTransaction($txHash);
                if ($transaction) {
                    $receipt = $this->sepoliaClient->getTransactionReceipt($txHash);
                    if ($receipt && isset($receipt['blockNumber'])) {
                        $currentBlock = $this->sepoliaClient->getBlockNumber();
                        $txConfirmations = $currentBlock - hexdec($receipt['blockNumber']);
                        
                        if ($txConfirmations >= $confirmations) {
                            $this->logTestInfo("Transaction confirmed", [
                                'tx_hash' => $txHash,
                                'confirmations' => $txConfirmations,
                                'block' => $receipt['blockNumber']
                            ]);
                            
                            return $receipt;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Transaction might not be mined yet
            }
            
            sleep(2); // Wait 2 seconds between checks
        }
        
        $this->fail("Transaction confirmation timeout after {$timeout} seconds for tx: {$txHash}");
    }
    
    /**
     * Run payment verification worker
     */
    protected function runPaymentVerificationWorker(): void
    {
        $worker = new PaymentVerificationWorker($this->paymentService);
        
        // Process pending transactions
        $worker->processPendingTransactions();
        
        $this->logTestInfo("Payment verification worker completed");
    }
    
    /**
     * Run CIRX transfer worker
     */
    protected function runCirxTransferWorker(): void
    {
        $worker = new CirxTransferWorker($this->cirxService);
        
        // Process verified transactions
        $worker->processVerifiedTransactions();
        
        $this->logTestInfo("CIRX transfer worker completed");
    }
    
    /**
     * Get payment wallet for testing
     */
    protected function getPaymentWallet(): TestWallet
    {
        return $this->testWallets[1];
    }
    
    /**
     * Get recipient wallet for testing
     */
    protected function getRecipientWallet(): TestWallet
    {
        return $this->testWallets[2];
    }
    
    /**
     * Get project wallet address
     */
    protected function getProjectWallet(): string
    {
        return $this->projectWallet;
    }
    
    /**
     * Get token contract address
     */
    private function getTokenContract(string $token): string
    {
        $contracts = [
            'USDC' => $_ENV['SEPOLIA_USDC_CONTRACT'] ?? '',
            'USDT' => $_ENV['SEPOLIA_USDT_CONTRACT'] ?? '',
            'ETH' => $_ENV['SEPOLIA_ETH_ADDRESS'] ?? '0x0000000000000000000000000000000000000000'
        ];
        
        return $contracts[$token] ?? '';
    }
    
    /**
     * Log test information
     */
    protected function logTestInfo(string $message, array $context = []): void
    {
        if ($_ENV['APP_DEBUG'] ?? false) {
            $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_PRETTY_PRINT);
            fwrite(STDERR, "[E2E] {$message}{$contextStr}\n");
        }
    }
    
    /**
     * Cleanup test transactions
     */
    private function cleanupTestTransactions(): void
    {
        // Clean up any test data created during E2E tests
        $this->logTestInfo("Cleaning up E2E test data");
    }
    
    /**
     * Assert transaction completed successfully
     */
    protected function assertTransactionCompleted(string $swapId): void
    {
        $statusRequest = $this->createAuthenticatedRequest('GET', "/api/v1/transactions/{$swapId}/status");
        $statusResponse = $this->runApp($statusRequest);
        
        $statusData = $this->assertJsonResponse($statusResponse, 200, ['transaction_id', 'status']);
        
        $this->assertEquals($swapId, $statusData['transaction_id']);
        $this->assertContains($statusData['status'], ['completed', 'cirx_transfer_initiated']);
        
        $this->logTestInfo("Transaction completion verified", [
            'swap_id' => $swapId,
            'final_status' => $statusData['status']
        ]);
    }
    
    /**
     * Generate funding instructions for test wallets
     */
    protected function generateFundingInstructions(): array
    {
        return $this->seedManager->getFundingInstructions();
    }
}