/**
 * Single Exchange Datafeed for TradingView
 * 
 * Provides individual exchange data feeds for BitMart, XT, and LBank
 * Much faster than aggregate as it only calls 1 API instead of 4
 */

export function useSingleExchangeDatafeed() {
  
  const createSingleExchangeDatafeed = (exchange) => {
    return {
      onReady: (callback) => {
        setTimeout(() => {
          callback({
            exchanges: [{ value: exchange.toUpperCase(), name: exchange.charAt(0).toUpperCase() + exchange.slice(1), desc: exchange.toUpperCase() }],
            symbols_types: [],
            supported_resolutions: ['1', '5', '15', '60', '240', '1D'],
            supports_marks: false,
            supports_timescale_marks: false,
            supports_time: true
          })
        }, 0)
      },

      searchSymbols: (userInput, exchange, symbolType, onResultReadyCallback) => {
        const symbols = [
          {
            symbol: 'CIRX/USDT',
            full_name: `${exchange.toUpperCase()}:CIRXUSDT`,
            description: 'CIRX/USDT',
            exchange: exchange.toUpperCase(),
            ticker: 'CIRXUSDT',
            type: 'crypto'
          }
        ]
        onResultReadyCallback(symbols)
      },

      resolveSymbol: (symbolName, onSymbolResolvedCallback, onResolveErrorCallback) => {
        const symbolInfo = {
          ticker: 'CIRXUSDT',
          name: 'CIRX/USDT',
          description: `CIRX/USDT on ${exchange.charAt(0).toUpperCase() + exchange.slice(1)}`,
          type: 'crypto',
          session: '24x7',
          timezone: 'Etc/UTC',
          exchange: exchange.toUpperCase(),
          minmov: 1,
          pricescale: 1000000,  // 6 decimal places for crypto prices
          has_intraday: true,
          has_weekly_and_monthly: false,
          supported_resolutions: ['1', '5', '15', '60', '240', '1D'],
          volume_precision: 2,
          data_status: 'streaming'
        }
        
        setTimeout(() => {
          onSymbolResolvedCallback(symbolInfo)
        }, 0)
      },

      getBars: async (symbolInfo, resolution, periodParams, onHistoryCallback, onErrorCallback) => {
        try {
          console.log(`📊 Fetching ${exchange} data for ${symbolInfo.name}`)
          
          // Fetch data from single exchange API
          const data = await fetchSingleExchangeData(exchange, 'CIRX', 'USDT')
          
          if (!data || !data.price) {
            throw new Error(`No data from ${exchange}`)
          }

          // Generate simple historical bars based on current price
          const bars = generateSimpleBars(data, periodParams, resolution)
          
          onHistoryCallback(bars, { noData: bars.length === 0 })
          
        } catch (error) {
          console.error(`${exchange} data fetch failed:`, error)
          onErrorCallback('Failed to fetch data from ' + exchange)
        }
      },

      subscribeBars: (symbolInfo, resolution, onRealtimeCallback, subscribeUID, onResetCacheNeededCallback) => {
        console.log(`📡 Starting ${exchange} real-time updates`)
        // Simple interval-based updates
        const updateInterval = setInterval(async () => {
          try {
            const data = await fetchSingleExchangeData(exchange, 'CIRX', 'USDT')
            if (data && data.price) {
              const bar = {
                time: Date.now(),
                low: data.price * 0.999,   // Simple simulation
                high: data.price * 1.001,  // Simple simulation
                open: data.price * 0.9995, // Simple simulation
                close: data.price,
                volume: data.volume || 1000
              }
              onRealtimeCallback(bar)
            }
          } catch (error) {
            console.error(`${exchange} real-time update failed:`, error)
          }
        }, 30000) // Update every 30 seconds

        // Store interval ID for cleanup
        window[`${exchange}_interval_${subscribeUID}`] = updateInterval
      },

      unsubscribeBars: (subscriberUID) => {
        const intervalId = window[`${exchange}_interval_${subscriberUID}`]
        if (intervalId) {
          clearInterval(intervalId)
          delete window[`${exchange}_interval_${subscriberUID}`]
        }
      }
    }
  }

  return { createSingleExchangeDatafeed }
}

