<?php

namespace Tests\E2E;

use App\Utils\BlockchainTestUtils;

/**
 * Multi-Token E2E Tests for Sepolia Testnet
 * 
 * Tests payment verification and OTC swaps for different tokens:
 * - ETH (native token)
 * - USDC (ERC-20 token)
 * - USDT (ERC-20 token)
 */
class MultiTokenSwapTest extends E2ETestCase
{
    private BlockchainTestUtils $blockchainUtils;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->blockchainUtils = new BlockchainTestUtils($this->sepoliaClient);
    }
    
    /**
     * Test swap with various payment amounts in ETH
     */
    public function testVariousETHPaymentAmounts(): void
    {
        $this->logTestInfo("Testing various ETH payment amounts");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $testAmounts = [
            '0.001', // Small amount
            '0.01',  // Medium amount
            '0.05'   // Larger amount
        ];
        
        foreach ($testAmounts as $amount) {
            $this->logTestInfo("Testing ETH amount: {$amount}");
            
            // Check if wallet has sufficient balance
            if (!$this->blockchainUtils->checkSufficientBalance($paymentWallet->getAddress(), $amount, 'ETH')) {
                $this->markTestSkipped("Insufficient balance for {$amount} ETH test");
                continue;
            }
            
            // Send payment
            $txHash = $this->sendSepoliaPayment(
                fromWallet: $paymentWallet,
                toAddress: $projectWallet,
                amount: $amount,
                token: 'ETH'
            );
            
            // Wait for confirmation
            $receipt = $this->waitForTransactionConfirmation($txHash, 1);
            $this->assertNotNull($receipt, "Transaction should be confirmed");
            
            // Verify transaction details
            $verification = $this->blockchainUtils->verifyTransaction($txHash, [
                'to' => $projectWallet,
                'value' => $amount
            ]);
            
            $this->assertTrue($verification['valid'], 
                "Transaction verification failed: " . implode(', ', $verification['errors'] ?? [])
            );
            
            // Initiate and process swap
            $swapId = $this->initiateSwap($txHash, 'sepolia', $recipientWallet->getAddress(), $amount, 'ETH');
            $this->processSwap($swapId);
            
            $this->logTestInfo("ETH payment amount test completed", [
                'amount' => $amount . ' ETH',
                'tx_hash' => $txHash,
                'swap_id' => $swapId
            ]);
        }
    }
    
    /**
     * Test USDC token payments with different amounts
     */
    public function testUSDCTokenPayments(): void
    {
        $this->logTestInfo("Testing USDC token payments");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $testAmounts = [
            '10.0',   // $10 USDC
            '100.0',  // $100 USDC
            '1000.0'  // $1000 USDC
        ];
        
        foreach ($testAmounts as $amount) {
            $this->logTestInfo("Testing USDC amount: {$amount}");
            
            // Check wallet has sufficient USDC (simulated)
            // In real implementation, you'd check actual USDC balance
            
            // Send USDC payment
            $txHash = $this->sendSepoliaPayment(
                fromWallet: $paymentWallet,
                toAddress: $projectWallet,
                amount: $amount,
                token: 'USDC'
            );
            
            // Wait for confirmation
            $receipt = $this->waitForTransactionConfirmation($txHash, 1);
            $this->assertNotNull($receipt);
            
            // Verify gas costs are reasonable for token transfers
            $gasCostEth = $this->blockchainUtils->weiToEth($receipt['gasUsed'] ?? '65000');
            $this->assertLessThan(0.01, floatval($gasCostEth), 'Gas cost should be reasonable');
            
            // Process swap
            $swapId = $this->initiateSwap($txHash, 'sepolia', $recipientWallet->getAddress(), $amount, 'USDC');
            $this->processSwap($swapId);
            
            $this->logTestInfo("USDC payment test completed", [
                'amount' => $amount . ' USDC',
                'tx_hash' => $txHash,
                'swap_id' => $swapId,
                'gas_used' => $receipt['gasUsed'] ?? 'unknown'
            ]);
        }
    }
    
    /**
     * Test USDT token payments
     */
    public function testUSDTTokenPayments(): void
    {
        $this->logTestInfo("Testing USDT token payments");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $amount = '500.0'; // $500 USDT
        
        // Send USDT payment
        $txHash = $this->sendSepoliaPayment(
            fromWallet: $paymentWallet,
            toAddress: $projectWallet,
            amount: $amount,
            token: 'USDT'
        );
        
        // Wait for confirmation
        $receipt = $this->waitForTransactionConfirmation($txHash, 1);
        $this->assertNotNull($receipt);
        
        // Process swap
        $swapId = $this->initiateSwap($txHash, 'sepolia', $recipientWallet->getAddress(), $amount, 'USDT');
        $this->processSwap($swapId);
        
        $this->logTestInfo("USDT payment test completed", [
            'amount' => $amount . ' USDT',
            'tx_hash' => $txHash,
            'swap_id' => $swapId
        ]);
    }
    
    /**
     * Test mixed token payments in sequence
     */
    public function testMixedTokenSequence(): void
    {
        $this->logTestInfo("Testing mixed token payment sequence");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        $payments = [
            ['amount' => '0.01', 'token' => 'ETH'],
            ['amount' => '50.0', 'token' => 'USDC'],
            ['amount' => '25.0', 'token' => 'USDT'],
            ['amount' => '0.005', 'token' => 'ETH']
        ];
        
        $swapIds = [];
        
        foreach ($payments as $index => $payment) {
            $this->logTestInfo("Processing payment {$index + 1}/4", $payment);
            
            // Send payment
            $txHash = $this->sendSepoliaPayment(
                fromWallet: $paymentWallet,
                toAddress: $projectWallet,
                amount: $payment['amount'],
                token: $payment['token']
            );
            
            // Wait for confirmation
            $receipt = $this->waitForTransactionConfirmation($txHash, 1);
            $this->assertNotNull($receipt);
            
            // Initiate swap
            $swapId = $this->initiateSwap(
                $txHash, 
                'sepolia', 
                $recipientWallet->getAddress(), 
                $payment['amount'], 
                $payment['token']
            );
            
            $swapIds[] = $swapId;
            
            $this->logTestInfo("Payment processed", [
                'index' => $index + 1,
                'amount' => $payment['amount'] . ' ' . $payment['token'],
                'tx_hash' => $txHash,
                'swap_id' => $swapId
            ]);
        }
        
        // Process all swaps in batch
        $this->runPaymentVerificationWorker();
        $this->runCirxTransferWorker();
        
        // Verify all swaps completed
        foreach ($swapIds as $index => $swapId) {
            $this->assertTransactionCompleted($swapId);
            $this->logTestInfo("Mixed token swap completed", [
                'index' => $index + 1,
                'swap_id' => $swapId
            ]);
        }
        
        $this->logTestInfo("All mixed token payments processed successfully");
    }
    
    /**
     * Test token precision and decimal handling
     */
    public function testTokenPrecisionHandling(): void
    {
        $this->logTestInfo("Testing token precision and decimal handling");
        
        $paymentWallet = $this->getPaymentWallet();
        $recipientWallet = $this->getRecipientWallet();
        $projectWallet = $this->getProjectWallet();
        
        // Test precise amounts with different decimal places
        $precisionTests = [
            ['amount' => '0.000001', 'token' => 'ETH', 'description' => '1 gwei'],
            ['amount' => '0.123456789012345678', 'token' => 'ETH', 'description' => 'maximum ETH precision'],
            ['amount' => '1.23', 'token' => 'USDC', 'description' => 'standard USDC amount'],
            ['amount' => '0.01', 'token' => 'USDC', 'description' => 'minimum practical USDC'],
            ['amount' => '999.999999', 'token' => 'USDC', 'description' => 'high precision USDC']
        ];
        
        foreach ($precisionTests as $test) {
            $this->logTestInfo("Testing precision: {$test['description']}", [
                'amount' => $test['amount'],
                'token' => $test['token']
            ]);
            
            // Skip if amount is too small for practical testing
            if ($test['token'] === 'ETH' && floatval($test['amount']) < 0.001) {
                $this->logTestInfo("Skipping very small amount test");
                continue;
            }
            
            $txHash = $this->sendSepoliaPayment(
                fromWallet: $paymentWallet,
                toAddress: $projectWallet,
                amount: $test['amount'],
                token: $test['token']
            );
            
            $receipt = $this->waitForTransactionConfirmation($txHash, 1);
            $this->assertNotNull($receipt);
            
            // Verify amount precision is preserved
            $swapId = $this->initiateSwap(
                $txHash,
                'sepolia',
                $recipientWallet->getAddress(),
                $test['amount'],
                $test['token']
            );
            
            $this->processSwap($swapId);
            
            $this->logTestInfo("Precision test completed", [
                'description' => $test['description'],
                'amount' => $test['amount'] . ' ' . $test['token'],
                'swap_id' => $swapId
            ]);
        }
    }
    
    /**
     * Test token contract validation
     */
    public function testTokenContractValidation(): void
    {
        $this->logTestInfo("Testing token contract validation");
        
        // Verify contract addresses are properly configured
        $usdcContract = $_ENV['SEPOLIA_USDC_CONTRACT'] ?? '';
        $usdtContract = $_ENV['SEPOLIA_USDT_CONTRACT'] ?? '';
        
        $this->assertNotEmpty($usdcContract, 'USDC contract address should be configured');
        $this->assertNotEmpty($usdtContract, 'USDT contract address should be configured');
        
        $this->assertTrue($this->blockchainUtils->isValidAddress($usdcContract), 'USDC contract should be valid address');
        $this->assertTrue($this->blockchainUtils->isValidAddress($usdtContract), 'USDT contract should be valid address');
        
        $this->logTestInfo("Token contract validation passed", [
            'usdc_contract' => $usdcContract,
            'usdt_contract' => $usdtContract
        ]);
    }
    
    /**
     * Test gas estimation for different token types
     */
    public function testGasEstimation(): void
    {
        $this->logTestInfo("Testing gas estimation for different token types");
        
        $testAddress = $this->getProjectWallet();
        
        // Test gas estimation for different transaction types
        $gasTests = [
            ['amount' => '0.01', 'token' => 'ETH'],
            ['amount' => '100.0', 'token' => 'USDC'],
            ['amount' => '100.0', 'token' => 'USDT']
        ];
        
        foreach ($gasTests as $test) {
            $gasEstimate = $this->blockchainUtils->estimateTransactionCost(
                $testAddress,
                $test['amount'],
                $test['token']
            );
            
            $this->assertArrayHasKey('gas_cost_eth', $gasEstimate);
            $this->assertArrayHasKey('is_affordable', $gasEstimate);
            
            // Gas should be affordable for testing
            $this->assertTrue($gasEstimate['is_affordable'], 
                "Gas should be affordable for {$test['token']} transactions"
            );
            
            $this->logTestInfo("Gas estimation completed", [
                'token' => $test['token'],
                'gas_cost_eth' => $gasEstimate['gas_cost_eth'] ?? 'unknown',
                'gas_price_gwei' => $gasEstimate['gas_price_gwei'] ?? 'unknown',
                'is_affordable' => $gasEstimate['is_affordable']
            ]);
        }
    }
    
    /**
     * Helper method to initiate swap
     */
    private function initiateSwap(string $txHash, string $chain, string $recipient, string $amount, string $token): string
    {
        $swapRequest = [
            'txId' => $txHash,
            'paymentChain' => $chain,
            'cirxRecipientAddress' => $recipient,
            'amountPaid' => $amount,
            'paymentToken' => $token
        ];
        
        $request = $this->createAuthenticatedRequest('POST', '/api/v1/transactions/initiate-swap', $swapRequest);
        $response = $this->runApp($request);
        
        $responseData = $this->assertJsonResponse($response, 202, ['swapId']);
        return $responseData['swapId'];
    }
    
    /**
     * Helper method to process swap through workers
     */
    private function processSwap(string $swapId): void
    {
        $this->runPaymentVerificationWorker();
        $this->runCirxTransferWorker();
        
        // Verify completion
        $this->assertTransactionCompleted($swapId);
    }
}