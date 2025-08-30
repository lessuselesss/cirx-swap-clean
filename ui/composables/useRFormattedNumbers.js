/**
 * useNumberInput Composable - Basic version with comma formatting
 */
import { ref, computed } from 'vue'
import { getTokenPrices, getTokenPrice } from './useRPriceData.js'
import { parseEther, formatEther, parseUnits, formatUnits, getContract } from 'viem' // is viem needed for this?
// Wallet store removed - using AppKit composables instead

export function useNumberInput(initialValue = '', options = {}) {
  const rawValue = ref(initialValue || '')
  const displayValue = ref(initialValue || '')
  
  // Simple comma formatting function
  function addCommas(value) {
    if (!value || value === '') return ''
    
    // Remove existing commas and clean the value
    const cleaned = value.toString().replace(/[^0-9.]/g, '')
    if (!cleaned) return ''
    
    const parts = cleaned.split('.')
    const integerPart = parts[0]
    const decimalPart = parts[1]
    
    // Add commas to integer part
    const withCommas = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
    
    // Reconstruct with decimal if it exists
    return decimalPart !== undefined ? `${withCommas}.${decimalPart}` : withCommas
  }
  
  // Remove commas for calculations
  function removeCommas(value) {
    if (!value) return ''
    return value.toString().replace(/,/g, '')
  }
  
  function handleInput(inputValue) {
    const cleanValue = removeCommas(inputValue)
    rawValue.value = cleanValue
    displayValue.value = addCommas(cleanValue)
  }
  
  function handleFocus() {
    // On focus, show clean value for easy editing
    displayValue.value = rawValue.value
  }
  
  function handleBlur() {
    // On blur, show formatted value with commas
    displayValue.value = addCommas(rawValue.value)
  }
  
  function handleKeypress(event) {
    const char = String.fromCharCode(event.which)
    // Allow only numbers and decimal point
    if (!/[0-9.]/.test(char)) {
      event.preventDefault()
    }
  }
  
  function handlePaste(event) {
    event.preventDefault()
    const pastedText = (event.clipboardData || window.clipboardData).getData('text')
    handleInput(pastedText)
  }
  
  return {
    displayValue,
    rawValue: computed(() => rawValue.value),
    handleInput,
    handleFocus,
    handleBlur,
    handleKeypress,
    handlePaste
  }
}


/**
 * OTC Configuration composable
 * Manages dynamic OTC discount tiers and configuration
 * Can be updated from external sources or admin panel
 */
