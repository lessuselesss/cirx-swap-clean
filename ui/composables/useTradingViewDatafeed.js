/**
 * Custom TradingView Datafeed for CIRX Token
 * Implements the TradingView Datafeed API for real-time and historical data
 */

export const useTradingViewDatafeed = () => {
  // Configuration for supported symbols
  const SUPPORTED_SYMBOLS = {
    'CIRX/USD': {
      name: 'CIRX/USD',
      full_name: 'Circular Protocol/US Dollar',
      description: 'CIRX to USD',
      type: 'crypto',
      session: '24x7',
      timezone: 'Etc/UTC',
      ticker: 'CIRX/USD',
      exchange: 'Circular DEX',
      minmov: 1,
      pricescale: 10000, // 4 decimal places
      has_intraday: true,
      has_no_volume: false,
      has_weekly_and_monthly: true,
      supported_resolutions: ['1', '3', '5', '15', '30', '60', '240', '1D', '1W', '1M'],
      volume_precision: 2,
      data_status: 'streaming'
    },
    'CIRX/ETH': {
      name: 'CIRX/ETH',
      full_name: 'Circular Protocol/Ethereum',
      description: 'CIRX to ETH',
      type: 'crypto',
      session: '24x7',
      timezone: 'Etc/UTC',
      ticker: 'CIRX/ETH',
      exchange: 'Circular DEX',
      minmov: 1,
      pricescale: 100000000, // 8 decimal places for ETH pairs
      has_intraday: true,
      has_no_volume: false,
      has_weekly_and_monthly: true,
      supported_resolutions: ['1', '3', '5', '15', '30', '60', '240', '1D', '1W', '1M'],
      volume_precision: 2,
      data_status: 'streaming'
    },
    'CIRX/USDC': {
      name: 'CIRX/USDC',
      full_name: 'Circular Protocol/USD Coin',
      description: 'CIRX to USDC',
      type: 'crypto',
      session: '24x7',
      timezone: 'Etc/UTC',
      ticker: 'CIRX/USDC',
      exchange: 'Circular DEX',
      minmov: 1,
      pricescale: 10000, // 4 decimal places
      has_intraday: true,
      has_no_volume: false,
      has_weekly_and_monthly: true,
      supported_resolutions: ['1', '3', '5', '15', '30', '60', '240', '1D', '1W', '1M'],
      volume_precision: 2,
      data_status: 'streaming'
    }
  }

  /**
   * Create custom datafeed object
   */
  const createDatafeed = () => {
    return {
      // Initialize datafeed
      onReady: (callback) => {
        console.log('[CIRX Datafeed]: onReady called')
        setTimeout(() => {
          callback({
            exchanges: [
              {
                value: 'Circular DEX',
                name: 'Circular DEX',
                desc: 'Circular Protocol Decentralized Exchange'
              }
            ],
            symbols_types: [
              {
                name: 'crypto',
                value: 'crypto'
              }
            ],
            supported_resolutions: ['1', '3', '5', '15', '30', '60', '240', '1D', '1W', '1M'],
            supports_marks: false,
            supports_timescale_marks: false,
            supports_time: true
          })
        }, 0)
      },

      // Search for symbols
      searchSymbols: (userInput, exchange, symbolType, onResultReadyCallback) => {
        console.log('[CIRX Datafeed]: searchSymbols called', { userInput, exchange, symbolType })
        
        const results = []
        const query = userInput.toUpperCase()

        // Search through supported symbols
        Object.values(SUPPORTED_SYMBOLS).forEach(symbolInfo => {
          if (symbolInfo.name.includes(query) || 
              symbolInfo.full_name.toUpperCase().includes(query) ||
              symbolInfo.description.toUpperCase().includes(query)) {
            results.push({
              symbol: symbolInfo.name,
              full_name: symbolInfo.full_name,
              description: symbolInfo.description,
              exchange: symbolInfo.exchange,
              ticker: symbolInfo.ticker,
              type: symbolInfo.type
            })
          }
        })

        setTimeout(() => onResultReadyCallback(results), 0)
      },

      // Resolve symbol information
      resolveSymbol: (symbolName, onSymbolResolvedCallback, onResolveErrorCallback) => {
        console.log('[CIRX Datafeed]: resolveSymbol called', symbolName)
        
        const symbolInfo = SUPPORTED_SYMBOLS[symbolName]
        
        if (!symbolInfo) {
          console.error('[CIRX Datafeed]: Symbol not found:', symbolName)
          setTimeout(() => onResolveErrorCallback('Symbol not found'), 0)
          return
        }

        setTimeout(() => {
          onSymbolResolvedCallback({
            name: symbolInfo.name,
            full_name: symbolInfo.full_name,
            description: symbolInfo.description,
            type: symbolInfo.type,
            session: symbolInfo.session,
            timezone: symbolInfo.timezone,
            ticker: symbolInfo.ticker,
            exchange: symbolInfo.exchange,
            minmov: symbolInfo.minmov,
            pricescale: symbolInfo.pricescale,
            has_intraday: symbolInfo.has_intraday,
            has_no_volume: symbolInfo.has_no_volume,
            has_weekly_and_monthly: symbolInfo.has_weekly_and_monthly,
            supported_resolutions: symbolInfo.supported_resolutions,
            volume_precision: symbolInfo.volume_precision,
            data_status: symbolInfo.data_status,
            currency_code: symbolInfo.name.split('/')[1]
          })
        }, 0)
      },

      // Get historical bars
      getBars: (symbolInfo, resolution, periodParams, onHistoryCallback, onErrorCallback) => {
        console.log('[CIRX Datafeed]: getBars called', { 
          symbol: symbolInfo.name, 
          resolution, 
          from: new Date(periodParams.from * 1000),
          to: new Date(periodParams.to * 1000),
          countBack: periodParams.countBack 
        })

        try {
          // Generate mock historical data for demo purposes
          // In production, this would fetch real data from your API
          const bars = generateMockBars(symbolInfo, resolution, periodParams)
          
          setTimeout(() => {
            if (bars.length === 0) {
              onHistoryCallback([], { noData: true })
            } else {
              onHistoryCallback(bars, { noData: false })
            }
          }, Math.random() * 100 + 50) // Simulate network delay
        } catch (error) {
          console.error('[CIRX Datafeed]: getBars error:', error)
          setTimeout(() => onErrorCallback(error.message), 0)
        }
      },

      // Subscribe to real-time updates
      subscribeBars: (symbolInfo, resolution, onRealtimeCallback, subscriberUID, onResetCacheNeededCallback) => {
        console.log('[CIRX Datafeed]: subscribeBars called', { 
          symbol: symbolInfo.name, 
          resolution, 
          subscriberUID 
        })
        
        // Store subscription for later use
        if (!window.tradingViewSubscriptions) {
          window.tradingViewSubscriptions = new Map()
        }
        
        window.tradingViewSubscriptions.set(subscriberUID, {
          symbolInfo,
          resolution,
          callback: onRealtimeCallback,
          resetCallback: onResetCacheNeededCallback
        })

        // Start real-time simulation (in production, connect to WebSocket)
        startRealtimeSimulation(symbolInfo, resolution, onRealtimeCallback, subscriberUID)
      },

      // Unsubscribe from real-time updates
      unsubscribeBars: (subscriberUID) => {
        console.log('[CIRX Datafeed]: unsubscribeBars called', subscriberUID)
        
        if (window.tradingViewSubscriptions) {
          window.tradingViewSubscriptions.delete(subscriberUID)
        }
        
        // Stop real-time updates
        if (window.tradingViewIntervals && window.tradingViewIntervals[subscriberUID]) {
          clearInterval(window.tradingViewIntervals[subscriberUID])
          delete window.tradingViewIntervals[subscriberUID]
        }
      }
    }
  }

  /**
   * Generate mock historical bars for demonstration
   * In production, replace this with real API calls
   */
  const generateMockBars = (symbolInfo, resolution, periodParams) => {
    const bars = []
    const { from, to, countBack } = periodParams
    
    // Calculate time interval in milliseconds
    const intervalMs = getIntervalInMs(resolution)
    const barsCount = countBack || Math.floor((to - from) / (intervalMs / 1000))
    
    // Starting price (mock)
    let basePrice = getBasePriceForSymbol(symbolInfo.name)
    
    // Generate bars going backwards from 'to' time
    for (let i = barsCount - 1; i >= 0; i--) {
      const time = (to * 1000) - (i * intervalMs)
      
      // Generate realistic OHLC data with some volatility
      const volatility = 0.02 // 2% volatility
      const change = (Math.random() - 0.5) * volatility
      
      const open = basePrice
      const close = open * (1 + change)
      const high = Math.max(open, close) * (1 + Math.random() * volatility * 0.5)
      const low = Math.min(open, close) * (1 - Math.random() * volatility * 0.5)
      const volume = Math.random() * 10000 + 1000 // Random volume
      
      bars.push({
        time: Math.floor(time / 1000) * 1000, // Ensure timestamp is in seconds
        open: Number(open.toFixed(8)),
        high: Number(high.toFixed(8)),
        low: Number(low.toFixed(8)),
        close: Number(close.toFixed(8)),
        volume: Math.floor(volume)
      })
      
      basePrice = close // Use previous close as next open
    }
    
    return bars.sort((a, b) => a.time - b.time)
  }

  /**
   * Start real-time price simulation
   */
  const startRealtimeSimulation = (symbolInfo, resolution, callback, subscriberUID) => {
    if (!window.tradingViewIntervals) {
      window.tradingViewIntervals = {}
    }

    // Update every 5 seconds (adjust based on resolution)
    const updateInterval = resolution === '1' ? 1000 : 5000
    
    window.tradingViewIntervals[subscriberUID] = setInterval(() => {
      // Generate new bar data
      const now = Math.floor(Date.now() / 1000)
      const basePrice = getBasePriceForSymbol(symbolInfo.name)
      const change = (Math.random() - 0.5) * 0.01 // 1% max change
      
      const newPrice = basePrice * (1 + change)
      const volume = Math.random() * 1000 + 100
      
      const bar = {
        time: now,
        open: newPrice,
        high: newPrice * 1.001,
        low: newPrice * 0.999,
        close: newPrice,
        volume: Math.floor(volume)
      }
      
      callback(bar)
    }, updateInterval)
  }

  /**
   * Helper functions
   */
  const getIntervalInMs = (resolution) => {
    const intervals = {
      '1': 60 * 1000,
      '3': 3 * 60 * 1000,
      '5': 5 * 60 * 1000,
      '15': 15 * 60 * 1000,
      '30': 30 * 60 * 1000,
      '60': 60 * 60 * 1000,
      '240': 4 * 60 * 60 * 1000,
      '1D': 24 * 60 * 60 * 1000,
      '1W': 7 * 24 * 60 * 60 * 1000,
      '1M': 30 * 24 * 60 * 60 * 1000
    }
    return intervals[resolution] || 60 * 1000
  }

  const getBasePriceForSymbol = (symbol) => {
    const basePrices = {
      'CIRX/USD': 1.25,
      'CIRX/ETH': 0.0003,
      'CIRX/USDC': 1.24
    }
    return basePrices[symbol] || 1.0
  }

  return {
    createDatafeed,
    SUPPORTED_SYMBOLS
  }
}

/**
 * Create and return the datafeed for direct use
 */
export const createCIRXDatafeed = () => {
  const { createDatafeed } = useTradingViewDatafeed()
  return createDatafeed()
}