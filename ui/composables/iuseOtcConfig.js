// OTC configuration management composable
import { ref, computed } from 'vue'

export function iuseOtcConfig() {
  const otcConfig = ref({
    discountTiers: [
      { minAmount: 50000, discount: 12, vestingMonths: 24 },  // $50K+: 12%
      { minAmount: 10000, discount: 8, vestingMonths: 12 },   // $10K+: 8%  
      { minAmount: 1000, discount: 5, vestingMonths: 6 }      // $1K+: 5%
    ],
    vestingPeriod: {
      months: 6,
      type: 'linear'
    },
    fees: {
      otc: 0.15,
      liquid: 0.3
    },
    displayRange: '5-12%',
    enabled: true
  })
  
  const fetchOtcConfig = async () => {
    try {
      // Fetch from local JSON file
      const configUrl = '/swap/discount.json'
      
      const response = await fetch(configUrl, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache'
        }
      })
      
      if (response.ok) {
        const config = await response.json()
        
        // Validate and merge config
        if (config.discountTiers && Array.isArray(config.discountTiers)) {
          otcConfig.value = { ...otcConfig.value, ...config }
          console.log('OTC config updated from hosted JSON:', config)
        }
      } else {
        console.warn('Failed to fetch OTC config, using defaults')
      }
    } catch (error) {
      console.warn('Error fetching OTC config:', error.message)
      // Continue with default config
    }
  }
  
  const getTierForUsd = (usdAmount, discountTiers) => {
    // Tiers are defined as minAmount thresholds (e.g., 1000, 10000, 50000)
    // Choose the highest tier that the amount qualifies for
    const tiers = [...(discountTiers || otcConfig.value.discountTiers)].sort((a, b) => b.minAmount - a.minAmount)
    for (const t of tiers) {
      if (usdAmount >= t.minAmount) return t
    }
    return null
  }
  
  const lowestTierMin = computed(() => {
    const tiers = otcConfig.value.discountTiers || []
    if (!tiers.length) return 0
    return Math.min(...tiers.map(t => t.minAmount))
  })
  
  return {
    otcConfig,
    fetchOtcConfig,
    getTierForUsd,
    lowestTierMin
  }
}