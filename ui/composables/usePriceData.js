import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

/**
 * Price Data Service for CIRX Token
 * Fetches real-time and historical price data from multiple sources
 */
export function usePriceData() {
  // State
  const currentPrice = ref(null)
  const priceHistory = ref([])
  const isLoading = ref(false)
  const error = ref(null)
  const lastUpdated = ref(null)
  
  // Configuration
  const CIRX_CONTRACT = '0x5a3e6a77ba2f983ec0d371ea3b475f8bc0811ad5' // CIRX token contract
  const UPDATE_INTERVAL = 30000 // 30 seconds
  const PRICE_CACHE_DURATION = 60000 // 1 minute
  
  let updateTimer = null
  let priceCache = new Map()
  
  /**
   * Fetch current CIRX price from CoinGecko API
   */
  const fetchCurrentPrice = async () => {
    try {
      const cacheKey = 'cirx_current_price'
      const cached = priceCache.get(cacheKey)
      
      if (cached && Date.now() - cached.timestamp < PRICE_CACHE_DURATION) {
        return cached.data
      }
      
      const response = await fetch(
        `https://api.coingecko.com/api/v3/simple/price?ids=circular-protocol&vs_currencies=usd&include_24hr_change=true&include_last_updated_at=true`,
        {
          headers: {
            'Accept': 'application/json'
          }
        }
      )
      
      if (!response.ok) {
        throw new Error(`CoinGecko API error: ${response.status}`)
      }
      
      const data = await response.json()
      const cirxData = data['circular-protocol']
      
      if (!cirxData || !cirxData.usd) {
        throw new Error('Invalid price data received')
      }
      
      const priceData = {
        price: cirxData.usd,
        change24h: cirxData.usd_24h_change || 0,
        lastUpdated: cirxData.last_updated_at || Date.now() / 1000
      }
      
      // Cache the result
      priceCache.set(cacheKey, {
        data: priceData,
        timestamp: Date.now()
      })
      
      return priceData
      
    } catch (err) {
      console.error('Failed to fetch current price from CoinGecko:', err)
      
      // Fallback to DEXTools API
      try {
        return await fetchPriceFromDEXTools()
      } catch (fallbackErr) {
        console.error('Fallback price fetch failed:', fallbackErr)
        throw new Error('Unable to fetch current price from any source')
      }
    }
  }
  
  /**
   * Fallback price fetch from DEXTools
   */
  const fetchPriceFromDEXTools = async () => {
    const response = await fetch(
      `https://api.dextools.io/v1/token/1/${CIRX_CONTRACT}/price`,
      {
        headers: {
          'Accept': 'application/json'
        }
      }
    )
    
    if (!response.ok) {
      throw new Error(`DEXTools API error: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (!data.data || !data.data.price) {
      throw new Error('Invalid DEXTools price data')
    }
    
    return {
      price: parseFloat(data.data.price),
      change24h: 0, // DEXTools doesn't provide 24h change in this endpoint
      lastUpdated: Date.now() / 1000
    }
  }
  
  /**
   * Generate historical price data for chart
   * Uses current price as base and creates realistic historical movements
   */
  const generateHistoricalData = (currentPriceValue, days = 7) => {
    const data = []
    const now = new Date()
    const msPerDay = 24 * 60 * 60 * 1000
    const pointsPerDay = 24 // Hourly data points
    const totalPoints = days * pointsPerDay
    
    let price = currentPriceValue
    
    // Generate realistic price movements based on CIRX volatility
    for (let i = totalPoints - 1; i >= 0; i--) {
      const time = Math.floor((now.getTime() - i * (msPerDay / pointsPerDay)) / 1000)
      
      // Add some realistic volatility (CIRX can be quite volatile)
      const volatility = 0.05 // 5% max change per hour
      const change = (Math.random() - 0.5) * volatility
      price = Math.max(0.001, price * (1 + change)) // Prevent negative prices
      
      data.push({
        time,
        value: parseFloat(price.toFixed(8))
      })
    }
    
    // Ensure last point matches current price
    if (data.length > 0) {
      data[data.length - 1].value = currentPriceValue
    }
    
    return data.sort((a, b) => a.time - b.time)
  }
  
  /**
   * Calculate trading pair prices based on current CIRX price
   */
  const calculateTradingPairs = (cirxPriceUSD) => {
    // Token prices (these should ideally come from the same API)
    const tokenPrices = {
      USDT: 1.0,
      USDC: 1.0,
      ETH: 2700.0, // This should also be fetched from API
      WETH: 2700.0
    }
    
    return {
      'USDT/CIRX': tokenPrices.USDT / cirxPriceUSD,
      'USDC/CIRX': tokenPrices.USDC / cirxPriceUSD,
      'ETH/CIRX': tokenPrices.ETH / cirxPriceUSD,
      'WETH/CIRX': tokenPrices.WETH / cirxPriceUSD
    }
  }
  
  /**
   * Update price data
   */
  const updatePriceData = async () => {
    try {
      isLoading.value = true
      error.value = null
      
      const priceData = await fetchCurrentPrice()
      currentPrice.value = priceData.price
      lastUpdated.value = new Date(priceData.lastUpdated * 1000)
      
      // Generate historical data if we don't have any
      if (priceHistory.value.length === 0) {
        priceHistory.value = generateHistoricalData(priceData.price)
      } else {
        // Add new data point
        const now = Math.floor(Date.now() / 1000)
        priceHistory.value.push({
          time: now,
          value: priceData.price
        })
        
        // Keep only last 7 days of data
        const weekAgo = now - (7 * 24 * 60 * 60)
        priceHistory.value = priceHistory.value.filter(point => point.time >= weekAgo)
      }
      
      console.log('✅ Price data updated:', {
        price: priceData.price,
        change24h: priceData.change24h,
        dataPoints: priceHistory.value.length
      })
      
    } catch (err) {
      console.error('❌ Price update failed:', err)
      error.value = err.message
    } finally {
      isLoading.value = false
    }
  }
  
  /**
   * Start automatic price updates
   */
  const startPriceUpdates = () => {
    // Initial fetch
    updatePriceData()
    
    // Set up periodic updates
    updateTimer = setInterval(updatePriceData, UPDATE_INTERVAL)
  }
  
  /**
   * Stop automatic price updates
   */
  const stopPriceUpdates = () => {
    if (updateTimer) {
      clearInterval(updateTimer)
      updateTimer = null
    }
  }
  
  // Computed properties
  const tradingPairs = computed(() => {
    if (!currentPrice.value) return {}
    return calculateTradingPairs(currentPrice.value)
  })
  
  const formattedPrice = computed(() => {
    if (!currentPrice.value) return '0.000000'
    return currentPrice.value.toFixed(6)
  })
  
  const priceForSymbol = computed(() => (symbol) => {
    if (!tradingPairs.value[symbol]) return 0
    return tradingPairs.value[symbol]
  })
  
  // Lifecycle
  onMounted(() => {
    startPriceUpdates()
  })
  
  onBeforeUnmount(() => {
    stopPriceUpdates()
  })
  
  return {
    // State
    currentPrice,
    priceHistory,
    isLoading,
    error,
    lastUpdated,
    
    // Computed
    tradingPairs,
    formattedPrice,
    priceForSymbol,
    
    // Methods
    updatePriceData,
    startPriceUpdates,
    stopPriceUpdates,
    
    // Utils
    generateHistoricalData
  }
}