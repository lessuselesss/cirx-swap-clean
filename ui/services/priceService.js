/**
 * Real-time price feed service
 * Fetches live token prices from multiple sources with fallback
 */

const PRICE_CACHE_DURATION = 30000 // 30 seconds
let priceCache = {}
let lastFetch = 0

/**
 * Fetch real CIRX/USDT price via backend API
 * Uses aggregated exchange data from backend to avoid CORS issues
 */
const fetchCIRXPrice = async () => {
  try {
    console.log('🔄 Fetching CIRX/USDT price from backend aggregator...')
    
    // Use our existing aggregateMarket backend integration
    const { AggregateMarket } = await import('../scripts/aggregateMarket.js')
    const market = AggregateMarket.getInstance()
    
    const marketData = await market.getMarketData('CIRX', 'USDT')
    
    if (marketData && marketData.averagePrice) {
      const price = parseFloat(marketData.averagePrice)
      
      if (price && typeof price === 'number' && !isNaN(price) && price > 0 && price < 100) {
        console.log(`✅ CIRX/USDT price from backend aggregator: $${price}`)
        return price
      } else {
        console.warn(`❌ Invalid price from backend aggregator:`, price)
      }
    } else {
      console.warn('❌ No market data from backend aggregator')
    }
    
  } catch (error) {
    console.warn('❌ Failed to fetch CIRX from backend aggregator:', error.message)
  }
  
  // Fallback to conservative estimate if backend fails
  console.warn('⚠️ Using fallback CIRX/USDT price - backend aggregator failed')
  return 0.004 // Conservative fallback: $0.004 USDT per CIRX token (typical trading range)
}

/**
 * CoinGecko price API for major tokens
 * CoinGecko has permissive CORS and usually works in browsers
 */
const fetchCoinGeckoPrices = async () => {
  try {
    console.log('🔄 Fetching major token prices from CoinGecko...')
    
    const response = await fetch(
      'https://api.coingecko.com/api/v3/simple/price?ids=ethereum,solana,tether,usd-coin&vs_currencies=usd',
      {
        headers: {
          'Accept': 'application/json',
        }
      }
    )
    
    if (!response.ok) {
      throw new Error(`CoinGecko API error: ${response.status}`)
    }
    
    const data = await response.json()
    console.log('✅ Major token prices from CoinGecko:', data)
    
    return {
      ETH: data.ethereum?.usd || 0,
      SOL: data.solana?.usd || 0,
      USDC: data['usd-coin']?.usd || 1,
      USDT: data.tether?.usd || 1
    }
  } catch (error) {
    console.warn('CoinGecko price fetch failed:', error.message)
    console.log('📊 Using fallback prices for major tokens')
    
    // Fallback to conservative estimates if CoinGecko fails
    return {
      ETH: 2500,   // Conservative ETH price in USD
      SOL: 100,    // Conservative SOL price in USD  
      USDC: 1.0,   // USDC should be ~$1 USD
      USDT: 1.0    // USDT should be ~$1 USD
    }
  }
}

/**
 * Fallback price source - conservative estimates
 */
const getFallbackPrices = () => {
  console.warn('Using fallback price data')
  return {
    ETH: 2500,   // Conservative fallback prices
    SOL: 100,    
    USDC: 1,     
    USDT: 1,     
    CIRX: 0.15   // Conservative CIRX estimate based on typical trading range
  }
}

/**
 * Get current token prices with caching
 */
