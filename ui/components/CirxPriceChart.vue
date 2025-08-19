<template>
  <div class="bg-circular-bg-primary/80 backdrop-blur-sm border border-gray-700/50 hover:border-gray-600 rounded-2xl transition-all duration-300 h-full flex flex-col overflow-hidden">
    <!-- Chart Header -->
    <div class="flex items-center justify-between p-6 pb-4 flex-shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-circular-primary/20 rounded-lg flex items-center justify-center">
          <span class="text-circular-primary font-bold text-sm">C</span>
        </div>
        <div>
          <h3 class="text-lg font-semibold text-white">{{ displaySymbol }}</h3>
          <p class="text-sm text-gray-400">Circular Protocol</p>
        </div>
        <div class="flex gap-2 ml-4">
          <select 
            v-model="selectedSymbol" 
            class="bg-gray-800 border border-gray-600 rounded-lg px-3 py-1 text-sm text-white focus:outline-none focus:ring-2 focus:ring-circular-primary"
          >
            <option value="USDT/CIRX">USDT/CIRX</option>
            <option value="ETH/CIRX">ETH/CIRX</option>
            <option value="USDC/CIRX">USDC/CIRX</option>
          </select>
          <select 
            v-model="selectedInterval" 
            class="bg-gray-800 border border-gray-600 rounded-lg px-3 py-1 text-sm text-white focus:outline-none focus:ring-2 focus:ring-circular-primary"
          >
            <option value="1">1m</option>
            <option value="5">5m</option>
            <option value="15">15m</option>
            <option value="60">1h</option>
            <option value="240">4h</option>
            <option value="1D">1D</option>
          </select>
        </div>
      </div>
      <button
        @click="$emit('close')"
        class="text-gray-400 hover:text-white transition-colors p-2 flex-shrink-0"
        title="Close chart"
      >
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
          <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
        </svg>
      </button>
    </div>

    <!-- Lightweight Chart -->
    <div class="flex-1 px-6 pb-2 min-h-0">
      <div
        ref="chartContainer"
        class="chart-container w-full h-full relative rounded-lg overflow-hidden"
      >
        <!-- Loading state -->
        <div
          v-if="isLoading"
          class="absolute inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 z-10"
        >
          <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-circular-primary mx-auto mb-4"></div>
            <p class="text-white text-sm">Loading Chart...</p>
          </div>
        </div>

        <!-- Error state -->
        <div
          v-if="error && !isLoading"
          class="absolute inset-0 flex items-center justify-center bg-gray-900 text-center z-10 rounded-lg"
        >
          <div class="text-red-400">
            <svg class="w-16 h-16 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm font-medium">Chart Loading Failed</p>
            <p class="text-xs text-gray-400 mt-2">{{ error }}</p>
            <button
              @click="initChart"
              class="mt-4 px-4 py-2 bg-circular-primary text-gray-900 rounded-lg text-sm hover:bg-circular-primary/80 transition-colors"
            >
              Retry
            </button>
          </div>
        </div>

        <!-- Current price display -->
        <div
          v-if="!isLoading && !error && displayPrice"
          class="absolute top-4 right-12 z-20 bg-gray-800/90 rounded-full w-20 h-20 flex items-center justify-center"
        >
          <div class="text-lg font-mono font-bold text-center" style="color: #9333ea;">
            {{ displayPrice }}
          </div>
        </div>
      </div>
    </div>

    <!-- Market Stats Footer -->
    <div class="px-6 pb-3 flex-shrink-0">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
        <div class="bg-transparent border border-gray-700/30 hover:border-gray-600/50 rounded-lg p-2 transition-all duration-300">
          <div class="text-xs text-gray-400 mb-1">Market Cap</div>
          <div class="text-sm font-semibold text-white">{{ marketCap }}</div>
        </div>
        <div class="bg-transparent border border-gray-700/30 hover:border-gray-600/50 rounded-lg p-2 transition-all duration-300">
          <div class="text-xs text-gray-400 mb-1">24h Volume</div>
          <div class="text-sm font-semibold text-white">{{ volume24h }}</div>
        </div>
        <div class="bg-transparent border border-gray-700/30 hover:border-gray-600/50 rounded-lg p-2 transition-all duration-300">
          <div class="text-xs text-gray-400 mb-1">Circulating Supply</div>
          <div class="text-sm font-semibold text-white">{{ circulatingSupply }}</div>
        </div>
        <div class="bg-transparent border border-gray-700/30 hover:border-gray-600/50 rounded-lg p-2 transition-all duration-300">
          <div class="text-xs text-gray-400 mb-1">Total Supply</div>
          <div class="text-sm font-semibold text-white">{{ totalSupply }}</div>
        </div>
      </div>
      
      <!-- External Links -->
      <div class="flex gap-2 mt-2">
        <a
          href="https://coinmarketcap.com/currencies/circular-protocol/"
          target="_blank"
          rel="noopener noreferrer"
          class="flex-1 px-3 py-1.5 bg-transparent border border-gray-600/50 hover:border-gray-500 text-white rounded-lg text-center text-xs font-medium transition-all duration-300"
        >
          View on CMC
        </a>
        <a
          href="https://circularlabs.io"
          target="_blank"
          rel="noopener noreferrer"
          class="flex-1 px-3 py-1.5 bg-circular-primary/20 border border-circular-primary/30 hover:bg-circular-primary/30 text-circular-primary rounded-lg text-center text-xs font-medium transition-all duration-300"
        >
          Learn More
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'

