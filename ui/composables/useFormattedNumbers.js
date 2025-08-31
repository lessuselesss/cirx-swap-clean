/**
 * Formatted Numbers and Address Validation Composable
 * Provides address formatting, validation utilities, and number formatting
 */

import { ref, computed } from 'vue'

export function useFormattedNumbers() {
  /**
   * Validate if a string is a valid Ethereum address
   * @param {string} address - The address to validate
   * @returns {boolean} - True if valid Ethereum address
   */
  const isValidEthereumAddress = (address) => {
    if (!address || typeof address !== 'string') return false
    // Check if it's a valid hex string starting with 0x and 40 hex characters
    return /^0x[a-fA-F0-9]{40}$/.test(address)
  }

  /**
   * Validate if a string is a valid Solana address
   * @param {string} address - The address to validate
   * @returns {boolean} - True if valid Solana address
   */
  const isValidSolanaAddress = (address) => {
    if (!address || typeof address !== 'string') return false
    // Solana addresses are base58 encoded, 32-44 characters
    return /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(address)
  }

  /**
   * Validate if a string is a valid Circular Protocol address
   * Circular addresses must be exactly 0x + 64 hex characters (66 total)
   * @param {string} address - The address to validate
   * @returns {boolean} - True if valid Circular address
   */
  const isValidCircularAddress = (address) => {
    if (!address || typeof address !== 'string') return false
    
    const trimmed = address.trim()
    
    // Circular addresses are 0x + exactly 64 hex characters (66 total)
    return (
      trimmed.startsWith('0x') &&
      trimmed.length === 66 &&
      /^0x[a-fA-F0-9]{64}$/.test(trimmed)
    )
  }

  /**
   * Determine the type of address
   * @param {string} address - The address to analyze
   * @returns {string|null} - Address type: 'ethereum', 'solana', 'circular', or null if invalid
   */
  const getAddressType = (address) => {
    if (!address || typeof address !== 'string') return null
    
    // Check for Circular Protocol address first (66 chars: 0x + 64 hex)
    if (isValidCircularAddress(address)) {
      return 'circular'
    }
    
    // Check for Ethereum address (42 chars: 0x + 40 hex)
    if (isValidEthereumAddress(address)) {
      return 'ethereum'
    }
    
    // Check for Solana address
    if (isValidSolanaAddress(address)) {
      return 'solana'
    }
    
    return null
  }

  /**
   * Format address for display (truncate middle)
   * @param {string} address - The address to format
   * @param {number} prefixLength - Number of characters to show at start
   * @param {number} suffixLength - Number of characters to show at end
   * @returns {string} - Formatted address
   */
  const formatAddress = (address, prefixLength = 6, suffixLength = 4) => {
    if (!address || address.length <= prefixLength + suffixLength) {
      return address || ''
    }
    
    return `${address.slice(0, prefixLength)}...${address.slice(-suffixLength)}`
  }

  /**
   * Check if address is valid for a specific chain
   * @param {string} address - The address to validate
   * @param {string} chain - The chain type ('ethereum', 'solana', 'circular')
   * @returns {boolean} - True if valid for the specified chain
   */
  const isValidAddressForChain = (address, chain) => {
    switch (chain?.toLowerCase()) {
      case 'ethereum':
        return isValidEthereumAddress(address)
      case 'solana':
        return isValidSolanaAddress(address)
      case 'circular':
        return isValidCircularAddress(address)
      default:
        return false
    }
  }

  /**
   * Format numbers with proper decimal places and commas
   * @param {number|string} value - The number to format
   * @param {number} decimals - Number of decimal places
   * @returns {string} - Formatted number
   */
  const formatNumber = (value, decimals = 2) => {
    if (value === null || value === undefined || value === '') return '0'
    
    const numValue = typeof value === 'string' ? parseFloat(value) : value
    if (isNaN(numValue)) return '0'
    
    return numValue.toLocaleString('en-US', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    })
  }

  /**
   * Format currency values
   * @param {number|string} value - The value to format
   * @param {string} currency - Currency symbol (default: '$')
   * @param {number} decimals - Number of decimal places
   * @returns {string} - Formatted currency
   */
  const formatCurrency = (value, currency = '$', decimals = 2) => {
    const formatted = formatNumber(value, decimals)
    return `${currency}${formatted}`
  }

  /**
   * Format token amounts with proper decimal handling
   * @param {number|string} value - The token amount
   * @param {string} symbol - Token symbol
   * @param {number} decimals - Number of decimal places
   * @returns {string} - Formatted token amount
   */
  const formatTokenAmount = (value, symbol = '', decimals = 6) => {
    const formatted = formatNumber(value, decimals)
    return symbol ? `${formatted} ${symbol}` : formatted
  }

  /**
   * Format percentage values
   * @param {number|string} value - The percentage value
   * @param {number} decimals - Number of decimal places
   * @returns {string} - Formatted percentage
   */
  const formatPercentage = (value, decimals = 2) => {
    const formatted = formatNumber(value, decimals)
    return `${formatted}%`
  }

  return {
    // Address validation functions
    isValidEthereumAddress,
    isValidSolanaAddress,
    isValidCircularAddress,
    getAddressType,
    formatAddress,
    isValidAddressForChain,
    
    // Number formatting functions
    formatNumber,
    formatCurrency,
    formatTokenAmount,
    formatPercentage
  }
}

