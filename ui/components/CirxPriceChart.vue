<template>
  <div class="relative bg-gradient-to-br from-slate-900/60 via-slate-800/40 to-slate-900/60 backdrop-blur-xl gradient-border rounded-2xl transition-all duration-500 h-full flex flex-col overflow-hidden shadow-2xl shadow-cyan-500/5 hover:shadow-cyan-500/10">
    <!-- Chart Header -->
    <div class="flex items-center justify-between p-6 pb-4 flex-shrink-0 border-b border-cyan-500/10">
      <div class="flex items-center gap-4">
        <div class="relative w-10 h-10 bg-gradient-to-br from-cyan-500/20 to-blue-600/20 rounded-xl flex items-center justify-center border border-cyan-500/30 shadow-lg shadow-cyan-500/10">
          <span class="text-cyan-400 font-bold text-lg">C</span>
          <div class="absolute inset-0 bg-gradient-to-br from-cyan-400/10 to-transparent rounded-xl"></div>
        </div>
        <div>
          <h3 class="text-xl font-bold bg-gradient-to-r from-white via-cyan-100 to-cyan-200 bg-clip-text text-transparent">
            {{ displaySymbol }}
          </h3>
          <p class="text-sm text-gray-400 font-medium">Circular Protocol</p>
        </div>
        <!-- Chart Info -->
        <div class="flex items-center gap-2 ml-4">
          <div class="text-xs text-gray-500 bg-slate-800/40 px-2 py-1 rounded-md border border-slate-600/30">
            Reference Chart + Live CIRX Data
          </div>
        </div>
        <div class="flex gap-3 ml-6">
          <select 
            v-model="selectedSymbol" 
            class="bg-gradient-to-br from-slate-800/80 to-slate-900/80 border border-cyan-500/30 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:border-cyan-400 transition-all duration-200 backdrop-blur-sm hover:border-cyan-400/50"
          >
            <option value="USDT/CIRX" class="bg-slate-900">USDT/CIRX</option>
            <option value="USDC/CIRX" class="bg-slate-900">USDC/CIRX</option>
            <option value="ETH/CIRX" class="bg-slate-900">ETH/CIRX</option>
          </select>
          <select 
            v-model="selectedInterval" 
            class="bg-gradient-to-br from-slate-800/80 to-slate-900/80 border border-cyan-500/30 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:border-cyan-400 transition-all duration-200 backdrop-blur-sm hover:border-cyan-400/50"
          >
            <option value="1" class="bg-slate-900">1m</option>
            <option value="5" class="bg-slate-900">5m</option>
            <option value="15" class="bg-slate-900">15m</option>
            <option value="60" class="bg-slate-900">1h</option>
            <option value="240" class="bg-slate-900">4h</option>
            <option value="1D" class="bg-slate-900">1D</option>
          </select>
        </div>
      </div>
      <button
        @click="$emit('close')"
        class="group relative text-gray-400 hover:text-white transition-all duration-200 p-3 flex-shrink-0 rounded-xl bg-slate-800/40 hover:bg-slate-700/60 border border-slate-600/30 hover:border-slate-500/50 backdrop-blur-sm"
        title="Close chart"
      >
        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" class="transition-transform group-hover:scale-110">
          <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
        </svg>
      </button>
    </div>

    <!-- TradingView Chart -->
    <div class="flex-1 px-6 pb-2 min-h-0">
      <div class="chart-container w-full h-full relative rounded-lg overflow-hidden">
        <TVChart 
          :options="chartOptions"
          :key="chartKey"
          class="w-full h-full"
        />
        
        <!-- CIRX Price Overlay -->
        <div
          v-if="!priceLoading && !priceError && currentPairPrice"
          class="absolute top-4 right-4 z-20 bg-gradient-to-br from-cyan-600/20 to-blue-600/20 backdrop-blur-md border border-cyan-500/30 rounded-xl px-4 py-3 shadow-lg shadow-cyan-500/20"
        >
          <div class="text-xs text-cyan-300 font-medium mb-1">{{ selectedSymbol }} Rate</div>
          <div class="text-lg font-bold text-white">
            {{ currentPairPrice }}
          </div>
          <div class="text-xs text-gray-400 mt-1">
            CIRX: ${{ formattedPrice }}
          </div>
        </div>

        <!-- Loading state for CIRX data -->
        <div
          v-if="priceLoading"
          class="absolute top-4 right-4 z-20 bg-slate-900/60 backdrop-blur-sm border border-slate-600/30 rounded-xl px-4 py-3"
        >
          <div class="text-xs text-gray-400 mb-2">Loading CIRX Price...</div>
          <div class="animate-pulse h-4 w-16 bg-gray-600 rounded"></div>
        </div>

        <!-- Error state for CIRX data -->
        <div
          v-if="priceError && !priceLoading"
          class="absolute top-4 right-4 z-20 bg-red-900/60 backdrop-blur-sm border border-red-600/30 rounded-xl px-4 py-3"
        >
          <div class="text-xs text-red-300 font-medium">Price Error</div>
          <div class="text-xs text-gray-400 mt-1">{{ priceError }}</div>
        </div>
      </div>
    </div>

    <!-- Market Stats Footer -->
    <div class="px-6 pb-3 flex-shrink-0">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
        <div class="group relative bg-gradient-to-br from-slate-800/40 via-slate-800/20 to-slate-900/40 border border-cyan-500/20 hover:border-cyan-400/40 rounded-lg p-3 transition-all duration-300 hover:shadow-lg hover:shadow-cyan-500/10 backdrop-blur-sm">
          <div class="text-xs text-gray-400 mb-1 group-hover:text-gray-300 transition-colors">Market Cap</div>
          <div class="text-sm font-semibold text-white group-hover:text-cyan-300 transition-colors">{{ marketCap }}</div>
          <div class="absolute inset-0 bg-gradient-to-r from-cyan-500/5 to-blue-500/5 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg"></div>
        </div>
        <div class="group relative bg-gradient-to-br from-slate-800/40 via-slate-800/20 to-slate-900/40 border border-cyan-500/20 hover:border-cyan-400/40 rounded-lg p-3 transition-all duration-300 hover:shadow-lg hover:shadow-cyan-500/10 backdrop-blur-sm">
          <div class="text-xs text-gray-400 mb-1 group-hover:text-gray-300 transition-colors">24h Volume</div>
          <div class="text-sm font-semibold text-white group-hover:text-cyan-300 transition-colors">{{ volume24h }}</div>
          <div class="absolute inset-0 bg-gradient-to-r from-cyan-500/5 to-blue-500/5 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg"></div>
        </div>
        <div class="group relative bg-gradient-to-br from-slate-800/40 via-slate-800/20 to-slate-900/40 border border-cyan-500/20 hover:border-cyan-400/40 rounded-lg p-3 transition-all duration-300 hover:shadow-lg hover:shadow-cyan-500/10 backdrop-blur-sm">
          <div class="text-xs text-gray-400 mb-1 group-hover:text-gray-300 transition-colors">Circulating Supply</div>
          <div class="text-sm font-semibold text-white group-hover:text-cyan-300 transition-colors">{{ circulatingSupply }}</div>
          <div class="absolute inset-0 bg-gradient-to-r from-cyan-500/5 to-blue-500/5 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg"></div>
        </div>
        <div class="group relative bg-gradient-to-br from-slate-800/40 via-slate-800/20 to-slate-900/40 border border-cyan-500/20 hover:border-cyan-400/40 rounded-lg p-3 transition-all duration-300 hover:shadow-lg hover:shadow-cyan-500/10 backdrop-blur-sm">
          <div class="text-xs text-gray-400 mb-1 group-hover:text-gray-300 transition-colors">Total Supply</div>
          <div class="text-sm font-semibold text-white group-hover:text-cyan-300 transition-colors">{{ totalSupply }}</div>
          <div class="absolute inset-0 bg-gradient-to-r from-cyan-500/5 to-blue-500/5 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg"></div>
        </div>
      </div>
      
      <!-- External Links -->
      <div class="flex gap-2 mt-3">
        <a
          href="https://coinmarketcap.com/currencies/circular-protocol/"
          target="_blank"
          rel="noopener noreferrer"
          class="group flex-1 px-3 py-2 bg-gradient-to-r from-slate-800/60 to-slate-700/60 border border-slate-600/40 hover:border-slate-500/60 text-white rounded-lg text-center text-xs font-medium transition-all duration-300 hover:shadow-md hover:shadow-slate-500/20 backdrop-blur-sm"
        >
          <span class="group-hover:text-gray-200">View on CMC</span>
        </a>
        <a
          href="https://circularlabs.io"
          target="_blank"
          rel="noopener noreferrer"
          class="group flex-1 px-3 py-2 bg-gradient-to-r from-cyan-600/30 to-blue-600/30 border border-cyan-500/40 hover:border-cyan-400/60 text-cyan-300 rounded-lg text-center text-xs font-medium transition-all duration-300 hover:shadow-md hover:shadow-cyan-500/30 backdrop-blur-sm hover:bg-gradient-to-r hover:from-cyan-600/40 hover:to-blue-600/40"
        >
          <span class="group-hover:text-cyan-200">Learn More</span>
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

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