// Props and emits
defineEmits(['close'])

// Real price data integration
const { 
  currentPrice: liveCirxPrice, 
  priceHistory, 
  isLoading: priceLoading, 
  error: priceError,
  tradingPairs,
  priceForSymbol,
  formattedPrice
} = usePriceData()

// Chart state
const chartContainer = ref(null)
const chart = ref(null)
const series = ref(null)
const selectedSymbol = ref('USDT/CIRX')
const selectedInterval = ref('1D')
const isLoading = ref(true)
const error = ref(null)
const priceUpdateInterval = ref(null)

// Display computed
const displaySymbol = computed(() => selectedSymbol.value.replace('/', ' / '))

// Current price for display (uses real trading pair data)
const displayPrice = computed(() => {
  const symbolPrice = priceForSymbol.value(selectedSymbol.value)
  if (symbolPrice && symbolPrice > 0) {
    // Format based on price magnitude for better readability
    if (symbolPrice >= 1000) {
      return symbolPrice.toFixed(0) // Large numbers like ETH/CIRX
    } else if (symbolPrice >= 1) {
      return symbolPrice.toFixed(2) // Numbers like USDT/CIRX
    } else {
      return symbolPrice.toFixed(6) // Small decimal numbers
    }
  }
  return null
})

// Market data (these would come from your API in production)
const marketCap = ref('$7.11M')
const volume24h = ref('$1.4M')
const circulatingSupply = ref('1.52B CIRX')
const totalSupply = ref('1T CIRX')

// Chart initialization
const initChart = async () => {
  if (!chartContainer.value) {
    console.error('Chart container not available')
    return
  }

  try {
    isLoading.value = true
    error.value = null

    // Import lightweight charts dynamically (v5 API)
    const { createChart, LineStyleType, LineSeries } = await import('lightweight-charts')

    // Clear any existing chart
    if (chart.value) {
      try {
        chart.value.remove()
      } catch (e) {
        console.warn('Error removing existing chart:', e)
      }
      chart.value = null
      series.value = null
    }

    // Wait for next tick to ensure DOM is ready
    await nextTick()

    // Calculate available height dynamically
    const containerHeight = chartContainer.value.clientHeight
    const chartHeight = Math.max(containerHeight, 300) // Minimum 300px
    
    // Create chart
    chart.value = createChart(chartContainer.value, {
      width: chartContainer.value.clientWidth,
      height: chartHeight,
      layout: {
        background: {
          type: 'solid',
          color: '#000406'
        },
        textColor: '#d1d5db'
      },
      grid: {
        vertLines: {
          color: '#374151',
          style: 1
        },
        horzLines: {
          color: '#374151',
          style: 1
        }
      },
      crosshair: {
        mode: 0 // Normal crosshair
      },
      rightPriceScale: {
        borderColor: '#374151',
        scaleMargins: {
          top: 0.02,    // Minimal top margin (2%)
          bottom: 0.02  // Minimal bottom margin (2%)
        }
      },
      timeScale: {
        borderColor: '#374151',
        timeVisible: true,
        secondsVisible: false
      },
      handleScroll: {
        mouseWheel: true,
        pressedMouseMove: true
      },
      handleScale: {
        axisPressedMouseMove: true,
        mouseWheel: true,
        pinch: true
      }
    })

    // Add line series using v5 API
    series.value = chart.value.addSeries(LineSeries, {
      color: '#10b981',
      lineWidth: 2,
      crosshairMarkerVisible: true,
      priceLineVisible: true,
      lastValueVisible: true
    })

    // Use real historical data if available, otherwise generate mock data
    let chartData
    if (priceHistory.value && priceHistory.value.length > 0) {
      // Transform real price data for the selected symbol
      const currentSymbolPrice = priceForSymbol.value(selectedSymbol.value)
      
      chartData = priceHistory.value.map(point => ({
        time: point.time,
        value: currentSymbolPrice || point.value
      }))
      
      console.log('✅ Using real price data:', { 
        symbol: selectedSymbol.value,
        points: chartData.length,
        latestPrice: chartData[chartData.length - 1]?.value
      })
    } else {
      // Fallback to generated data while real data loads
      chartData = generateFallbackData(200)
      console.log('⏳ Using fallback data while real price data loads')
    }
    
    series.value.setData(chartData)

    // Auto-resize chart
    const resizeObserver = new ResizeObserver((entries) => {
      if (chart.value && entries.length > 0) {
        const { width, height } = entries[0].contentRect
        const newHeight = Math.max(height, 300) // Minimum 300px
        chart.value.applyOptions({ width, height: newHeight })
      }
    })
    
    // Fit chart content to fill vertical space better
    chart.value.timeScale().fitContent()
    resizeObserver.observe(chartContainer.value)

    // Start real-time updates only if we have real data
    if (priceHistory.value && priceHistory.value.length > 0) {
      startRealtimeUpdates()
    }

    console.log('✅ Lightweight Charts initialized successfully')
    isLoading.value = false

  } catch (err) {
    console.error('❌ Chart initialization failed:', err)
    error.value = err.message || 'Failed to initialize chart'
    isLoading.value = false
  }
}

