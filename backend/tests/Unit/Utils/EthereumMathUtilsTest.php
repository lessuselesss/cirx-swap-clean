<?php

namespace Tests\Unit\Utils;

use App\Utils\EthereumMathUtils;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EthereumMathUtilsTest extends TestCase
{
    /**
     * Test converting ETH to wei (smallest unit)
     */
    public function test_convert_eth_to_wei(): void
    {
        // 1 ETH = 1e18 wei
        $result = EthereumMathUtils::convertToSmallestUnit('1', 'ETH');
        $this->assertEquals('1000000000000000000', $result);
        
        // 0.5 ETH = 5e17 wei  
        $result = EthereumMathUtils::convertToSmallestUnit('0.5', 'ETH');
        $this->assertEquals('500000000000000000', $result);
        
        // Very small amount
        $result = EthereumMathUtils::convertToSmallestUnit('0.000001', 'ETH');
        $this->assertEquals('1000000000000', $result);
    }

    /**
     * Test converting USDC to smallest units
     */
    public function test_convert_usdc_to_smallest_unit(): void
    {
        // 1 USDC = 1e6 units
        $result = EthereumMathUtils::convertToSmallestUnit('1', 'USDC');
        $this->assertEquals('1000000', $result);
        
        // 100.50 USDC
        $result = EthereumMathUtils::convertToSmallestUnit('100.50', 'USDC');
        $this->assertEquals('100500000', $result);
        
        // Very small amount (0.000001 USDC = 1 unit)
        $result = EthereumMathUtils::convertToSmallestUnit('0.000001', 'USDC');
        $this->assertEquals('1', $result);
    }

    /**
     * Test converting USDT to smallest units
     */
    public function test_convert_usdt_to_smallest_unit(): void
    {
        // Same as USDC - 6 decimals
        $result = EthereumMathUtils::convertToSmallestUnit('1', 'USDT');
        $this->assertEquals('1000000', $result);
        
        $result = EthereumMathUtils::convertToSmallestUnit('50.123456', 'USDT');
        $this->assertEquals('50123456', $result);
    }

    /**
     * Test converting from wei to ETH
     */
    public function test_convert_wei_to_eth(): void
    {
        // 1e18 wei = 1 ETH
        $result = EthereumMathUtils::convertFromSmallestUnit('1000000000000000000', 'ETH');
        $this->assertEquals('1.000000000000000000', $result);
        
        // 5e17 wei = 0.5 ETH
        $result = EthereumMathUtils::convertFromSmallestUnit('500000000000000000', 'ETH');
        $this->assertEquals('0.500000000000000000', $result);
    }

    /**
     * Test converting from USDC units to USDC
     */
    public function test_convert_usdc_units_to_usdc(): void
    {
        // 1e6 units = 1 USDC
        $result = EthereumMathUtils::convertFromSmallestUnit('1000000', 'USDC');
        $this->assertEquals('1.000000', $result);
        
        // 100.5 USDC
        $result = EthereumMathUtils::convertFromSmallestUnit('100500000', 'USDC');
        $this->assertEquals('100.500000', $result);
    }

    /**
     * Test amount comparison with proper precision
     */
    public function test_compare_amounts(): void
    {
        // ETH comparison (18 decimals)
        $this->assertEquals(0, EthereumMathUtils::compareAmounts('1.0', '1.000000000000000000', 'ETH'));
        $this->assertEquals(1, EthereumMathUtils::compareAmounts('1.000000000000000001', '1.0', 'ETH'));
        $this->assertEquals(-1, EthereumMathUtils::compareAmounts('0.999999999999999999', '1.0', 'ETH'));
        
        // USDC comparison (6 decimals)
        $this->assertEquals(0, EthereumMathUtils::compareAmounts('100.0', '100.000000', 'USDC'));
        $this->assertEquals(1, EthereumMathUtils::compareAmounts('100.000001', '100.0', 'USDC'));
        $this->assertEquals(-1, EthereumMathUtils::compareAmounts('99.999999', '100.0', 'USDC'));
    }

    /**
     * Test mathematical operations
     */
    public function test_mathematical_operations(): void
    {
        // Addition
        $result = EthereumMathUtils::addAmounts('1.5', '2.5', 'ETH');
        $this->assertEquals('4.000000000000000000', $result);
        
        // Subtraction  
        $result = EthereumMathUtils::subtractAmounts('10.0', '3.5', 'USDC');
        $this->assertEquals('6.500000', $result);
        
        // Multiplication
        $result = EthereumMathUtils::multiplyAmount('2.5', '4', 'ETH');
        $this->assertEquals('10.000000000000000000', $result);
        
        // Division
        $result = EthereumMathUtils::divideAmount('10.0', '2.5', 'USDC');
        $this->assertEquals('4.000000', $result);
    }

    /**
     * Test token decimals retrieval
     */
    public function test_get_token_decimals(): void
    {
        $this->assertEquals(18, EthereumMathUtils::getTokenDecimals('ETH'));
        $this->assertEquals(6, EthereumMathUtils::getTokenDecimals('USDC'));
        $this->assertEquals(6, EthereumMathUtils::getTokenDecimals('USDT'));
        
        // Case insensitive
        $this->assertEquals(18, EthereumMathUtils::getTokenDecimals('eth'));
        $this->assertEquals(6, EthereumMathUtils::getTokenDecimals('usdc'));
    }

    /**
     * Test supported tokens
     */
    public function test_supported_tokens(): void
    {
        $tokens = EthereumMathUtils::getSupportedTokens();
        $this->assertContains('ETH', $tokens);
        $this->assertContains('USDC', $tokens);
        $this->assertContains('USDT', $tokens);
        $this->assertCount(3, $tokens);
        
        $this->assertTrue(EthereumMathUtils::isTokenSupported('ETH'));
        $this->assertTrue(EthereumMathUtils::isTokenSupported('usdc'));
        $this->assertFalse(EthereumMathUtils::isTokenSupported('BTC'));
        $this->assertFalse(EthereumMathUtils::isTokenSupported('SOL')); // Solana removed
    }

    /**
     * Test amount formatting
     */
    public function test_format_amount(): void
    {
        // Remove trailing zeros
        $this->assertEquals('1', EthereumMathUtils::formatAmount('1.000000000000000000', 'ETH'));
        $this->assertEquals('1.5', EthereumMathUtils::formatAmount('1.500000000000000000', 'ETH'));
        $this->assertEquals('100.123456', EthereumMathUtils::formatAmount('100.123456000000000000', 'ETH'));
        
        // USDC formatting
        $this->assertEquals('100', EthereumMathUtils::formatAmount('100.000000', 'USDC'));
        $this->assertEquals('99.5', EthereumMathUtils::formatAmount('99.500000', 'USDC'));
        
        // Max decimals limit
        $this->assertEquals('1.12', EthereumMathUtils::formatAmount('1.123456789', 'ETH', 2));
    }

    /**
     * Test amount validation
     */
    public function test_amount_validation(): void
    {
        // Valid amounts
        $this->assertTrue(EthereumMathUtils::isValidAmount('1.0', 'ETH'));
        $this->assertTrue(EthereumMathUtils::isValidAmount('100.123456', 'USDC'));
        $this->assertTrue(EthereumMathUtils::isValidAmount('0.000001', 'USDT'));
        $this->assertTrue(EthereumMathUtils::isValidAmount('1000000', 'ETH'));
        
        // Invalid amounts
        $this->assertFalse(EthereumMathUtils::isValidAmount('0', 'ETH')); // Zero
        $this->assertFalse(EthereumMathUtils::isValidAmount('-1', 'ETH')); // Negative
        $this->assertFalse(EthereumMathUtils::isValidAmount('abc', 'ETH')); // Not numeric
        $this->assertFalse(EthereumMathUtils::isValidAmount('1.1234567', 'USDC')); // Too many decimals for USDC
        $this->assertFalse(EthereumMathUtils::isValidAmount('1.1234567890123456789', 'ETH')); // Too many decimals for ETH
        $this->assertFalse(EthereumMathUtils::isValidAmount('1..5', 'ETH')); // Invalid format
    }

    /**
     * Test unsupported token throws exception
     */
    public function test_unsupported_token_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported token: BTC');
        
        EthereumMathUtils::convertToSmallestUnit('1.0', 'BTC');
    }

    /**
     * Test division by zero throws exception
     */
    public function test_division_by_zero_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero');
        
        EthereumMathUtils::divideAmount('10.0', '0', 'ETH');
    }

    /**
     * Test precision edge cases
     */
    public function test_precision_edge_cases(): void
    {
        // Very small ETH amounts
        $result = EthereumMathUtils::convertToSmallestUnit('0.000000000000000001', 'ETH');
        $this->assertEquals('1', $result);
        
        // Very small USDC amounts
        $result = EthereumMathUtils::convertToSmallestUnit('0.000001', 'USDC');
        $this->assertEquals('1', $result);
        
        // Large amounts
        $result = EthereumMathUtils::convertToSmallestUnit('1000000', 'ETH');
        $this->assertEquals('1000000000000000000000000', $result);
    }

    /**
     * Test case insensitivity for token symbols
     */
    public function test_case_insensitive_tokens(): void
    {
        // All should work the same
        $result1 = EthereumMathUtils::convertToSmallestUnit('1', 'ETH');
        $result2 = EthereumMathUtils::convertToSmallestUnit('1', 'eth');
        $result3 = EthereumMathUtils::convertToSmallestUnit('1', 'Eth');
        
        $this->assertEquals($result1, $result2);
        $this->assertEquals($result2, $result3);
        
        $this->assertEquals(6, EthereumMathUtils::getTokenDecimals('usdc'));
        $this->assertEquals(6, EthereumMathUtils::getTokenDecimals('USDC'));
        $this->assertEquals(6, EthereumMathUtils::getTokenDecimals('USDT'));
    }

    /**
     * Test financial precision scenarios (preventing rounding errors)
     */
    public function test_financial_precision_scenarios(): void
    {
        // Common DeFi scenario: 0.1 ETH payment
        $ethAmount = '0.1';
        $weiAmount = EthereumMathUtils::convertToSmallestUnit($ethAmount, 'ETH');
        $backToEth = EthereumMathUtils::convertFromSmallestUnit($weiAmount, 'ETH');
        
        // Should be exactly equal (no precision loss)
        $this->assertEquals(0, EthereumMathUtils::compareAmounts($ethAmount, $backToEth, 'ETH'));
        
        // Common USDC scenario: $99.99 payment  
        $usdcAmount = '99.99';
        $units = EthereumMathUtils::convertToSmallestUnit($usdcAmount, 'USDC');
        $backToUsdc = EthereumMathUtils::convertFromSmallestUnit($units, 'USDC');
        
        // Should be exactly equal (no precision loss)
        $this->assertEquals(0, EthereumMathUtils::compareAmounts($usdcAmount, $backToUsdc, 'USDC'));
    }
}