// Props and emits
defineEmits(['close'])

// Chart state - Use CIRX-related pairs
const selectedSymbol = ref('USDT/CIRX')
const selectedInterval = ref('1D')

// Map CIRX pairs to closest TradingView symbols for display
const tradingViewSymbolMap = {
  'USDT/CIRX': 'COINBASE:ETHUSD', // Show ETH as reference since CIRX not available
  'USDC/CIRX': 'COINBASE:USDCUSD', // Show USDC reference
  'ETH/CIRX': 'COINBASE:ETHUSD' // Show ETH reference
}

// Display computed - shows the actual CIRX pair name
const displaySymbol = computed(() => selectedSymbol.value.replace('/', ' / '))

// Chart key for reactivity
const chartKey = computed(() => `${selectedSymbol.value}-${selectedInterval.value}`)

// Get the reference TradingView symbol for the selected CIRX pair
const tradingViewSymbol = computed(() => tradingViewSymbolMap[selectedSymbol.value] || 'COINBASE:ETHUSD')

// TradingView Chart Options - use reference symbol but display CIRX pair info
const chartOptions = computed(() => ({
  symbol: tradingViewSymbol.value,
  interval: selectedInterval.value,
  theme: 'dark',
  style: '1', // Candle style
  locale: 'en',
  toolbar_bg: '#0a0b1e',
  enable_publishing: false,
  backgroundColor: '#0a0b1e',
  gridColor: '#334155',
  hide_top_toolbar: false,
  hide_legend: false,
  save_image: false,
  container_id: 'tradingview_chart',
  autosize: true,
  studies: [],
  // Professional styling
  overrides: {
    'paneProperties.background': '#0a0b1e',
    'paneProperties.gridProperties.color': 'rgba(99, 102, 241, 0.12)',
    'scalesProperties.textColor': '#8b949e',
    'scalesProperties.lineColor': 'rgba(99, 102, 241, 0.3)',
    // Candle colors
    'mainSeriesProperties.candleStyle.upColor': '#10b981',
    'mainSeriesProperties.candleStyle.downColor': '#ef4444',
    'mainSeriesProperties.candleStyle.drawWick': true,
    'mainSeriesProperties.candleStyle.drawBorder': true,
    'mainSeriesProperties.candleStyle.borderColor': '#374151',
    'mainSeriesProperties.candleStyle.borderUpColor': '#10b981',
    'mainSeriesProperties.candleStyle.borderDownColor': '#ef4444',
    'mainSeriesProperties.candleStyle.wickUpColor': '#10b981',
    'mainSeriesProperties.candleStyle.wickDownColor': '#ef4444'
  }
}))