// Generate fallback data when real data isn't available
const generateFallbackData = (count) => {
  const data = []
  let basePrice = getBasePriceForSymbol(selectedSymbol.value)
  const now = new Date()
  
  // Generate data for the last `count` minutes
  for (let i = count - 1; i >= 0; i--) {
    const time = Math.floor((now.getTime() - i * 60 * 1000) / 1000)
    
    // Generate realistic price data with some volatility
    const volatility = 0.02 // 2% volatility
    const change = (Math.random() - 0.5) * volatility
    
    const price = basePrice * (1 + change)
    
    data.push({
      time,
      value: Number(price.toFixed(8))
    })
    
    basePrice = price // Use previous price as base for next
  }
  
  return data
}

// Start real-time price updates using live data
const startRealtimeUpdates = () => {
  if (priceUpdateInterval.value) {
    clearInterval(priceUpdateInterval.value)
  }

  priceUpdateInterval.value = setInterval(() => {
    if (!series.value) return

    // Use real price data if available
    if (priceHistory.value && priceHistory.value.length > 0) {
      const latestPoint = priceHistory.value[priceHistory.value.length - 1]
      const currentSymbolPrice = priceForSymbol.value(selectedSymbol.value)
      
      if (latestPoint && currentSymbolPrice) {
        const newPoint = {
          time: latestPoint.time,
          value: Number(currentSymbolPrice.toFixed(8))
        }
        
        // Update series with real price data
        series.value.update(newPoint)
        
        console.log('📈 Real-time price update:', {
          symbol: selectedSymbol.value,
          price: currentSymbolPrice,
          time: new Date(latestPoint.time * 1000).toLocaleTimeString()
        })
      }
    }
    
  }, 30000) // Update every 30 seconds (matching price service interval)
}

// Helper functions
const getBasePriceForSymbol = (symbol) => {
  const basePrices = {
    'USDT/CIRX': 225.12, // 1 USDT = ~225 CIRX (CIRX ~$0.004443)
    'ETH/CIRX': 607470, // 1 ETH = ~607K CIRX (ETH ~$2700, CIRX ~$0.004443)
    'USDC/CIRX': 225.0 // 1 USDC = ~225 CIRX (CIRX ~$0.004443)
  }
  return basePrices[symbol] || 1.0
}

// Watch for real price data becoming available
watch([priceHistory, tradingPairs], ([newHistory, newPairs]) => {
  if (newHistory && newHistory.length > 0 && newPairs && Object.keys(newPairs).length > 0) {
    console.log('🔄 Real price data available, updating chart')
    // Reinitialize chart with real data
    initChart()
  }
}, { immediate: false })

// Watch for prop changes and update chart
watch([selectedSymbol, selectedInterval], ([newSymbol, newInterval]) => {
  console.log('🔄 Updating chart:', { symbol: newSymbol, interval: newInterval })
  // Reinitialize chart with new symbol/interval
  initChart()
})

// Watch for price errors and show them in chart
watch(priceError, (newError) => {
  if (newError) {
    error.value = `Price feed error: ${newError}`
  }
})

// You could add methods here to refresh market data
const refreshMarketData = async () => {
  // In production, fetch real market data from your API
  try {
    // Example API call structure:
    // const response = await fetch(`/api/market-data/${selectedSymbol.value}`)
    // const data = await response.json()
    // marketCap.value = data.marketCap
    // volume24h.value = data.volume24h
    // etc.
  } catch (error) {
    console.error('Failed to refresh market data:', error)
  }
}

// Lifecycle
onMounted(() => {
  nextTick(() => {
    initChart()
  })
})

onBeforeUnmount(() => {
  if (priceUpdateInterval.value) {
    clearInterval(priceUpdateInterval.value)
  }
  
  if (chart.value) {
    try {
      chart.value.remove()
    } catch (e) {
      console.warn('Error removing chart:', e)
    }
    chart.value = null
    series.value = null
  }
})

// Expose methods for parent component if needed
defineExpose({
  refreshChart: initChart,
  refreshMarketData
})
</script>

<style scoped>
/* Ensure chart has proper styling */
.chart-container {
  background: #1f2937;
  border: 1px solid #374151;
}
</style>