export const getTokenPrices = async () => {
  const now = Date.now()
  
  // Return cached prices if still fresh
  if (priceCache.data && (now - lastFetch) < PRICE_CACHE_DURATION) {
    return priceCache.data
  }
  
  try {
    console.log('🔄 Starting token price fetch process...')
    
    // Step 1: Fetch major token prices from CoinGecko (USD-denominated)
    let majorTokenPrices = await fetchCoinGeckoPrices()
    
    // If CoinGecko fails, use fallback prices for major tokens
    if (!majorTokenPrices) {
      console.warn('⚠️ CoinGecko failed, using fallback prices for major tokens')
      majorTokenPrices = {
        ETH: 2500,   // Conservative ETH price in USD
        SOL: 100,    // Conservative SOL price in USD
        USDC: 1.0,   // USDC should be ~$1 USD
        USDT: 1.0    // USDT should be ~$1 USD (base currency for CIRX)
      }
    }
    
    // Step 2: Fetch CIRX price separately (priced in USDT from exchanges)
    console.log('🔄 Fetching CIRX/USDT price from exchanges...')
    const cirxPriceInUsdt = await fetchCIRXPrice()
    
    // Step 3: Convert CIRX price to USD using USDT rate with NaN protection
    // CIRX/USD = CIRX/USDT × USDT/USD
    const usdtPrice = majorTokenPrices.USDT || 1.0
    
    // Validate conversion inputs
    if (typeof cirxPriceInUsdt !== 'number' || typeof usdtPrice !== 'number' ||
        isNaN(cirxPriceInUsdt) || isNaN(usdtPrice) || 
        cirxPriceInUsdt <= 0 || usdtPrice <= 0) {
      console.error('Invalid price conversion inputs:', { cirxPriceInUsdt, usdtPrice })
      throw new Error('Invalid CIRX or USDT price for conversion')
    }
    
    const cirxPriceInUsd = cirxPriceInUsdt * usdtPrice
    
    // Validate conversion result
    if (!isFinite(cirxPriceInUsd) || cirxPriceInUsd <= 0) {
      console.error('Invalid CIRX USD price result:', { cirxPriceInUsdt, usdtPrice, cirxPriceInUsd })
      throw new Error('CIRX price conversion resulted in invalid value')
    }
    
    console.log(`💱 Price conversion: CIRX ${cirxPriceInUsdt} USDT × ${usdtPrice} USDT/USD = ${cirxPriceInUsd} USD`)
    
    // Step 4: Combine all prices in USD denomination
    const allPrices = {
      ...majorTokenPrices,
      CIRX: cirxPriceInUsd  // Now in USD like other tokens
    }
    
    // Step 5: Cache the results with metadata
    priceCache = {
      data: allPrices,
      timestamp: now,
      source: majorTokenPrices === getFallbackPrices() ? 'mixed-fallback' : 'live-mixed',
      cirxSource: 'exchanges', // CIRX from live exchanges
      lastCirxPrice: cirxPriceInUsdt,
      lastUsdtRate: usdtPrice
    }
    lastFetch = now
    
    console.log('✅ All token prices updated (USD-denominated):', {
      ETH: `$${allPrices.ETH}`,
      SOL: `$${allPrices.SOL}`, 
      USDC: `$${allPrices.USDC}`,
      USDT: `$${allPrices.USDT}`,
      CIRX: `$${allPrices.CIRX} (from ${cirxPriceInUsdt} USDT)`
    })
    
    return allPrices
    
  } catch (error) {
    console.error('❌ Complete price fetch failed, using fallbacks:', error)
    
    const fallbackPrices = getFallbackPrices()
    
    priceCache = {
      data: fallbackPrices,
      timestamp: now,
      source: 'complete-fallback',
      error: error.message
    }
    lastFetch = now
    
    console.warn('📊 Using complete fallback prices:', fallbackPrices)
    return fallbackPrices
  }
}

/**
 * Get price for a specific token
 */
export const getTokenPrice = async (tokenSymbol) => {
  const prices = await getTokenPrices()
  return prices[tokenSymbol] || 0
}

/**
 * Check if prices are from live feed or fallback
 */
export const getPriceSource = () => {
  return priceCache.source || 'unknown'
}

/**
 * Force refresh prices (bypass cache)
 */
export const refreshPrices = async () => {
  lastFetch = 0
  return await getTokenPrices()
}

/**
 * Get detailed cache status for debugging
 */
export const getCacheInfo = () => {
  const ageMs = priceCache.timestamp ? Date.now() - priceCache.timestamp : 0
  const ageMinutes = Math.floor(ageMs / 60000)
  
  return {
    hasCache: !!priceCache.data,
    age: {
      milliseconds: ageMs,
      minutes: ageMinutes,
      isStale: ageMs > PRICE_CACHE_DURATION
    },
    source: priceCache.source,
    cirxSource: priceCache.cirxSource,
    lastCirxPrice: priceCache.lastCirxPrice,
    lastUsdtRate: priceCache.lastUsdtRate,
    error: priceCache.error,
    data: priceCache.data,
    cacheExpiry: priceCache.timestamp ? new Date(priceCache.timestamp + PRICE_CACHE_DURATION) : null
  }
}

/**
 * Get CIRX price specifically with source information
 */
export const getCirxPriceInfo = async () => {
  const prices = await getTokenPrices()
  const cacheInfo = getCacheInfo()
  
  return {
    priceUsd: prices.CIRX,
    priceUsdt: cacheInfo.lastCirxPrice,
    usdtRate: cacheInfo.lastUsdtRate,
    source: cacheInfo.cirxSource,
    timestamp: cacheInfo.age.milliseconds,
    isLive: cacheInfo.source?.includes('live'),
    isFallback: cacheInfo.source?.includes('fallback')
  }
}

/**
 * Test CIRX price fetching directly (for debugging)
 */
export const testCirxFetch = async () => {
  console.log('🧪 Testing CIRX price fetch directly...')
  try {
    const cirxPrice = await fetchCIRXPrice()
    console.log('✅ Direct CIRX fetch result:', cirxPrice)
    return cirxPrice
  } catch (error) {
    console.error('❌ Direct CIRX fetch failed:', error)
    throw error
  }
}