/**
 * useNumberInput Composable - Basic version with comma formatting
 */

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
 * Real ERC-20 token balance and interaction service
 * Provides live token balances and contract interactions
 */

// ERC-20 token contract addresses (mainnet)
export const TOKEN_ADDRESSES = {
  USDC: '0xA0b86a33E6411d5F68EC6CECa5E4Ef10Cb8a92Bb', // USDC on Ethereum mainnet
  USDT: '0xdAC17F958D2ee523a2206206994597C13D831ec7', // USDT on Ethereum mainnet
  // CIRX: '0x...' // Will be added when CIRX contract is deployed
}

// ERC-20 ABI (minimal for balance queries)
const ERC20_ABI = [
  {
    "constant": true,
    "inputs": [{"name": "_owner", "type": "address"}],
    "name": "balanceOf",
    "outputs": [{"name": "balance", "type": "uint256"}],
    "type": "function"
  },
  {
    "constant": true,
    "inputs": [],
    "name": "decimals",
    "outputs": [{"name": "", "type": "uint8"}],
    "type": "function"
  },
  {
    "constant": true,
    "inputs": [],
    "name": "symbol",
    "outputs": [{"name": "", "type": "string"}],
    "type": "function"
  }
]

/**
 * Get ETH balance for an address
 */
export const getETHBalance = async (address, provider) => {
  try {
    if (!provider || !address) return '0'
    
    const balanceWei = await provider.request({
      method: 'eth_getBalance',
      params: [address, 'latest']
    })
    
    // Convert Wei to ETH
    const balanceEth = parseInt(balanceWei, 16) / Math.pow(10, 18)
    return balanceEth.toFixed(6)
  } catch (error) {
    console.warn('Failed to fetch ETH balance:', error)
    return '0'
  }
}

/**
 * Get ERC-20 token balance for an address
 */
export const getTokenBalance = async (tokenSymbol, address, provider) => {
  try {
    if (!provider || !address || !TOKEN_ADDRESSES[tokenSymbol]) {
      return '0'
    }
    
    const contractAddress = TOKEN_ADDRESSES[tokenSymbol]
    
    // Create contract call data for balanceOf(address)
    const data = '0x70a08231' + address.slice(2).padStart(64, '0')
    
    const result = await provider.request({
      method: 'eth_call',
      params: [{
        to: contractAddress,
        data: data
      }, 'latest']
    })
    
    if (!result || result === '0x') {
      return '0'
    }
    
    // Convert hex result to decimal and adjust for token decimals
    const balance = parseInt(result, 16)
    const decimals = getTokenDecimals(tokenSymbol)
    const formattedBalance = balance / Math.pow(10, decimals)
    
    return formattedBalance.toFixed(6)
  } catch (error) {
    console.warn(`Failed to fetch ${tokenSymbol} balance:`, error)
    return '0'
  }
}

