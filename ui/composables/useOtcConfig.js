import { ref, computed } from 'vue'

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