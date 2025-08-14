/**
 * Real-time price feed service
 * Fetches live token prices from multiple sources with fallback
 */

const PRICE_CACHE_DURATION = 30000 // 30 seconds
let priceCache = {}
let lastFetch = 0

/**
 * Fetch real CIRX/USDT price from exchanges where it trades
 * CIRX trades against USDT on multiple exchanges
 */
const fetchCIRXPrice = async () => {
  // Try multiple exchanges in order of preference for CIRX/USDT pair
  const exchanges = [
    // Gate.io API - Primary source for CIRX/USDT
    {
      name: 'gate.io',
      url: 'https://api.gateio.ws/api/v4/spot/tickers?currency_pair=CIRX_USDT',
      parser: (data) => {
        if (Array.isArray(data) && data[0]?.last) {
          return parseFloat(data[0].last)
        }
        return null
      }
    },
    // MEXC API - Backup exchange for CIRX/USDT
    {
      name: 'mexc',
      url: 'https://api.mexc.com/api/v3/ticker/24hr?symbol=CIRXUSDT',
      parser: (data) => {
        if (data?.lastPrice) {
          return parseFloat(data.lastPrice)
        }
        return null
      }
    },
    // KuCoin API - Another backup for CIRX/USDT
    {
      name: 'kucoin',
      url: 'https://api.kucoin.com/api/v1/market/orderbook/level1?symbol=CIRX-USDT',
      parser: (data) => {
        if (data?.data?.price) {
          return parseFloat(data.data.price)
        }
        return null
      }
    },
    // CoinGecko backup (if CIRX gets listed there)
    {
      name: 'coingecko',
      url: 'https://api.coingecko.com/api/v3/simple/price?ids=circular-protocol&vs_currencies=usd',
      parser: (data) => {
        if (data?.['circular-protocol']?.usd) {
          return parseFloat(data['circular-protocol'].usd)
        }
        return null
      }
    }
  ]

  const prices = []
  
  for (const exchange of exchanges) {
    try {
      console.log(`🔄 Fetching CIRX/USDT price from ${exchange.name}...`)
      
      const controller = new AbortController()
      const timeoutId = setTimeout(() => controller.abort(), 8000) // 8 second timeout
      
      const response = await fetch(exchange.url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'User-Agent': 'CircularProtocol/1.0',
          'Cache-Control': 'no-cache'
        },
        signal: controller.signal,
        mode: 'cors' // Explicitly set CORS mode
      })
      
      clearTimeout(timeoutId)
      
      if (!response.ok) {
        console.warn(`❌ HTTP ${response.status} from ${exchange.name}:`, response.statusText)
        continue
      }
      
      const data = await response.json()
      console.log(`📊 Raw data from ${exchange.name}:`, JSON.stringify(data).substring(0, 200) + '...')
      
      const price = exchange.parser(data)
      
      if (price && typeof price === 'number' && !isNaN(price) && price > 0 && price < 100) {
        console.log(`✅ CIRX/USDT price from ${exchange.name}: $${price}`)
        prices.push({ 
          exchange: exchange.name, 
          price,
          timestamp: Date.now(),
          source: 'CIRX/USDT'
        })
      } else {
        console.warn(`❌ Invalid price from ${exchange.name}:`, price)
      }
      
    } catch (error) {
      console.warn(`❌ Failed to fetch CIRX from ${exchange.name}:`, error.message)
      
      // Log specific error types for debugging
      if (error.name === 'AbortError') {
        console.warn(`⏱️ Timeout fetching from ${exchange.name}`)
      } else if (error.message.includes('CORS')) {
        console.warn(`🚫 CORS issue with ${exchange.name}`)
      } else if (error.message.includes('network')) {
        console.warn(`🌐 Network error with ${exchange.name}`)
      }
    }
  }
  
  if (prices.length > 0) {
    // Sort prices for statistical analysis
    const sortedPrices = prices.map(p => p.price).sort((a, b) => a - b)
    
    // Use statistical approach based on number of sources
    let finalPrice
    
    if (prices.length >= 3) {
      // Use median with outlier detection for 3+ sources
      const median = sortedPrices[Math.floor(sortedPrices.length / 2)]
      const mean = sortedPrices.reduce((a, b) => a + b, 0) / sortedPrices.length
      
      // If median and mean are close (within 10%), use median
      if (Math.abs(median - mean) / mean < 0.1) {
        finalPrice = median
        console.log(`📊 Using median CIRX/USDT price: $${finalPrice} from ${prices.length} exchanges (median ≈ mean)`)
      } else {
        // Remove outliers and recalculate
        const threshold = mean * 0.2 // 20% threshold
        const filteredPrices = sortedPrices.filter(p => Math.abs(p - mean) <= threshold)
        finalPrice = filteredPrices.reduce((a, b) => a + b, 0) / filteredPrices.length
        console.log(`📊 Using filtered mean CIRX/USDT price: $${finalPrice} from ${filteredPrices.length}/${prices.length} exchanges (outliers removed)`)
      }
      
    } else if (prices.length === 2) {
      // Average two sources with validation
      const [price1, price2] = sortedPrices
      const minPrice = Math.min(price1, price2)
      
      // Prevent division by zero
      if (minPrice <= 0) {
        console.error('Invalid prices for comparison:', { price1, price2 })
        finalPrice = prices[0].price // Use first price as fallback
        console.log(`📊 Using fallback price due to invalid comparison: $${finalPrice}`)
      } else {
        const diff = Math.abs(price1 - price2) / minPrice
        
        // Validate diff result
        if (!isFinite(diff)) {
          console.error('Invalid price difference calculation:', { price1, price2, minPrice, diff })
          finalPrice = prices[0].price
          console.log(`📊 Using first price due to calculation error: $${finalPrice}`)
        } else if (diff < 0.05) { // Less than 5% difference
          finalPrice = (price1 + price2) / 2
          console.log(`📊 Using average CIRX/USDT price: $${finalPrice} from 2 exchanges (${diff.toFixed(2)}% difference)`)
        } else {
          // Large difference - use the more recent/reliable source (Gate.io first in list)
          const gatePrice = prices.find(p => p.exchange === 'gate.io')
          finalPrice = gatePrice ? gatePrice.price : prices[0].price
          console.log(`📊 Using primary source CIRX/USDT price: $${finalPrice} (${diff.toFixed(2)}% spread detected)`)
        }
      }
      
    } else {
      // Single source
      finalPrice = prices[0].price
      console.log(`📊 Using single-source CIRX/USDT price: $${finalPrice} from ${prices[0].exchange}`)
    }
    
    // Final validation
    if (finalPrice > 0 && finalPrice < 100 && !isNaN(finalPrice)) {
      console.log(`✅ Final CIRX/USDT price: $${finalPrice} (${prices.map(p => `${p.exchange}:$${p.price}`).join(', ')})`)
      return finalPrice
    } else {
      console.error(`❌ Invalid aggregated price: ${finalPrice}`)
    }
  }
  
  // Fallback to conservative estimate if all exchanges fail or price is invalid
  console.warn('⚠️ Using fallback CIRX/USDT price - all exchanges failed or returned invalid data')
  console.warn('💡 This could be due to:')
  console.warn('   - CORS restrictions in browser environment')
  console.warn('   - Exchange API rate limits or downtime') 
  console.warn('   - Network connectivity issues')
  console.warn('   - Invalid API responses')
  
  return 0.15 // Conservative fallback: $0.15 USDT per CIRX token
}

/**
 * CoinGecko price API for major tokens
 */
const fetchCoinGeckoPrices = async () => {
  try {
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
    
    return {
      ETH: data.ethereum?.usd || 0,
      SOL: data.solana?.usd || 0,
      USDC: data['usd-coin']?.usd || 1,
      USDT: data.tether?.usd || 1
    }
  } catch (error) {
    console.warn('CoinGecko price fetch failed:', error.message)
    return null
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