export function useOtcConfig() {
  
  // Default OTC configuration
  const defaultConfig = {
    discountTiers: [
      { minAmount: 50000, discount: 12, vestingMonths: 6 },  // $50K+: 12%
      { minAmount: 10000, discount: 8, vestingMonths: 6 },   // $10K+: 8%  
      { minAmount: 1000, discount: 5, vestingMonths: 6 }     // $1K+: 5%
    ],
    vestingPeriod: {
      months: 6,
      type: 'linear'
    },
    fees: {
      otc: 0.15,    // 0.15% for OTC swaps
      liquid: 0.3   // 0.3% for liquid swaps
    },
    displayRange: '5-12%',
    enabled: true,
    minimumAmount: 1000, // Minimum USD amount for OTC
    maximumAmount: null  // No maximum limit
  }

  // Reactive configuration
  const otcConfig = ref({ ...defaultConfig })
  const isLoading = ref(false)
  const lastUpdated = ref(null)
  const error = ref(null)

  // Computed properties
  const discountTiers = computed(() => otcConfig.value.discountTiers)
  const vestingPeriod = computed(() => otcConfig.value.vestingPeriod)
  const fees = computed(() => otcConfig.value.fees)
  const isEnabled = computed(() => otcConfig.value.enabled)
  const minimumOtcAmount = computed(() => otcConfig.value.minimumAmount)

  // Get display range for UI
  const displayRange = computed(() => {
    const tiers = discountTiers.value
    if (!tiers || tiers.length === 0) return '0%'
    
    const minDiscount = Math.min(...tiers.map(t => t.discount))
    const maxDiscount = Math.max(...tiers.map(t => t.discount))
    
    if (minDiscount === maxDiscount) {
      return `${minDiscount}%`
    }
    
    return `${minDiscount}-${maxDiscount}%`
  })

  /**
   * Fetch OTC configuration from external source
   */
  const fetchConfig = async (url = '/swap/discount.json') => {
    try {
      isLoading.value = true
      error.value = null

      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache'
        }
      })

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const config = await response.json()
      
      // Validate configuration structure
      const validatedConfig = validateConfig(config)
      
      if (validatedConfig) {
        otcConfig.value = { ...defaultConfig, ...validatedConfig }
        lastUpdated.value = new Date()
        console.log('OTC config updated:', validatedConfig)
        return true
      } else {
        throw new Error('Invalid configuration format')
      }

    } catch (err) {
      console.warn('Failed to fetch OTC config:', err.message)
      error.value = err.message
      return false
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Validate configuration structure
   */
  const validateConfig = (config) => {
    try {
      // Check required fields
      if (!config || typeof config !== 'object') {
        return null
      }

      const validated = {}

      // Validate discount tiers
      if (config.discountTiers && Array.isArray(config.discountTiers)) {
        const validTiers = config.discountTiers.filter(tier => 
          tier && 
          typeof tier.minAmount === 'number' && tier.minAmount > 0 &&
          typeof tier.discount === 'number' && tier.discount > 0 && tier.discount <= 50 &&
          (!tier.vestingMonths || (typeof tier.vestingMonths === 'number' && tier.vestingMonths > 0))
        ).sort((a, b) => b.minAmount - a.minAmount) // Sort by minAmount descending

        if (validTiers.length > 0) {
          validated.discountTiers = validTiers
        }
      }

      // Validate vesting period
      if (config.vestingPeriod && typeof config.vestingPeriod === 'object') {
        const { months, type } = config.vestingPeriod
        if (typeof months === 'number' && months > 0 && 
            typeof type === 'string' && ['linear', 'cliff'].includes(type)) {
          validated.vestingPeriod = { months, type }
        }
      }

      // Validate fees
      if (config.fees && typeof config.fees === 'object') {
        const validFees = {}
        if (typeof config.fees.otc === 'number' && config.fees.otc >= 0 && config.fees.otc <= 5) {
          validFees.otc = config.fees.otc
        }
        if (typeof config.fees.liquid === 'number' && config.fees.liquid >= 0 && config.fees.liquid <= 5) {
          validFees.liquid = config.fees.liquid
        }
        if (Object.keys(validFees).length > 0) {
          validated.fees = validFees
        }
      }

      // Validate other fields
      if (typeof config.enabled === 'boolean') {
        validated.enabled = config.enabled
      }

      if (typeof config.minimumAmount === 'number' && config.minimumAmount > 0) {
        validated.minimumAmount = config.minimumAmount
      }

      if (config.maximumAmount === null || (typeof config.maximumAmount === 'number' && config.maximumAmount > 0)) {
        validated.maximumAmount = config.maximumAmount
      }

      return Object.keys(validated).length > 0 ? validated : null

    } catch (err) {
      console.error('Config validation error:', err)
      return null
    }
  }

  /**
   * Reset to default configuration
   */
  const resetToDefault = () => {
    otcConfig.value = { ...defaultConfig }
    lastUpdated.value = new Date()
    error.value = null
  }

  /**
   * Update specific configuration values
   */
  const updateConfig = (updates) => {
    const validatedUpdates = validateConfig(updates)
    if (validatedUpdates) {
      otcConfig.value = { ...otcConfig.value, ...validatedUpdates }
      lastUpdated.value = new Date()
      return true
    }
    return false
  }

  /**
   * Get discount for specific USD amount
   */
  const getDiscountForAmount = (usdAmount) => {
    if (!isEnabled.value || usdAmount < minimumOtcAmount.value) {
      return 0
    }

    for (const tier of discountTiers.value) {
      if (usdAmount >= tier.minAmount) {
        return tier.discount
      }
    }
    
    return 0
  }

  /**
   * Check if amount qualifies for OTC
   */
  const qualifiesForOtc = (usdAmount) => {
    return isEnabled.value && usdAmount >= minimumOtcAmount.value
  }

  /**
   * Get tier information for amount
   */
  const getTierInfo = (usdAmount) => {
    const discount = getDiscountForAmount(usdAmount)
    const tier = discountTiers.value.find(t => usdAmount >= t.minAmount)
    
    return {
      qualifies: discount > 0,
      discount,
      tier,
      nextTier: discountTiers.value.find(t => t.minAmount > usdAmount),
      vestingMonths: tier?.vestingMonths || vestingPeriod.value.months,
      vestingType: vestingPeriod.value.type
    }
  }

  /**
   * Auto-fetch configuration on initialization
   */
  const initialize = async () => {
    await fetchConfig()
  }

  // Auto-initialize when composable is first used
  // This ensures discountTiers are immediately available
  if (process.client) {
    // Only fetch on client-side to avoid SSR issues
    initialize().catch(() => {
      // Silently fall back to default config if fetch fails
      console.log('Using default OTC configuration')
    })
  }

  return {
    // Reactive state
    otcConfig,
    isLoading,
    lastUpdated,
    error,
    
    // Computed properties  
    discountTiers,
    vestingPeriod,
    fees,
    isEnabled,
    minimumOtcAmount,
    displayRange,
    
    // Methods
    fetchConfig,
    resetToDefault,
    updateConfig,
    initialize,
    
    // Utility functions
    getDiscountForAmount,
    qualifiesForOtc,
    getTierInfo,
    validateConfig
  }
}



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