/**
 * Get token decimals (standard values)
 */
const getTokenDecimals = (tokenSymbol) => {
  const decimals = {
    USDC: 6,
    USDT: 6,
    CIRX: 18, // Assumed standard
    ETH: 18
  }
  return decimals[tokenSymbol] || 18
}

/**
 * Get all token balances for an address
 */
export const getAllTokenBalances = async (address, provider) => {
  if (!provider || !address) {
    return {
      ETH: '0',
      USDC: '0', 
      USDT: '0',
      CIRX: '0'
    }
  }
  
  try {
    // Fetch all balances in parallel
    const [ethBalance, usdcBalance, usdtBalance] = await Promise.all([
      getETHBalance(address, provider),
      getTokenBalance('USDC', address, provider),
      getTokenBalance('USDT', address, provider),
      // getTokenBalance('CIRX', address, provider), // Enable when CIRX contract is ready
    ])
    
    return {
      ETH: ethBalance,
      USDC: usdcBalance,
      USDT: usdtBalance,
      CIRX: '0' // Placeholder until CIRX contract is deployed
    }
  } catch (error) {
    console.error('Failed to fetch token balances:', error)
    
    // Return fallback values
    return {
      ETH: '0',
      USDC: '0',
      USDT: '0', 
      CIRX: '0'
    }
  }
}

/**
 * Check if token contract exists and is valid
 */
export const validateTokenContract = async (tokenSymbol, provider) => {
  try {
    if (tokenSymbol === 'ETH') return true
    
    const contractAddress = TOKEN_ADDRESSES[tokenSymbol]
    if (!contractAddress || !provider) return false
    
    // Try to get token symbol from contract
    const data = '0x95d89b41' // symbol() function selector
    
    const result = await provider.request({
      method: 'eth_call',
      params: [{
        to: contractAddress,
        data: data
      }, 'latest']
    })
    
    return result && result !== '0x'
  } catch (error) {
    console.warn(`Token contract validation failed for ${tokenSymbol}:`, error)
    return false
  }
}

/**
 * Format balance for display
 */
export const formatBalance = (balance, decimals = 6) => {
  const num = parseFloat(balance)
  if (isNaN(num) || num === 0) return '0'
  
  if (num < 0.000001) return '< 0.000001'
  if (num < 1) return num.toFixed(decimals)
  if (num < 1000) return num.toFixed(4)
  if (num < 1000000) return (num / 1000).toFixed(2) + 'K'
  
  return (num / 1000000).toFixed(2) + 'M'
}

/**
 * Check if address has sufficient balance for swap
 */
export const hasSufficientBalance = async (address, tokenSymbol, amount, provider) => {
  try {
    const balance = tokenSymbol === 'ETH' 
      ? await getETHBalance(address, provider)
      : await getTokenBalance(tokenSymbol, address, provider)
    
    const available = parseFloat(balance)
    const required = parseFloat(amount)
    
    // Reserve gas fees for ETH transactions
    const gasReserve = tokenSymbol === 'ETH' ? 0.001 : 0
    
    return available >= (required + gasReserve)
  } catch (error) {
    console.warn('Balance check failed:', error)
    return false
  }
}

/**
 * Formatting utilities
 * Consolidates duplicate formatting logic across the codebase
 */

/**
 * Format numbers with proper locale and options
 * @param {number|string} value - Value to format
 * @param {object} options - Formatting options
 * @returns {string} Formatted number
 */