// Fetch data from individual exchanges
async function fetchSingleExchangeData(exchange, symbol, pair) {
  const timeoutMs = 5000
  
  const fetchWithTimeout = (url) => {
    return Promise.race([
      fetch(url),
      new Promise((_, reject) => 
        setTimeout(() => reject(new Error(`Timeout: ${exchange}`)), timeoutMs)
      )
    ])
  }

  try {
    let url, response, data
    
    switch (exchange.toLowerCase()) {
      case 'bitmart':
        url = `http://localhost:18423/api/v1/proxy/circular-labs?endpoint=CProxy.php&url=${encodeURIComponent(`https://api-cloud.bitmart.com/spot/quotation/v3/ticker?symbol=${symbol.toUpperCase()}_${pair.toUpperCase()}`)}`
        response = await fetchWithTimeout(url)
        if (response.ok) {
          data = await response.json()
          if (data.data) {
            return {
              price: parseFloat(data.data.last),
              volume: parseFloat(data.data.v_24h),
              change: parseFloat(data.data.fluctuation) * 100
            }
          }
        }
        break
        
      case 'xt':
        url = `http://localhost:18423/api/v1/proxy/circular-labs?endpoint=CProxy.php&url=${encodeURIComponent(`https://sapi.xt.com/v4/public/ticker/24h?symbol=${symbol.toLowerCase()}_${pair.toLowerCase()}`)}`
        response = await fetchWithTimeout(url)
        if (response.ok) {
          data = await response.json()
          if (data.result && data.result[0]) {
            return {
              price: parseFloat(data.result[0].c),
              volume: parseFloat(data.result[0].q),
              change: parseFloat(data.result[0].cr) * 100
            }
          }
        }
        break
        
      case 'lbank':
        url = `http://localhost:18423/api/v1/proxy/circular-labs?endpoint=CProxy.php&url=${encodeURIComponent(`https://api.lbkex.com/v2/ticker.do?symbol=${symbol.toLowerCase()}_${pair.toLowerCase()}`)}`
        response = await fetchWithTimeout(url)
        if (response.ok) {
          data = await response.json()
          if (data.data && data.data[0] && data.data[0].ticker) {
            const ticker = data.data[0].ticker
            return {
              price: parseFloat(ticker.latest),
              volume: parseFloat(ticker.vol),
              change: parseFloat(ticker.change) * 100
            }
          }
        }
        break
    }
    
    throw new Error(`No valid data from ${exchange}`)
    
  } catch (error) {
    console.error(`${exchange} API error:`, error.message)
    throw error
  }
}

// Generate simple historical bars from current price
function generateSimpleBars(currentData, periodParams, resolution) {
  const bars = []
  const now = Date.now()
  const price = currentData.price
  const volume = currentData.volume || 1000
  
  // Convert resolution to milliseconds
  const resolutionMs = getResolutionMs(resolution)
  const barsCount = Math.min(100, Math.floor((periodParams.to * 1000 - periodParams.from * 1000) / resolutionMs))
  
  for (let i = barsCount; i > 0; i--) {
    const time = now - (i * resolutionMs)
    const variation = 0.02 // 2% price variation for simulation
    const randomPrice = price * (1 + (Math.random() - 0.5) * variation)
    
    bars.push({
      time: time,
      low: randomPrice * 0.995,
      high: randomPrice * 1.005,
      open: randomPrice * (0.995 + Math.random() * 0.01),
      close: randomPrice,
      volume: volume * (0.8 + Math.random() * 0.4) // Vary volume ±20%
    })
  }
  
  return bars.sort((a, b) => a.time - b.time)
}

function getResolutionMs(resolution) {
  switch (resolution) {
    case '1': return 60 * 1000        // 1 minute
    case '5': return 5 * 60 * 1000    // 5 minutes  
    case '15': return 15 * 60 * 1000  // 15 minutes
    case '60': return 60 * 60 * 1000  // 1 hour
    case '240': return 4 * 60 * 60 * 1000 // 4 hours
    case '1D': return 24 * 60 * 60 * 1000 // 1 day
    default: return 60 * 1000         // Default 1 minute
  }
}