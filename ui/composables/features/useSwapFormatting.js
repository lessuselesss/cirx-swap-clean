import { computed } from 'vue'
import { useFormattingUtils } from '../core/useFormattingUtils.js'

/**
 * Composable for swap-related formatting utilities
 * Pure functions with no external dependencies for maximum reusability
 */
export function useSwapFormatting() {
  
  /**
   * Convert hex string to BigInt with error handling
   */
  const hexToBigInt = (hex) => {
    try {
      if (typeof hex !== 'string') return 0n
      return BigInt(hex)
    } catch { 
      return 0n 
    }
  }

  /**
   * Format balance display with intelligent decimal truncation
   * Shows first non-zero decimal digit for precision
   */
  const formatBalance = (balance) => {
    const num = parseFloat(balance)
    if (num === 0 || isNaN(num)) return '0.0'
    
    const str = num.toString()
    const [integer, decimal] = str.split('.')
    
    if (!decimal) return integer + '.0'
    
    // Find first non-zero digit in decimal
    let firstNonZeroIndex = -1
    for (let i = 0; i < decimal.length; i++) {
      if (decimal[i] !== '0') {
        firstNonZeroIndex = i
        break
      }
    }
    
    if (firstNonZeroIndex === -1) {
      // All decimal digits are zero
      return integer + '.0'
    }
    
    // Include up to and including the first non-zero decimal digit
    const truncatedDecimal = decimal.substring(0, firstNonZeroIndex + 1)
    return integer + '.' + truncatedDecimal
  }

  // Use formatWithCommas from consolidated utilities
  const { formatWithCommas } = useFormattingUtils()

  /**
   * Format amount display for different size ranges
   * Automatically handles M/K suffixes for large numbers
   */
  const formatAmount = (amount) => {
    if (amount >= 1000000) return `${(amount / 1000000).toFixed(1)}M`.replace('.0M', 'M')
    if (amount >= 1000) return `${(amount / 1000).toFixed(0)}K`
    return amount.toString()
  }

  /**
   * Generate exchange rate display string from CIRX amount
   * Returns formatted rate or empty string if no amount
   */
  const getExchangeRateDisplay = (cirxAmountValue) => {
    const cirxAmountNum = parseFloat(cirxAmountValue) || 0
    
    if (cirxAmountNum === 0) {
      return ''
    }
    
    return `${cirxAmountNum.toFixed(4)} CIRX`
  }

  /**
   * Calculate and format new CIRX balance after transaction
   * Handles different balance sources (recipient, connected wallet, default)
   */
  const getNewCirxBalance = (options = {}) => {
    const {
      cirxAmountValue = 0,
      recipientAddress = '',
      recipientAddressError = '',
      isCircularChainConnected = false,
      cirxBalance = 0
    } = options
    
    const purchaseAmount = parseFloat(cirxAmountValue) || 0
    
    // If we have fetched the recipient's current balance, calculate the new total
    if (recipientAddress && !recipientAddressError) {
      const currentBalance = 0 // TODO: Fetch actual recipient balance
      const newBalance = currentBalance + purchaseAmount
      return `New Balance: ${newBalance.toFixed(4)} CIRX`
    }
    
    // If we have connected wallet balance, use that
    if (isCircularChainConnected) {
      const currentBalance = parseFloat(cirxBalance) || 0
      const newBalance = currentBalance + purchaseAmount
      return `New Balance: ${newBalance.toFixed(4)} CIRX`
    }
    
    // Default case - just show the purchase amount as new balance
    if (purchaseAmount > 0) {
      return `New Balance: ${purchaseAmount.toFixed(4)} CIRX`
    }
    
    return 'New Balance: 0.0000 CIRX'
  }

  /**
   * Format token balance data from API responses
   * Handles various balance data formats and fallbacks
   */
  const formatTokenBalance = (balanceData) => {
    if (!balanceData) return '0.000000000000000000'
    const amount = parseFloat(balanceData.formatted || balanceData)
    if (isNaN(amount)) return '0.000000000000000000'
    return amount.toFixed(18)
  }

  return {
    // Core formatting utilities
    hexToBigInt,
    formatBalance,
    formatWithCommas,
    formatAmount,
    formatTokenBalance,
    
    // Swap-specific formatters
    getExchangeRateDisplay,
    getNewCirxBalance
  }
}