export function formatNumber(value, options = {}) {
  const {
    decimals = 2,
    locale = 'en-US',
    style = 'decimal',
    currency = 'USD',
    compact = false,
    showSign = false
  } = options

  if (value === null || value === undefined || value === '') {
    return '0'
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value
  
  if (isNaN(numValue)) {
    return '0'
  }

  const formatOptions = {
    style,
    minimumFractionDigits: style === 'currency' ? 2 : 0,
    maximumFractionDigits: decimals
  }

  if (style === 'currency') {
    formatOptions.currency = currency
  }

  if (compact) {
    formatOptions.notation = 'compact'
    formatOptions.compactDisplay = 'short'
  }

  if (showSign) {
    formatOptions.signDisplay = 'always'
  }

  try {
    return new Intl.NumberFormat(locale, formatOptions).format(numValue)
  } catch (error) {
    console.warn('Number formatting error:', error)
    return numValue.toString()
  }
}

/**
 * Format token amounts with appropriate decimal places
 * @param {number|string} amount - Token amount
 * @param {object} options - Formatting options
 * @returns {string} Formatted token amount
 */
export function formatTokenAmount(amount, options = {}) {
  const {
    decimals = 6,
    symbol = '',
    compact = false,
    showFullPrecision = false
  } = options

  if (!amount || amount === '0') {
    return `0${symbol ? ' ' + symbol : ''}`
  }

  const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount
  
  if (isNaN(numAmount)) {
    return `0${symbol ? ' ' + symbol : ''}`
  }

  let formattedAmount

  if (showFullPrecision) {
    // Show full precision for very small amounts
    formattedAmount = numAmount.toString()
  } else if (compact && numAmount >= 1000) {
    // Use compact notation for large amounts
    formattedAmount = formatNumber(numAmount, { decimals: 2, compact: true })
  } else if (numAmount < 0.001) {
    // Use scientific notation for very small amounts
    formattedAmount = numAmount.toExponential(2)
  } else if (numAmount < 1) {
    // More decimals for amounts less than 1
    formattedAmount = formatNumber(numAmount, { decimals: Math.min(decimals + 2, 8) })
  } else {
    // Standard formatting
    formattedAmount = formatNumber(numAmount, { decimals })
  }

  return `${formattedAmount}${symbol ? ' ' + symbol : ''}`
}

/**
 * Format currency amounts
 * @param {number|string} amount - Currency amount
 * @param {object} options - Formatting options
 * @returns {string} Formatted currency
 */
export function formatCurrency(amount, options = {}) {
  const {
    currency = 'USD',
    locale = 'en-US',
    compact = false
  } = options

  return formatNumber(amount, {
    style: 'currency',
    currency,
    locale,
    compact,
    decimals: 2
  })
}

/**
 * Format percentage values
 * @param {number|string} value - Percentage value (0.15 for 15%)
 * @param {object} options - Formatting options
 * @returns {string} Formatted percentage
 */
export function formatPercentage(value, options = {}) {
  const {
    decimals = 2,
    locale = 'en-US',
    multiply100 = true
  } = options

  if (value === null || value === undefined || value === '') {
    return '0%'
  }

  const numValue = typeof value === 'string' ? parseFloat(value) : value
  
  if (isNaN(numValue)) {
    return '0%'
  }

  const displayValue = multiply100 ? numValue * 100 : numValue

  try {
    return new Intl.NumberFormat(locale, {
      style: 'percent',
      minimumFractionDigits: 0,
      maximumFractionDigits: decimals
    }).format(multiply100 ? numValue : numValue / 100)
  } catch (error) {
    return `${displayValue.toFixed(decimals)}%`
  }
}

/**
 * Format time durations
 * @param {number} seconds - Duration in seconds
 * @param {object} options - Formatting options
 * @returns {string} Formatted duration
 */
export function formatDuration(seconds, options = {}) {
  const {
    format = 'auto', // 'auto', 'short', 'long'
    showSeconds = true
  } = options

  if (!seconds || seconds <= 0) {
    return format === 'long' ? '0 seconds' : '0s'
  }

  const units = [
    { label: format === 'long' ? 'year' : 'y', seconds: 31536000 },
    { label: format === 'long' ? 'month' : 'mo', seconds: 2592000 },
    { label: format === 'long' ? 'day' : 'd', seconds: 86400 },
    { label: format === 'long' ? 'hour' : 'h', seconds: 3600 },
    { label: format === 'long' ? 'minute' : 'm', seconds: 60 },
    { label: format === 'long' ? 'second' : 's', seconds: 1 }
  ]

  const parts = []
  let remaining = Math.floor(seconds)

  for (const unit of units) {
    if (remaining >= unit.seconds) {
      const count = Math.floor(remaining / unit.seconds)
      remaining -= count * unit.seconds
      
      if (format === 'long') {
        parts.push(`${count} ${unit.label}${count !== 1 ? 's' : ''}`)
      } else {
        parts.push(`${count}${unit.label}`)
      }

      if (format === 'auto' && parts.length >= 2) break
      if (!showSeconds && unit.label.includes('second')) break
    }
  }

  if (parts.length === 0) {
    return format === 'long' ? '0 seconds' : '0s'
  }

  return format === 'long' ? parts.join(', ') : parts.join(' ')
}

/**
 * Format file sizes
 * @param {number} bytes - Size in bytes
 * @param {object} options - Formatting options
 * @returns {string} Formatted file size
 */
export function formatFileSize(bytes, options = {}) {
  const {
    decimals = 1,
    binary = true
  } = options

  if (bytes === 0) return '0 B'

  const k = binary ? 1024 : 1000
  const sizes = binary 
    ? ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB']
    : ['B', 'KB', 'MB', 'GB', 'TB', 'PB']

  const i = Math.floor(Math.log(bytes) / Math.log(k))
  const size = bytes / Math.pow(k, i)

  return `${size.toFixed(decimals)} ${sizes[i]}`
}

/**
 * Format transaction hashes
 * @param {string} hash - Transaction hash
 * @param {object} options - Formatting options
 * @returns {string} Formatted hash
 */
export function formatTransactionHash(hash, options = {}) {
  const {
    startChars = 8,
    endChars = 6,
    separator = '...'
  } = options

  if (!hash) return ''

  if (hash.length <= startChars + endChars + separator.length) {
    return hash
  }

  return `${hash.slice(0, startChars)}${separator}${hash.slice(-endChars)}`
}

/**
 * Format relative time (time ago)
 * @param {Date|string|number} date - Date to format
 * @param {object} options - Formatting options
 * @returns {string} Formatted relative time
 */
export function formatTimeAgo(date, options = {}) {
  const {
    locale = 'en-US',
    numeric = 'auto' // 'auto', 'always'
  } = options

  if (!date) return ''

  const now = new Date()
  const targetDate = new Date(date)
  
  if (isNaN(targetDate.getTime())) {
    return 'Invalid date'
  }

  try {
    const rtf = new Intl.RelativeTimeFormat(locale, { numeric })
    const diffInSeconds = Math.floor((targetDate.getTime() - now.getTime()) / 1000)

    const units = [
      { unit: 'year', seconds: 31536000 },
      { unit: 'month', seconds: 2592000 },
      { unit: 'day', seconds: 86400 },
      { unit: 'hour', seconds: 3600 },
      { unit: 'minute', seconds: 60 },
      { unit: 'second', seconds: 1 }
    ]

    for (const { unit, seconds } of units) {
      const interval = Math.floor(Math.abs(diffInSeconds) / seconds)
      if (interval >= 1) {
        return rtf.format(diffInSeconds < 0 ? -interval : interval, unit)
      }
    }

    return rtf.format(0, 'second')
  } catch (error) {
    console.warn('Relative time formatting error:', error)
    return targetDate.toLocaleDateString()
  }
}