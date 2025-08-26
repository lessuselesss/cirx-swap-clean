<template>
  <div class="relative gradient-border rounded-2xl transition-all duration-500 h-full flex flex-col overflow-hidden shadow-2xl shadow-cyan-500/5 hover:shadow-cyan-500/10" style="background-color: rgba(0, 3, 6, 0.9);">
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
            Real CIRX Market Data
          </div>
        </div>
        <div class="flex gap-3 ml-6">
          <select 
            v-model="selectedSymbol" 
            class="bg-gradient-to-br from-slate-800/80 to-slate-900/80 border border-cyan-500/30 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:border-cyan-400 transition-all duration-200 backdrop-blur-sm hover:border-cyan-400/50"
          >
            <option value="CIRX/USDT" class="bg-slate-900">CIRX/USDT</option>
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
        <Chart 
          :options="chartOptions"
          :key="chartKey"
          :style="{ height: '100%', width: '100%' }"
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

// Use aggregate price feed for multi-exchange data
const {
  currentPrice: liveCirxPrice,
  isLoading: priceLoading,
  error: priceError,
  formattedPrice,
  marketStats
} = useAggregatePriceFeed()

// Props and emits
defineEmits(['close'])

// Chart state - CIRX/USDT only
const selectedSymbol = ref('CIRX/USDT')
const selectedInterval = ref('1D')

// Display computed - shows the actual CIRX pair name
const displaySymbol = computed(() => selectedSymbol.value.replace('/', ' / '))

// Chart key for reactivity
const chartKey = computed(() => `${selectedSymbol.value}-${selectedInterval.value}`)

// Import the aggregate datafeed
const { createDatafeed } = useAggregateDatafeed()

// TradingView Chart Options - using aggregate datafeed
const chartOptions = computed(() => ({
  // Symbol and interval - now using aggregated CIRX data
  symbol: selectedSymbol.value,
  interval: selectedInterval.value,
  datafeed: createDatafeed(), // Use aggregate datafeed with multi-exchange data
  
  // Appearance
  theme: 'dark',
  autosize: true,
  
  // Enable features
  studies_overrides: {},
  
  // Basic settings that work well for crypto trading
  timezone: 'Etc/UTC',
  locale: 'en',
  
  // Disable some features for cleaner look
  disabled_features: [
    'use_localstorage_for_settings',
    'volume_force_overlay'
  ],
  
  // Enable useful features  
  enabled_features: [
    'study_templates'
  ],
  
  // Custom styling for dark theme
  loading_screen: { 
    backgroundColor: '#1f2937'
  },
  
  // Toolbar styling
  toolbar_bg: '#111827'
}))

// Current CIRX/USDT price for display
const currentPairPrice = computed(() => {
  if (!liveCirxPrice.value || liveCirxPrice.value <= 0) return null
  
  // CIRX/USDT means CIRX price in USDT (which is the USD price)
  const pairValue = liveCirxPrice.value
  
  // Format based on price magnitude for better readability
  if (pairValue >= 1) {
    return pairValue.toFixed(4) // Standard crypto price format
  } else {
    return pairValue.toFixed(6) // Small decimal numbers
  }
})

// Market data from aggregate feed
const marketCap = computed(() => marketStats.value.marketCap)
const volume24h = computed(() => marketStats.value.volume24h)
const circulatingSupply = computed(() => marketStats.value.circulatingSupply)
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
  background: rgba(0, 3, 6, 0.6);
  border: 1px solid rgba(6, 182, 212, 0.2);
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