<template>
  <div class="min-h-screen bg-circular-bg-primary p-8">
    <div class="max-w-7xl mx-auto">
      <h1 class="text-3xl font-bold text-white mb-8">TradingView Chart Test</h1>
      
      <!-- Basic Chart Test -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">Basic TradingView Chart</h2>
        <div class="h-96">
          <TradingViewChart
            symbol="CIRX/USD"
            interval="1D"
            theme="dark"
            :use-custom-datafeed="true"
            :show-controls="true"
            @ready="onBasicChartReady"
            @error="onBasicChartError"
          />
        </div>
      </div>

      <!-- CirxPriceChart Integration Test -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">CIRX Price Chart Component</h2>
        <div class="h-96">
          <CirxPriceChart />
        </div>
      </div>

      <!-- Multiple Charts Test -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-gray-800 rounded-lg p-6">
          <h3 class="text-lg font-semibold text-white mb-4">CIRX/USD Chart</h3>
          <div class="h-80">
            <TradingViewChart
              symbol="CIRX/USD"
              interval="1h"
              theme="dark"
              :use-custom-datafeed="true"
              :show-controls="false"
            />
          </div>
        </div>
        
        <div class="bg-gray-800 rounded-lg p-6">
          <h3 class="text-lg font-semibold text-white mb-4">CIRX/ETH Chart</h3>
          <div class="h-80">
            <TradingViewChart
              symbol="CIRX/ETH"
              interval="4h"
              theme="dark"
              :use-custom-datafeed="true"
              :show-controls="false"
            />
          </div>
        </div>
      </div>

      <!-- Chart Controls Test -->
      <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">Chart Controls Test</h2>
        <div class="flex gap-4 mb-4">
          <div>
            <label class="block text-sm text-gray-400 mb-2">Symbol:</label>
            <select 
              v-model="testSymbol" 
              class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-circular-primary"
            >
              <option value="CIRX/USD">CIRX/USD</option>
              <option value="CIRX/ETH">CIRX/ETH</option>
              <option value="CIRX/USDC">CIRX/USDC</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-400 mb-2">Interval:</label>
            <select 
              v-model="testInterval" 
              class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-circular-primary"
            >
              <option value="1">1m</option>
              <option value="5">5m</option>
              <option value="15">15m</option>
              <option value="60">1h</option>
              <option value="240">4h</option>
              <option value="1D">1D</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-400 mb-2">Theme:</label>
            <select 
              v-model="testTheme" 
              class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-circular-primary"
            >
              <option value="dark">Dark</option>
              <option value="light">Light</option>
            </select>
          </div>
          <div class="flex items-end">
            <button
              @click="refreshChart"
              class="px-4 py-2 bg-circular-primary text-gray-900 rounded-lg font-medium hover:bg-circular-primary-hover transition-colors"
            >
              Refresh Chart
            </button>
          </div>
        </div>
        
        <div class="h-96">
          <TradingViewChart
            ref="controlledChart"
            :key="chartKey"
            :symbol="testSymbol"
            :interval="testInterval"
            :theme="testTheme"
            :use-custom-datafeed="true"
            :show-controls="true"
            @ready="onControlledChartReady"
            @error="onControlledChartError"
            @symbol-change="onSymbolChange"
            @interval-change="onIntervalChange"
          />
        </div>
      </div>

      <!-- Debug Information -->
      <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-semibold text-white mb-4">Debug Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <h3 class="font-semibold text-gray-300 mb-2">TradingView Status</h3>
            <div class="space-y-1">
              <div class="flex justify-between">
                <span class="text-gray-400">TradingView Loaded:</span>
                <span :class="isTradingViewLoaded ? 'text-green-400' : 'text-red-400'">
                  {{ isTradingViewLoaded ? 'Yes' : 'No' }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">Datafeeds Available:</span>
                <span :class="isDatafeedsAvailable ? 'text-green-400' : 'text-red-400'">
                  {{ isDatafeedsAvailable ? 'Yes' : 'No' }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">Charts Ready:</span>
                <span :class="chartsReady > 0 ? 'text-green-400' : 'text-gray-400'">
                  {{ chartsReady }}
                </span>
              </div>
            </div>
          </div>
          
          <div>
            <h3 class="font-semibold text-gray-300 mb-2">Current Settings</h3>
            <div class="space-y-1">
              <div class="flex justify-between">
                <span class="text-gray-400">Symbol:</span>
                <span class="text-white">{{ testSymbol }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">Interval:</span>
                <span class="text-white">{{ testInterval }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">Theme:</span>
                <span class="text-white">{{ testTheme }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Error Log -->
        <div v-if="errors.length > 0" class="mt-4">
          <h3 class="font-semibold text-red-400 mb-2">Errors</h3>
          <div class="bg-red-900/20 border border-red-700 rounded-lg p-3 max-h-40 overflow-y-auto">
            <div v-for="(error, index) in errors" :key="index" class="text-red-300 text-sm mb-1">
              <span class="text-red-500">[{{ error.time }}]</span> {{ error.message }}
            </div>
          </div>
        </div>

        <!-- Event Log -->
        <div v-if="events.length > 0" class="mt-4">
          <h3 class="font-semibold text-green-400 mb-2">Events</h3>
          <div class="bg-green-900/20 border border-green-700 rounded-lg p-3 max-h-40 overflow-y-auto">
            <div v-for="(event, index) in events" :key="index" class="text-green-300 text-sm mb-1">
              <span class="text-green-500">[{{ event.time }}]</span> {{ event.message }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

// Page metadata
definePageMeta({
  title: 'TradingView Chart Test',
  description: 'Test page for TradingView Charting Library integration',
  ssr: false
})

// Test controls
const testSymbol = ref('CIRX/USD')
const testInterval = ref('1D')
const testTheme = ref('dark')
const chartKey = ref(0)
const controlledChart = ref(null)

// Status tracking
const chartsReady = ref(0)
const errors = ref([])
const events = ref([])

// Computed properties
const isTradingViewLoaded = computed(() => {
  return typeof window !== 'undefined' && !!window.TradingView
})

const isDatafeedsAvailable = computed(() => {
  return typeof window !== 'undefined' && !!window.Datafeeds
})

// Event handlers
const onBasicChartReady = (chart) => {
  chartsReady.value++
  addEvent('Basic chart ready')
  console.log('✅ Basic chart ready:', chart)
}

const onBasicChartError = (error) => {
  addError(`Basic chart error: ${error.message || error}`)
  console.error('❌ Basic chart error:', error)
}

const onControlledChartReady = (chart) => {
  chartsReady.value++
  addEvent('Controlled chart ready')
  console.log('✅ Controlled chart ready:', chart)
}

const onControlledChartError = (error) => {
  addError(`Controlled chart error: ${error.message || error}`)
  console.error('❌ Controlled chart error:', error)
}

const onSymbolChange = (symbolInfo) => {
  addEvent(`Symbol changed to: ${symbolInfo.name || symbolInfo}`)
  console.log('📈 Symbol changed:', symbolInfo)
}

const onIntervalChange = (interval) => {
  addEvent(`Interval changed to: ${interval}`)
  console.log('⏰ Interval changed:', interval)
}

// Helper functions
const addEvent = (message) => {
  events.value.unshift({
    time: new Date().toLocaleTimeString(),
    message
  })
  // Keep only last 50 events
  if (events.value.length > 50) {
    events.value = events.value.slice(0, 50)
  }
}

const addError = (message) => {
  errors.value.unshift({
    time: new Date().toLocaleTimeString(),
    message
  })
  // Keep only last 20 errors
  if (errors.value.length > 20) {
    errors.value = errors.value.slice(0, 20)
  }
}

const refreshChart = () => {
  chartKey.value++
  addEvent('Chart refreshed manually')
}

// Initialize
onMounted(() => {
  addEvent('Chart test page loaded')
  
  // Check TradingView availability after a short delay
  setTimeout(() => {
    if (isTradingViewLoaded.value) {
      addEvent('TradingView library detected')
    } else {
      addError('TradingView library not found')
    }
    
    if (isDatafeedsAvailable.value) {
      addEvent('TradingView Datafeeds detected')
    } else {
      addError('TradingView Datafeeds not found')
    }
  }, 1000)
})

// Head configuration
useHead({
  title: 'TradingView Chart Test - CIRX',
  meta: [
    { 
      name: 'description', 
      content: 'Test page for TradingView Charting Library integration with CIRX trading platform' 
    }
  ]
})
</script>

<style scoped>
/* Additional styling for test page */
.h-96 {
  height: 24rem;
}

.h-80 {
  height: 20rem;
}

/* Scrollbar styling for logs */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: rgba(55, 65, 81, 0.1);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.5);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.7);
}
</style>