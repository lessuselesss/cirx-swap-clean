import { computed, ref } from 'vue'
import { getTokenPrices, getTokenPrice } from '../services/priceService.js'

/**
 * Swap business logic composable
 * Handles quote calculations, price feeds, and swap validation
 * Separated from UI components for better testability
 */
export function useSwapLogic() {
  
  // Safe arithmetic utilities to prevent NaN issues
  const safeDiv = (a, b, fallback = 0) => {
    if (typeof a !== 'number' || typeof b !== 'number' || isNaN(a) || isNaN(b) || b === 0) {
      return fallback
    }
    const result = a / b
    return isFinite(result) ? result : fallback
  }
  
  const safeMul = (a, b, fallback = 0) => {
    if (typeof a !== 'number' || typeof b !== 'number' || isNaN(a) || isNaN(b)) {
      return fallback
    }
    const result = a * b
    return isFinite(result) ? result : fallback
  }
  
  const safePercentage = (value, defaultValue = 0) => {
    const num = parseFloat(value)
    return (isNaN(num) || !isFinite(num)) ? defaultValue : num
  }
  
  const validateNumber = (value, name = 'value') => {
    const num = parseFloat(value)
    if (isNaN(num) || !isFinite(num) || num < 0) {
      console.warn(`Invalid ${name}:`, value)
      return null
    }
    return num
  }
  
  // Real-time token prices (fetched from live APIs)
  const tokenPrices = ref({
    ETH: 2500,   // Will be updated with live prices
    USDC: 1,     
    USDT: 1,     
    SOL: 100,    
    CIRX: 1      
  })

  // Track if we're using live or fallback prices
  const priceSource = ref('loading')

  // Initialize prices on first use
  const initializePrices = async () => {
    try {
      const livePrices = await getTokenPrices()
      tokenPrices.value = { ...livePrices }
      priceSource.value = 'live'
    } catch (error) {
      console.warn('Failed to load live prices, using fallback:', error)
      priceSource.value = 'fallback'
    }
  }

  // Auto-initialize prices
  initializePrices()

  // Fee structure
  const fees = {
    liquid: 0.3,  // 0.3% for liquid swaps
    otc: 0.15     // 0.15% for OTC swaps
  }

  // OTC discount tiers
  const discountTiers = [
    { minAmount: 50000, discount: 12 },  // $50K+: 12%
    { minAmount: 10000, discount: 8 },   // $10K+: 8%  
    { minAmount: 1000, discount: 5 }     // $1K+: 5%
  ]

  /**
   * Calculate discount percentage based on USD amount
   */
  const calculateDiscount = (usdAmount) => {
    for (const tier of discountTiers) {
      if (usdAmount >= tier.minAmount) {
        return tier.discount
      }
    }
    return 0
  }

  /**
   * Normalize token symbol for price lookup
   */
  const normalizeTokenSymbol = (tokenSymbol) => {
    // Handle Solana-specific token naming
    if (tokenSymbol === 'USDC_SOL') return 'USDC'
    if (tokenSymbol === 'USDT_SOL') return 'USDT'
    return tokenSymbol
  }

  /**
   * Get token price in USD
   */
  const getTokenPrice = (tokenSymbol) => {
    const normalizedSymbol = normalizeTokenSymbol(tokenSymbol)
    const price = tokenPrices.value[normalizedSymbol]
    
    // Add validation to prevent NaN
    if (typeof price !== 'number' || isNaN(price) || price <= 0) {
      console.warn(`Invalid price for token ${tokenSymbol}:`, price)
      return 0
    }
    
    return price
  }

  /**
   * Refresh prices from live feed
   */
  const refreshPrices = async () => {
    await initializePrices()
  }

  /**
   * Calculate swap quote with proper CIRX/USDT conversion
   * Enhanced with comprehensive NaN prevention
   */
  const calculateQuote = (inputAmount, inputToken, isOTC = false, selectedTier = null) => {
    // Validate input amount
    const inputValue = validateNumber(inputAmount, 'input amount')
    if (inputValue === null || inputValue <= 0) {
      return null
    }
    
    // Get token prices with validation
    const inputTokenPrice = getTokenPrice(inputToken) // Price in USD
    const cirxPrice = getTokenPrice('CIRX') // CIRX price in USD (via USDT)
    
    // Comprehensive price validation
    if (inputTokenPrice <= 0 || cirxPrice <= 0) {
      console.warn(`Cannot calculate quote: invalid prices - ${inputToken}: $${inputTokenPrice}, CIRX: $${cirxPrice}`)
      return null
    }
    
    // Safe calculation of total USD value
    const totalUsdValue = safeMul(inputValue, inputTokenPrice)
    if (totalUsdValue <= 0) {
      console.error('Invalid total USD value calculation:', { inputValue, inputTokenPrice, totalUsdValue })
      return null
    }
    
    // Calculate fee with safe percentage handling
    const feeRate = safePercentage(isOTC ? fees.otc : fees.liquid)
    const feeAmount = safeMul(inputValue, safeDiv(feeRate, 100))
    const amountAfterFee = Math.max(0, inputValue - feeAmount)
    const usdAfterFee = safeMul(amountAfterFee, inputTokenPrice)
    
    // Calculate CIRX amount with safe division
    let cirxReceived = safeDiv(usdAfterFee, cirxPrice)
    if (cirxReceived <= 0) {
      console.error('Invalid CIRX calculation:', { usdAfterFee, cirxPrice, cirxReceived })
      return null
    }
    
    // Apply OTC discount with safe calculations
    let discount = 0
    if (isOTC) {
      // Use selected tier discount if provided, otherwise calculate based on amount
      if (selectedTier && selectedTier.discount) {
        discount = safePercentage(selectedTier.discount)
      } else {
        discount = safePercentage(calculateDiscount(totalUsdValue))
      }
      
      if (discount > 0) {
        const multiplier = 1 + safeDiv(discount, 100)
        cirxReceived = safeMul(cirxReceived, multiplier)
      }
    }
    
    // Final validation of CIRX amount
    if (!isFinite(cirxReceived) || cirxReceived <= 0) {
      console.error('Final CIRX validation failed:', cirxReceived)
      return null
    }
    
    // Calculate exchange rate with safe division
    const exchangeRate = safeDiv(inputTokenPrice, cirxPrice)
    
    return {
      inputAmount: inputValue,
      inputToken,
      inputUsdValue: totalUsdValue,
      tokenPrice: inputTokenPrice,
      cirxPrice,
      feeRate,
      feeAmount: parseFloat(feeAmount.toFixed(8)),
      feeUsd: safeMul(feeAmount, inputTokenPrice),
      discount,
      cirxAmount: parseFloat(cirxReceived.toFixed(6)),
      cirxAmountFormatted: formatNumber(cirxReceived),
      exchangeRate: `1 ${inputToken} = ${exchangeRate.toFixed(2)} CIRX`,
      isOTC,
      priceImpact: 0, // Could be calculated based on liquidity
      minimumReceived: parseFloat(safeMul(cirxReceived, 0.995).toFixed(6)),
      vestingPeriod: isOTC ? '6 months' : null
    }
  }

  /**
   * Calculate reverse quote (CIRX amount -> input token amount) with proper price conversion
   * Enhanced with comprehensive NaN prevention
   */
  const calculateReverseQuote = (cirxAmount, targetToken, isOTC = false, selectedTier = null) => {
    // Validate CIRX amount
    const cirxValue = validateNumber(cirxAmount, 'CIRX amount')
    if (cirxValue === null || cirxValue <= 0) {
      return null
    }
    
    // Get token prices with validation
    const targetTokenPrice = getTokenPrice(targetToken) // Price in USD
    const cirxPrice = getTokenPrice('CIRX') // CIRX price in USD (via USDT)
    
    // Comprehensive price validation
    if (targetTokenPrice <= 0 || cirxPrice <= 0) {
      console.warn(`Cannot calculate reverse quote: invalid prices - ${targetToken}: $${targetTokenPrice}, CIRX: $${cirxPrice}`)
      return null
    }
    
    // Convert CIRX to USD with safe multiplication
    let usdValue = safeMul(cirxValue, cirxPrice)
    if (usdValue <= 0) {
      console.error('Invalid USD value from CIRX conversion:', { cirxValue, cirxPrice, usdValue })
      return null
    }
    
    let discount = 0
    
    // Reverse the OTC discount calculation with safe operations
    if (isOTC) {
      // Use selected tier discount if provided, otherwise calculate based on amount
      if (selectedTier && selectedTier.discount) {
        discount = safePercentage(selectedTier.discount)
      } else {
        discount = safePercentage(calculateDiscount(usdValue))
      }
      
      if (discount > 0) {
        const discountMultiplier = 1 + safeDiv(discount, 100)
        if (discountMultiplier <= 0) {
          console.error('Invalid discount multiplier:', { discount, discountMultiplier })
          return null
        }
        usdValue = safeDiv(usdValue, discountMultiplier)
      }
    }
    
    // Reverse the fee calculation with safe operations
    const feeRate = safePercentage(isOTC ? fees.otc : fees.liquid)
    const feeMultiplier = 1 - safeDiv(feeRate, 100)
    
    // Validate fee multiplier to prevent division by zero
    if (feeMultiplier <= 0 || feeMultiplier > 1) {
      console.error('Invalid fee multiplier:', { feeRate, feeMultiplier })
      return null
    }
    
    // Calculate input amount with safe division
    const denominator = safeMul(targetTokenPrice, feeMultiplier)
    const inputValue = safeDiv(usdValue, denominator)
    
    // Validate final result
    if (inputValue <= 0 || !isFinite(inputValue)) {
      console.error('Invalid reverse calculation result:', { usdValue, denominator, inputValue })
      return null
    }
    
    // Calculate the forward quote for verification and additional data
    const forwardQuote = calculateQuote(inputValue.toString(), targetToken, isOTC, selectedTier)
    
    return {
      inputAmount: inputValue,
      inputToken: targetToken,
      cirxAmount: cirxValue,
      tokenPrice: targetTokenPrice,
      cirxPrice,
      feeRate,
      discount,
      isReverse: true,
      forwardQuote // Include forward calculation for verification
    }
  }

  /**
   * Validate swap parameters
   */
  const validateSwap = (inputAmount, inputToken, recipientAddress = null, isConnected = false) => {
    const errors = []

    // Amount validation
    if (!inputAmount || parseFloat(inputAmount) <= 0) {
      errors.push('Invalid amount')
    }

    // Token validation
    if (!inputToken || !tokenPrices[inputToken]) {
      errors.push('Unsupported token')
    }

    // Recipient validation
    if (!isConnected && !recipientAddress) {
      errors.push('Recipient address required')
    }

    if (recipientAddress && !/^0x[a-fA-F0-9]{40}$/.test(recipientAddress)) {
      errors.push('Invalid recipient address')
    }

    // Minimum amount validation
    const usdValue = parseFloat(inputAmount) * getTokenPrice(inputToken)
    if (usdValue < 10) {
      errors.push('Minimum swap amount is $10')
    }

    return {
      isValid: errors.length === 0,
      errors
    }
  }

  /**
   * Calculate maximum input amount based on balance
   */
  const calculateMaxAmount = (balance, tokenSymbol) => {
    const availableBalance = parseFloat(balance) || 0
    
    if (availableBalance <= 0) return '0'

    // Reserve small amount for gas fees if using native tokens
    const reserveAmount = ['ETH', 'SOL'].includes(tokenSymbol) ? 0.001 : 0
    const maxAmount = Math.max(0, availableBalance - reserveAmount)

    return maxAmount.toString()
  }

  /**
   * Format number for display
   */
  const formatNumber = (value, decimals = 2) => {
    const num = parseFloat(value)
    if (isNaN(num)) return '0'

    if (num >= 1000000) {
      return (num / 1000000).toFixed(1) + 'M'
    } else if (num >= 1000) {
      return (num / 1000).toFixed(1) + 'K'
    } else if (num >= 1) {
      return num.toFixed(decimals)
    } else {
      return num.toFixed(6).replace(/\.?0+$/, '')
    }
  }

  /**
   * Format USD value
   */
  const formatUsd = (value) => {
    const num = parseFloat(value)
    if (isNaN(num)) return '$0.00'
    
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(num)
  }

  /**
   * Get available tokens for current wallet
   */
  const getAvailableTokens = (walletChain) => {
    if (walletChain === 'solana') {
      return [
        { symbol: 'SOL', name: 'Solana', logo: '/tokens/sol.svg' },
        { symbol: 'USDC', name: 'USD Coin', logo: '/tokens/usdc.svg' }
      ]
    } else {
      return [
        { symbol: 'ETH', name: 'Ethereum', logo: '/tokens/eth.svg' },
        { symbol: 'USDC', name: 'USD Coin', logo: '/tokens/usdc.svg' },
        { symbol: 'USDT', name: 'Tether', logo: '/tokens/usdt.svg' }
      ]
    }
  }

  /**
   * Check if amount qualifies for OTC discount
   */
  const qualifiesForOTC = (inputAmount, inputToken) => {
    const usdValue = parseFloat(inputAmount) * getTokenPrice(inputToken)
    return usdValue >= 1000 // Minimum for OTC discount
  }

  /**
   * Get estimated transaction time
   */
  const getEstimatedTime = (isOTC, walletChain) => {
    if (isOTC) return 'Immediate (with 6-month vesting)'
    
    if (walletChain === 'ethereum') return '~15 seconds'
    if (walletChain === 'solana') return '~1 second'
    
    return '~1 minute'
  }

  return {
    // Price data
    tokenPrices,
    priceSource,
    fees,
    discountTiers,
    
    // Core functions
    calculateQuote,
    calculateReverseQuote,
    calculateDiscount,
    validateSwap,
    calculateMaxAmount,
    refreshPrices,
    
    // Utility functions
    formatNumber,
    formatUsd,
    getTokenPrice,
    normalizeTokenSymbol,
    getAvailableTokens,
    qualifiesForOTC,
    getEstimatedTime
  }
}