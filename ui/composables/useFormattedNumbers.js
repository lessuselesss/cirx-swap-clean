/**
 * Formatted Numbers and Address Validation Composable
 * Provides address formatting, validation utilities, and number formatting
 */

import { ref, computed, watch } from 'vue'
import { useAddressValidation } from './core/useAddressValidation.js'
import { useFormattingUtils } from './core/useFormattingUtils.js'

export function useFormattedNumbers() {
  // Import consolidated address validation functions
  const { 
    isValidEthereumAddress: validEth,
    isValidSolanaAddress: validSol,
    isValidCircularAddress: validCirc,
    detectAddressType,
    isValidAddress
  } = useAddressValidation()
  
  // Import consolidated formatting functions
  const { formatAddress: formatAddr } = useFormattingUtils()

  // Use imported validators for all address types
  const isValidEthereumAddress = validEth
  const isValidSolanaAddress = validSol
  const isValidCircularAddress = validCirc

  // Custom address type detection for this project's requirements
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

  // Use imported formatAddress function
  const formatAddress = formatAddr

  // Custom chain validation using imported validators
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

  // NOTE: Formatting functions moved to ~/composables/core/useFormattingUtils.js
  // Import that composable instead of duplicating formatting logic here

  return {
    // Address validation functions
    isValidEthereumAddress,
    isValidSolanaAddress,
    isValidCircularAddress,
    getAddressType,
    formatAddress,
    isValidAddressForChain
    
    // NOTE: Number formatting functions moved to ~/composables/core/useFormattingUtils.js
    // formatNumber, formatCurrency, formatTokenAmount, formatPercentage
  }
}

/**
 * useNumberInput Composable - Basic version with comma formatting
 */

export function useNumberInput(initialValue = '', options = {}) {
  // Handle both direct values and refs
  const getValue = () => {
    if (typeof initialValue === 'function') {
      return initialValue() || ''
    }
    return (typeof initialValue === 'object' && initialValue.value !== undefined) 
      ? initialValue.value || '' 
      : initialValue || ''
  }
  
  const rawValue = ref(getValue())
  const displayValue = ref(getValue())
  
  // Use comma formatting from consolidated utilities
  const { formatWithCommas } = useFormattingUtils()
  const addCommas = formatWithCommas
  
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
  
  // Watch for changes to the initialValue prop and update internal state
  watch(() => getValue(), (newValue) => {
    console.log('🔧 useNumberInput watch triggered:', { newValue, currentRaw: rawValue.value })
    if (newValue !== rawValue.value) {
      rawValue.value = newValue || ''
      displayValue.value = addCommas(newValue || '')
      console.log('🔧 useNumberInput state updated:', { rawValue: rawValue.value, displayValue: displayValue.value })
    }
  }, { immediate: false })
  
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
 * Vested Configuration composable
 * Manages dynamic vested discount tiers and configuration
 * Can be updated from external sources or admin panel
 */
export function useVestedConfig() {
  
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
  const fetchConfig = async (url) => {
    // Use production URL by default, with development fallback
    if (!url) {
      url = process.client && window.location.hostname === 'localhost' 
        ? '/vested_discount.json'  // Development
        : 'https://circularprotocol.io/buy/vested_discount.json'  // Production
    }
    
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

// REMOVED: Balance management functions moved to useAppKitWallet.js for centralization
// This eliminates 183 lines of duplicate code and ensures single source of truth