/**
 * Swap service for backend API integration
 * Provides payment processing and CIRX token swaps via backend
 * Environment-based address configuration for payment monitoring
 */
export function useSwapService() {
  // TODO: Replace with AppKit composables when implementing contract interactions
  // const { address, isConnected } = useAppKitAccount()
  const runtimeConfig = useRuntimeConfig()

  // Contract configuration based on environment
  const CONTRACT_CONFIG = {
    // Production addresses (will be populated when contracts are deployed)
    production: {
      CIRX_TOKEN: process.env.NUXT_PUBLIC_CIRX_TOKEN_ADDRESS || null,
      VESTING_CONTRACT: process.env.NUXT_PUBLIC_VESTING_CONTRACT_ADDRESS || null,
      OTC_SWAP: process.env.NUXT_PUBLIC_OTC_SWAP_ADDRESS || null,
      USDC: process.env.NUXT_PUBLIC_USDC_ADDRESS || '0xA0b86a33E6417c5c6E9c8B9b4f5b5e4E8d8e8d8e',
      USDT: process.env.NUXT_PUBLIC_USDT_ADDRESS || '0xB0b86a33E6417c5c6E9c8B9b4f5b5e4E8d8e8d8e'
    },
    
    // Development/testing addresses
    development: {
      CIRX_TOKEN: null, // Will be set when local contracts are deployed
      VESTING_CONTRACT: null,
      OTC_SWAP: null,
      USDC: '0xA0b86a33E6417c5c6E9c8B9b4f5b5e4E8d8e8d8e', // Mock addresses for dev
      USDT: '0xB0b86a33E6417c5c6E9c8B9b4f5b5e4E8d8e8d8e'
    }
  }

  // Get current environment configuration
  const isDevelopment = process.env.NODE_ENV === 'development'
  const currentConfig = isDevelopment ? CONTRACT_CONFIG.development : CONTRACT_CONFIG.production

  // Contract addresses with null checks
  const CONTRACT_ADDRESSES = computed(() => ({
    CIRX_TOKEN: currentConfig.CIRX_TOKEN,
    VESTING_CONTRACT: currentConfig.VESTING_CONTRACT,
    OTC_SWAP: currentConfig.OTC_SWAP,
    USDC: currentConfig.USDC,
    USDT: currentConfig.USDT,
    ETH: '0x0000000000000000000000000000000000000000' // Native ETH
  }))

  // Contract deployment status
  const contractsDeployed = computed(() => ({
    cirxToken: !!CONTRACT_ADDRESSES.value.CIRX_TOKEN,
    vestingContract: !!CONTRACT_ADDRESSES.value.VESTING_CONTRACT,
    otcSwap: !!CONTRACT_ADDRESSES.value.OTC_SWAP,
    allDeployed: !!(CONTRACT_ADDRESSES.value.CIRX_TOKEN && 
                    CONTRACT_ADDRESSES.value.VESTING_CONTRACT && 
                    CONTRACT_ADDRESSES.value.OTC_SWAP)
  }))

  // Contract ABIs
  const ABIS = {
    ERC20: [
      {
        name: 'balanceOf',
        type: 'function',
        stateMutability: 'view',
        inputs: [{ name: 'account', type: 'address' }],
        outputs: [{ name: '', type: 'uint256' }]
      },
      {
        name: 'allowance',
        type: 'function',
        stateMutability: 'view',
        inputs: [
          { name: 'owner', type: 'address' },
          { name: 'spender', type: 'address' }
        ],
        outputs: [{ name: '', type: 'uint256' }]
      },
      {
        name: 'approve',
        type: 'function',
        stateMutability: 'nonpayable',
        inputs: [
          { name: 'spender', type: 'address' },
          { name: 'amount', type: 'uint256' }
        ],
        outputs: [{ name: '', type: 'bool' }]
      },
      {
        name: 'transfer',
        type: 'function',
        stateMutability: 'nonpayable',
        inputs: [
          { name: 'to', type: 'address' },
          { name: 'amount', type: 'uint256' }
        ],
        outputs: [{ name: '', type: 'bool' }]
      }
    ],

    OTC_SWAP: [
      {
        name: 'getLiquidQuote',
        type: 'function',
        stateMutability: 'view',
        inputs: [
          { name: 'inputToken', type: 'address' },
          { name: 'inputAmount', type: 'uint256' }
        ],
        outputs: [
          { name: 'cirxAmount', type: 'uint256' },
          { name: 'fee', type: 'uint256' }
        ]
      },
      {
        name: 'getOTCQuote',
        type: 'function',
        stateMutability: 'view',
        inputs: [
          { name: 'inputToken', type: 'address' },
          { name: 'inputAmount', type: 'uint256' }
        ],
        outputs: [
          { name: 'cirxAmount', type: 'uint256' },
          { name: 'fee', type: 'uint256' },
          { name: 'discountBps', type: 'uint256' }
        ]
      },
      {
        name: 'swapLiquid',
        type: 'function',
        stateMutability: 'payable',
        inputs: [
          { name: 'inputToken', type: 'address' },
          { name: 'inputAmount', type: 'uint256' },
          { name: 'minCirxOut', type: 'uint256' }
        ],
        outputs: []
      },
      {
        name: 'swapOTC',
        type: 'function',
        stateMutability: 'payable',
        inputs: [
          { name: 'inputToken', type: 'address' },
          { name: 'inputAmount', type: 'uint256' },
          { name: 'minCirxOut', type: 'uint256' }
        ],
        outputs: []
      }
    ],

    VESTING: [
      {
        name: 'getVestingInfo',
        type: 'function',
        stateMutability: 'view',
        inputs: [{ name: 'user', type: 'address' }],
        outputs: [
          { name: 'totalAmount', type: 'uint256' },
          { name: 'startTime', type: 'uint256' },
          { name: 'claimedAmount', type: 'uint256' },
          { name: 'claimableAmount', type: 'uint256' },
          { name: 'isActive', type: 'bool' }
        ]
      },
      {
        name: 'claimTokens',
        type: 'function',
        stateMutability: 'nonpayable',
        inputs: [],
        outputs: []
      }
    ]
  }

  // Helper functions
  const validateConnection = () => {
    if (!walletStore.isConnected) {
      throw new Error('Wallet not connected')
    }
    
    if (walletStore.activeChain !== 'ethereum') {
      throw new Error('Ethereum wallet required for contract interactions')
    }

    if (!walletStore.activeWallet?.isOnSupportedChain) {
      throw new Error('Please switch to a supported network')
    }
  }

  const validateContractAddress = (contractType) => {
    const address = CONTRACT_ADDRESSES.value[contractType]
    if (!address) {
      throw new Error(`${contractType} contract not deployed or configured`)
    }
    return address
  }

  const getTokenDecimals = (tokenSymbol) => {
    // Most tokens use 18 decimals, but we can customize here
    const decimals = {
      ETH: 18,
      USDC: 6,  // USDC uses 6 decimals
      USDT: 6,  // USDT uses 6 decimals
      CIRX: 18
    }
    return decimals[tokenSymbol] || 18
  }

  // Token balance operations
  const getTokenBalance = async (tokenSymbol, userAddress = null) => {
    try {
      validateConnection()
      
      const address = userAddress || walletStore.activeWallet?.address
      if (!address) {
        throw new Error('No address provided')
      }

      // Handle native ETH
      if (tokenSymbol === 'ETH') {
        const balance = await walletStore.ethereumWallet.publicClient?.getBalance({ address })
        return balance ? formatEther(balance) : '0'
      }

      // Handle ERC20 tokens
      const tokenAddress = CONTRACT_ADDRESSES.value[tokenSymbol]
      if (!tokenAddress) {
        throw new Error(`Token ${tokenSymbol} not configured`)
      }

      const balance = await walletStore.ethereumWallet.publicClient?.readContract({
        address: tokenAddress,
        abi: ABIS.ERC20,
        functionName: 'balanceOf',
        args: [address]
      })

      const decimals = getTokenDecimals(tokenSymbol)
      return balance ? formatUnits(balance, decimals) : '0'

    } catch (error) {
      console.error(`Failed to get ${tokenSymbol} balance:`, error)
      
      // Return mock balance in development when contracts aren't deployed
      if (isDevelopment && !contractsDeployed.value.allDeployed) {
        const mockBalances = {
          ETH: '1.5',
          USDC: '1000.00',
          USDT: '500.00',
          CIRX: '0.00'
        }
        return mockBalances[tokenSymbol] || '0'
      }

      throw error
    }
  }

  // Token approval operations
  const approveToken = async (tokenSymbol, spenderAddress, amount) => {
    try {
      validateConnection()
      
      if (tokenSymbol === 'ETH') {
        return null // ETH doesn't need approval
      }

      const tokenAddress = validateContractAddress(tokenSymbol)
      const decimals = getTokenDecimals(tokenSymbol)
      const amountWei = parseUnits(amount.toString(), decimals)

      const hash = await walletStore.ethereumWallet.walletClient?.writeContract({
        address: tokenAddress,
        abi: ABIS.ERC20,
        functionName: 'approve',
        args: [spenderAddress, amountWei]
      })

      return hash

    } catch (error) {
      console.error(`Failed to approve ${tokenSymbol}:`, error)
      throw error
    }
  }

  const getAllowance = async (tokenSymbol, ownerAddress, spenderAddress) => {
    try {
      if (tokenSymbol === 'ETH') {
        return '999999999' // ETH doesn't need approval
      }

      const tokenAddress = validateContractAddress(tokenSymbol)
      
      const allowance = await walletStore.ethereumWallet.publicClient?.readContract({
        address: tokenAddress,
        abi: ABIS.ERC20,
        functionName: 'allowance',
        args: [ownerAddress, spenderAddress]
      })

      const decimals = getTokenDecimals(tokenSymbol)
      return allowance ? formatUnits(allowance, decimals) : '0'

    } catch (error) {
      console.error(`Failed to get ${tokenSymbol} allowance:`, error)
      return '0'
    }
  }

  // Swap quote operations
  const getLiquidQuote = async (inputToken, inputAmount) => {
    try {
      if (!contractsDeployed.value.otcSwap) {
        // Return mock quote for development
        const mockPrice = inputToken === 'ETH' ? 2500 : 1 // $2500 per ETH, $1 per stablecoin
        return {
          cirxAmount: (parseFloat(inputAmount) * mockPrice).toFixed(2),
          fee: (parseFloat(inputAmount) * mockPrice * 0.003).toFixed(4), // 0.3% fee
          feePercentage: '0.3'
        }
      }

      validateConnection()
      const contractAddress = validateContractAddress('OTC_SWAP')
      const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
      const decimals = getTokenDecimals(inputToken)
      const amountWei = parseUnits(inputAmount.toString(), decimals)

      const [cirxAmount, fee] = await walletStore.ethereumWallet.publicClient?.readContract({
        address: contractAddress,
        abi: ABIS.OTC_SWAP,
        functionName: 'getLiquidQuote',
        args: [tokenAddress, amountWei]
      })

      return {
        cirxAmount: formatUnits(cirxAmount, 18),
        fee: formatUnits(fee, 18),
        feePercentage: '0.3' // Could be dynamic based on contract
      }

    } catch (error) {
      console.error('Failed to get liquid quote:', error)
      throw error
    }
  }

  const getOTCQuote = async (inputToken, inputAmount) => {
    try {
      if (!contractsDeployed.value.otcSwap) {
        // Return mock quote for development
        const mockPrice = inputToken === 'ETH' ? 2500 : 1
        const baseAmount = parseFloat(inputAmount) * mockPrice
        const usdValue = baseAmount

        // Mock discount tiers
        let discount = 0
        if (usdValue >= 50000) discount = 12
        else if (usdValue >= 10000) discount = 8
        else if (usdValue >= 1000) discount = 5

        const discountMultiplier = 1 + (discount / 100)
        const cirxAmount = baseAmount * discountMultiplier

        return {
          cirxAmount: cirxAmount.toFixed(2),
          fee: (cirxAmount * 0.0015).toFixed(4), // 0.15% fee for OTC
          discount: discount.toString(),
          feePercentage: '0.15'
        }
      }

      validateConnection()
      const contractAddress = validateContractAddress('OTC_SWAP')
      const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
      const decimals = getTokenDecimals(inputToken)
      const amountWei = parseUnits(inputAmount.toString(), decimals)

      const [cirxAmount, fee, discountBps] = await walletStore.ethereumWallet.publicClient?.readContract({
        address: contractAddress,
        abi: ABIS.OTC_SWAP,
        functionName: 'getOTCQuote',
        args: [tokenAddress, amountWei]
      })

      return {
        cirxAmount: formatUnits(cirxAmount, 18),
        fee: formatUnits(fee, 18),
        discount: (Number(discountBps) / 100).toString(),
        feePercentage: '0.15'
      }

    } catch (error) {
      console.error('Failed to get OTC quote:', error)
      throw error
    }
  }

  // Swap execution operations
  const executeLiquidSwap = async (inputToken, inputAmount, minCirxOut, slippageTolerance = 0.5) => {
    try {
      validateConnection()
      
      // Since contracts aren't deployed, use backend API approach
      if (!contractsDeployed.value.otcSwap) {
        
        // Step 1: User sends payment transaction to monitored address
        const paymentAddress = '0x834244D016F29d6acb42C1B054a88e2e9b1c9228' // From .env.local
        const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
        const decimals = getTokenDecimals(inputToken)
        const amountWei = parseUnits(inputAmount.toString(), decimals)

        let paymentHash
        if (inputToken === 'ETH') {
          // Send ETH to payment address
          paymentHash = await walletStore.ethereumWallet.walletClient?.sendTransaction({
            to: paymentAddress,
            value: amountWei
          })
        } else {
          // Send ERC20 token to payment address
          paymentHash = await walletStore.ethereumWallet.walletClient?.writeContract({
            address: tokenAddress,
            abi: ABIS.ERC20,
            functionName: 'transfer',
            args: [paymentAddress, amountWei]
          })
        }

        if (!paymentHash) {
          throw new Error('Payment transaction failed')
        }

        // Step 2: Call backend API to initiate swap processing
        const response = await $fetch(`${runtimeConfig.public.apiBaseUrl}/transactions/initiate-swap`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            txId: paymentHash,
            paymentChain: 'ethereum',
            cirxRecipientAddress: walletStore.activeWallet.address,
            amountPaid: inputAmount.toString(),
            paymentToken: inputToken
          })
        })

        return {
          success: true,
          hash: paymentHash,
          swapId: response.swapId,
          type: 'liquid'
        }
      }

      // Original contract code (kept for when contracts are deployed)
      const contractAddress = validateContractAddress('OTC_SWAP')
      const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
      const decimals = getTokenDecimals(inputToken)
      const amountWei = parseUnits(inputAmount.toString(), decimals)
      
      // Apply slippage tolerance to minimum output
      const slippageMultiplier = 1 - (slippageTolerance / 100)
      const adjustedMinOut = (parseFloat(minCirxOut) * slippageMultiplier).toString()
      const minOutWei = parseUnits(adjustedMinOut, 18)

      // Handle approval for ERC20 tokens
      if (inputToken !== 'ETH') {
        const currentAllowance = await getAllowance(
          inputToken, 
          walletStore.activeWallet.address, 
          contractAddress
        )
        
        if (parseFloat(currentAllowance) < parseFloat(inputAmount)) {
          await approveToken(inputToken, contractAddress, inputAmount)
        }
      }

      // Execute swap
      const hash = await walletStore.ethereumWallet.walletClient?.writeContract({
        address: contractAddress,
        abi: ABIS.OTC_SWAP,
        functionName: 'swapLiquid',
        args: [tokenAddress, amountWei, minOutWei],
        value: inputToken === 'ETH' ? amountWei : 0n
      })

      return {
        success: true,
        hash,
        type: 'liquid'
      }

    } catch (error) {
      console.error('Liquid swap failed:', error)
      throw error
    }
  }

  const executeOTCSwap = async (inputToken, inputAmount, minCirxOut, slippageTolerance = 0.5) => {
    try {
      validateConnection()
      
      // Since contracts aren't deployed, use backend API approach
      if (!contractsDeployed.value.otcSwap) {
        
        // Step 1: User sends payment transaction to monitored address
        const paymentAddress = '0x834244D016F29d6acb42C1B054a88e2e9b1c9228' // From .env.local
        const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
        const decimals = getTokenDecimals(inputToken)
        const amountWei = parseUnits(inputAmount.toString(), decimals)

        let paymentHash
        if (inputToken === 'ETH') {
          // Send ETH to payment address
          paymentHash = await walletStore.ethereumWallet.walletClient?.sendTransaction({
            to: paymentAddress,
            value: amountWei
          })
        } else {
          // Send ERC20 token to payment address
          paymentHash = await walletStore.ethereumWallet.walletClient?.writeContract({
            address: tokenAddress,
            abi: ABIS.ERC20,
            functionName: 'transfer',
            args: [paymentAddress, amountWei]
          })
        }

        if (!paymentHash) {
          throw new Error('Payment transaction failed')
        }

        // Step 2: Call backend API to initiate swap processing
        const response = await $fetch(`${runtimeConfig.public.apiBaseUrl}/transactions/initiate-swap`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            txId: paymentHash,
            paymentChain: 'ethereum',
            cirxRecipientAddress: walletStore.activeWallet.address,
            amountPaid: inputAmount.toString(),
            paymentToken: inputToken
          })
        })

        return {
          success: true,
          hash: paymentHash,
          swapId: response.swapId,
          type: 'otc'
        }
      }

      // Original contract code (kept for when contracts are deployed)
      const contractAddress = validateContractAddress('OTC_SWAP')
      const tokenAddress = CONTRACT_ADDRESSES.value[inputToken]
      const decimals = getTokenDecimals(inputToken)
      const amountWei = parseUnits(inputAmount.toString(), decimals)
      
      // Apply slippage tolerance
      const slippageMultiplier = 1 - (slippageTolerance / 100)
      const adjustedMinOut = (parseFloat(minCirxOut) * slippageMultiplier).toString()
      const minOutWei = parseUnits(adjustedMinOut, 18)

      // Handle approval for ERC20 tokens
      if (inputToken !== 'ETH') {
        const currentAllowance = await getAllowance(
          inputToken, 
          walletStore.activeWallet.address, 
          contractAddress
        )
        
        if (parseFloat(currentAllowance) < parseFloat(inputAmount)) {
          await approveToken(inputToken, contractAddress, inputAmount)
        }
      }

      // Execute OTC swap
      const hash = await walletStore.ethereumWallet.walletClient?.writeContract({
        address: contractAddress,
        abi: ABIS.OTC_SWAP,
        functionName: 'swapOTC',
        args: [tokenAddress, amountWei, minOutWei],
        value: inputToken === 'ETH' ? amountWei : 0n
      })

      return {
        success: true,
        hash,
        type: 'otc'
      }

    } catch (error) {
      console.error('OTC swap failed:', error)
      throw error
    }
  }

  // Vesting operations
  const getVestingInfo = async (userAddress = null) => {
    try {
      const address = userAddress || walletStore.activeWallet?.address
      if (!address) {
        throw new Error('No address provided')
      }

      if (!contractsDeployed.value.vestingContract) {
        // Return mock vesting info for development
        return {
          totalAmount: '0',
          startTime: 0,
          claimedAmount: '0',
          claimableAmount: '0',
          isActive: false
        }
      }

      validateConnection()
      const contractAddress = validateContractAddress('VESTING_CONTRACT')

      const [totalAmount, startTime, claimedAmount, claimableAmount, isActive] = 
        await walletStore.ethereumWallet.publicClient?.readContract({
          address: contractAddress,
          abi: ABIS.VESTING,
          functionName: 'getVestingInfo',
          args: [address]
        })

      return {
        totalAmount: formatUnits(totalAmount, 18),
        startTime: Number(startTime),
        claimedAmount: formatUnits(claimedAmount, 18),
        claimableAmount: formatUnits(claimableAmount, 18),
        isActive
      }

    } catch (error) {
      console.error('Failed to get vesting info:', error)
      throw error
    }
  }

  const claimVestedTokens = async () => {
    try {
      validateConnection()
      
      if (!contractsDeployed.value.vestingContract) {
        throw new Error('Vesting contract not deployed. Please contact support.')
      }

      const contractAddress = validateContractAddress('VESTING_CONTRACT')

      const hash = await walletStore.ethereumWallet.walletClient?.writeContract({
        address: contractAddress,
        abi: ABIS.VESTING,
        functionName: 'claimTokens',
        args: []
      })

      return {
        success: true,
        hash
      }

    } catch (error) {
      console.error('Claim failed:', error)
      throw error
    }
  }

  // Return the interface
  return {
    // Configuration
    CONTRACT_ADDRESSES,
    contractsDeployed,
    isDevelopment,
    
    // Token operations
    getTokenBalance,
    approveToken,
    getAllowance,
    
    // Quote operations
    getLiquidQuote,
    getOTCQuote,
    
    // Swap operations
    executeLiquidSwap,
    executeOTCSwap,
    
    // Vesting operations
    getVestingInfo,
    claimVestedTokens,
    
    // Utilities
    validateConnection,
    validateContractAddress,
    getTokenDecimals,
    
    // Constants
    ABIS
  }
}