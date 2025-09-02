/**
 * CIRX Business Logic Utilities
 * Contains deposit addresses, quote calculations, validation, and transaction helpers
 */
import { computed } from 'vue'
import { useVestedConfig } from './useFormattedNumbers.js'
import { calculateDiscount } from './core/useMathUtils.js'

export function useCirxUtils() {
  // Get dynamic vested configuration
  const { discountTiers } = useVestedConfig()
  const runtimeConfig = useRuntimeConfig()
  
  // Deposit wallet addresses for different chains/tokens
  // CRITICAL: These addresses MUST match PLATFORM_FEE_WALLET in backend .env to prevent fund loss!
  const DEPOSIT_ADDRESSES = computed(() => ({
    ETH: runtimeConfig.public.ethDepositAddress || '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
    USDC: runtimeConfig.public.usdcDepositAddress || '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
    USDT: runtimeConfig.public.usdtDepositAddress || '0x834244d016f29d6acb42c1b054a88e2e9b1c9228',
  }))

  // Token price constants (ideally from backend or price oracle)
  const TOKEN_PRICES = {
    'ETH': 2700.0,   // $2,700 per ETH
    'USDC': 1.0,     // $1 per USDC
    'USDT': 1.0,     // $1 per USDT
    'SOL': 100.0,    // $100 per SOL
    'BNB': 300.0,    // $300 per BNB
    'MATIC': 0.80    // $0.80 per MATIC
  }

  // calculateDiscount is now imported from useMathUtils

  /**
   * Calculate CIRX quote based on payment amount and token
   * Platform fee: 4 CIRX is subtracted from the CIRX amount received (not added to payment)
   * User pays exactly the input amount, receives CIRX minus 4 CIRX platform fee
   */
  const calculateCirxQuote = (paymentAmount, paymentToken, isOTC = false) => {
    try {
      const amount = parseFloat(paymentAmount)
      if (isNaN(amount) || amount <= 0) {
        throw new Error('Invalid payment amount')
      }
      
      const tokenPrice = TOKEN_PRICES[paymentToken] || 1.0
      
      // Platform fee: 4 CIRX (subtracted from CIRX received, not added to payment)
      const platformFeeCirx = 4.0
      const platformFeeUsd = platformFeeCirx * 2.5 // 4 CIRX × $2.50 = $10
      
      // User's payment amount in USD
      const usdAmount = amount * tokenPrice
      
      // Base CIRX rate: $2.50 per CIRX (1 USD = 0.4 CIRX)
      let grossCirxAmount = usdAmount / 2.5
      
      // Apply OTC discount if applicable (to gross amount before fee)
      let discountPercentage = 0
      if (isOTC) {
        discountPercentage = calculateDiscount(usdAmount, discountTiers.value)
        
        if (discountPercentage > 0) {
          const discountMultiplier = 1 + (discountPercentage / 100)
          grossCirxAmount *= discountMultiplier
        }
      }
      
      // Subtract 4 CIRX platform fee from received amount
      const netCirxAmount = Math.max(0, grossCirxAmount - platformFeeCirx)
      
      // User pays exactly what they input (no additional fees)
      const totalPaymentRequired = amount
      
      return {
        cirxAmount: netCirxAmount.toFixed(1), // Net amount after 4 CIRX fee
        grossCirxAmount: grossCirxAmount.toFixed(1), // Before platform fee
        usdValue: usdAmount.toFixed(2),
        discountPercentage: discountPercentage.toString(),
        platformFee: {
          cirx: platformFeeCirx.toString(),
          usd: platformFeeUsd.toString(),
          deductedFromCirx: true // Important: fee is deducted from CIRX, not payment
        },
        totalPaymentRequired: totalPaymentRequired.toFixed(6), // No fee added to payment
        basePaymentAmount: amount.toFixed(6)
      }
      
    } catch (error) {
      console.error('Quote calculation failed:', error)
      throw error
    }
  }
  
  /**
   * Get the appropriate deposit address for a payment token
   */
  const getDepositAddress = (paymentToken, paymentChain = 'ethereum') => {
    // Map tokens to their deposit addresses
    const tokenAddressMap = {
      'ETH': DEPOSIT_ADDRESSES.value.ETH,
      'USDC': DEPOSIT_ADDRESSES.value.USDC,
      'USDT': DEPOSIT_ADDRESSES.value.USDT
    }
    
    // Map chains to their deposit addresses
    const chainAddressMap = {
      'ethereum': DEPOSIT_ADDRESSES.value.ETH,
      'polygon': DEPOSIT_ADDRESSES.value.POLYGON,
      'bsc': DEPOSIT_ADDRESSES.value.BSC
    }
    
    // Prioritize token-specific address, fallback to chain address
    return tokenAddressMap[paymentToken] || chainAddressMap[paymentChain] || DEPOSIT_ADDRESSES.value.ETH
  }
  
  /**
   * Validate if a Circular Protocol address is properly formatted
   */
  const validateCircularAddress = (address) => {
    if (!address || typeof address !== 'string') {
      return false
    }
    
    // Circular Protocol addresses are 64 hex characters with 0x prefix
    const circularAddressRegex = /^0x[a-fA-F0-9]{64}$/
    return circularAddressRegex.test(address)
  }
  
  /**
   * Create a complete swap transaction data object
   */
  const createSwapTransaction = (paymentTxId, paymentChain, cirxRecipientAddress, amountPaid, paymentToken) => {
    return {
      txId: paymentTxId,
      paymentChain: paymentChain.toLowerCase(),
      cirxRecipientAddress,
      amountPaid: amountPaid.toString(),
      paymentToken: paymentToken.toUpperCase()
    }
  }

  return {
    // Configuration
    DEPOSIT_ADDRESSES,
    TOKEN_PRICES,
    
    // Utility Methods
    calculateCirxQuote,
    getDepositAddress,
    validateCircularAddress,
    createSwapTransaction
  }
}