<template>
  <div class="bg-circular-bg-primary/60 backdrop-blur-sm border border-gray-700/50 hover:border-gray-600 rounded-2xl transition-all duration-300 h-full flex flex-col overflow-hidden">
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
            <option value="CIRX/USD">CIRX/USD</option>
            <option value="CIRX/ETH">CIRX/ETH</option>
            <option value="CIRX/USDC">CIRX/USDC</option>
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

    <!-- TradingView Chart -->
    <div class="flex-1 px-6 pb-6">
      <TradingViewChart
        ref="chartRef"
        :symbol="selectedSymbol"
        :interval="selectedInterval"
        :height="'100%'"
        theme="dark"
        :use-custom-datafeed="true"
        :show-controls="false"
        @ready="onChartReady"
        @error="onChartError"
        @symbol-change="onSymbolChange"
        @interval-change="onIntervalChange"
      />
    </div>

    <!-- Market Stats Footer -->
    <div class="px-6 pb-4 flex-shrink-0">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-transparent border border-gray-700/30 hover:border-gray-600/50 rounded-lg p-3 transition-all duration-300">
          <div class="text-xs text-gray-400 mb-1">Market Cap</div>
          <div class="text-sm font-semibold text-white">{{ marketCap }}</div>
        </div>
        <div class="bg-transparent border border-gray-700/30 hover:border-gray-600/50 rounded-lg p-3 transition-all duration-300">
          <div class="text-xs text-gray-400 mb-1">24h Volume</div>
          <div class="text-sm font-semibold text-white">{{ volume24h }}</div>
        </div>
        <div class="bg-transparent border border-gray-700/30 hover:border-gray-600/50 rounded-lg p-3 transition-all duration-300">
          <div class="text-xs text-gray-400 mb-1">Circulating Supply</div>
          <div class="text-sm font-semibold text-white">{{ circulatingSupply }}</div>
        </div>
        <div class="bg-transparent border border-gray-700/30 hover:border-gray-600/50 rounded-lg p-3 transition-all duration-300">
          <div class="text-xs text-gray-400 mb-1">Total Supply</div>
          <div class="text-sm font-semibold text-white">{{ totalSupply }}</div>
        </div>
      </div>
      
      <!-- External Links -->
      <div class="flex gap-2 mt-3">
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
import { ref, computed, watch } from 'vue'

// Props and emits
defineEmits(['close'])

// Chart state
const chartRef = ref(null)
const selectedSymbol = ref('CIRX/USD')
const selectedInterval = ref('1D')

// Display computed
const displaySymbol = computed(() => selectedSymbol.value.replace('/', ' / '))

// Market data (these would come from your API in production)
const marketCap = ref('$7.11M')
const volume24h = ref('$1.4M')
const circulatingSupply = ref('1.52B CIRX')
const totalSupply = ref('1T CIRX')

// Chart event handlers
const onChartReady = (chart) => {
  console.log('✅ CIRX Chart ready:', chart)
}

const onChartError = (error) => {
  console.error('❌ CIRX Chart error:', error)
}

const onSymbolChange = (symbolInfo) => {
  console.log('📈 Symbol changed:', symbolInfo)
  // You could update market data based on symbol change
}

const onIntervalChange = (interval) => {
  console.log('⏰ Interval changed:', interval)
}

// Watch for prop changes and update chart
watch([selectedSymbol, selectedInterval], ([newSymbol, newInterval]) => {
  console.log('🔄 Updating chart:', { symbol: newSymbol, interval: newInterval })
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

// Expose methods for parent component if needed
defineExpose({
  refreshChart: () => chartRef.value?.refresh(),
  refreshMarketData
})
</script>

<style scoped>
/* Ensure chart has proper styling */
:deep(.tradingview-chart-wrapper) {
  height: 100%;
  min-height: 400px;
}
</style>