// Current CIRX pair price for display
const currentPairPrice = computed(() => {
  if (!liveCirxPrice.value || liveCirxPrice.value <= 0) return null
  
  // Convert current CIRX USD price to trading pair rate
  let pairValue
  switch(selectedSymbol.value) {
    case 'USDT/CIRX':
    case 'USDC/CIRX':
      // How many CIRX for 1 USDT/USDC
      pairValue = 1.0 / liveCirxPrice.value
      break
    case 'ETH/CIRX':
      // How many CIRX for 1 ETH (assume ETH = $2700)
      pairValue = 2700.0 / liveCirxPrice.value
      break
    default:
      pairValue = 1.0 / liveCirxPrice.value
  }
  
  // Format based on price magnitude for better readability
  if (pairValue >= 1000) {
    return pairValue.toFixed(0) // Large numbers like ETH/CIRX
  } else if (pairValue >= 1) {
    return pairValue.toFixed(2) // Numbers like USDT/CIRX
  } else {
    return pairValue.toFixed(6) // Small decimal numbers
  }
})

// Market data (these would come from your API in production)
const marketCap = ref('$7.11M')
const volume24h = ref('$1.4M')
const circulatingSupply = ref('1.52B CIRX')
const totalSupply = ref('1T CIRX')

// Watch for changes to update chart
watch([selectedSymbol, selectedInterval], () => {
  console.log('Chart updated:', { 
    symbol: selectedSymbol.value, 
    interval: selectedInterval.value 
  })
})
</script>

<style scoped>
.chart-container {
  background: linear-gradient(135deg, #0a0b1e 0%, #050611 100%);
  border: 1px solid rgba(99, 102, 241, 0.2);
  box-shadow: 
    0 0 20px rgba(6, 182, 212, 0.1),
    0 8px 32px rgba(0, 0, 0, 0.3),
    inset 0 1px 0 rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(8px);
  position: relative;
}

.chart-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: radial-gradient(circle at 30% 20%, rgba(99, 102, 241, 0.08) 0%, transparent 50%);
  pointer-events: none;
  border-radius: inherit;
}

/* Sophisticated glow effects */
.chart-container:hover {
  box-shadow: 
    0 0 30px rgba(6, 182, 212, 0.15),
    0 12px 48px rgba(0, 0, 0, 0.4),
    inset 0 1px 0 rgba(255, 255, 255, 0.08);
  transition: box-shadow 0.3s ease;
}

/* Hide TradingView branding if needed */
:deep(.tradingview-widget-copyright) {
  display: none !important;
}

/* Customize TradingView container */
:deep(.tradingview-widget-container) {
  background: transparent !important;
  border-radius: 0.5rem;
}

/* Gradient border matching swap form - normal state */
.gradient-border {
  position: relative;
  border: 1px solid rgba(6, 182, 212, 0.3); /* cyan-500/30 */
  border-radius: 1rem;
  transition: all 0.3s ease;
}

/* Animated gradient border on hover */
.gradient-border:hover {
  border: 1px solid #ef4444;
  animation: border-color-cycle 75s ease infinite;
}

@keyframes border-color-cycle {
  0% { border-color: #00ff88; }
  25% { border-color: #00d9ff; }
  50% { border-color: #8b5cf6; }
  75% { border-color: #a855f7; }
  100% { border-color: #00ff88; }
}
</style>