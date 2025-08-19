<template>
  <div class="tradingview-chart-wrapper relative">
    <!-- Chart container -->
    <div
      ref="chartContainer"
      :id="containerId"
      class="tradingview-chart-container"
      :style="{ height: height, width: '100%' }"
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
        class="absolute inset-0 flex items-center justify-center bg-gray-900 text-center z-10"
      >
        <div class="text-red-400">
          <svg class="w-16 h-16 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          <p class="text-sm font-medium">Chart Loading Failed</p>
          <p class="text-xs text-gray-400 mt-2">{{ error }}</p>
          <button
            @click="initChart"
            class="mt-4 px-4 py-2 bg-circular-primary text-gray-900 rounded-lg text-sm hover:bg-circular-primary-hover transition-colors"
          >
            Retry
          </button>
        </div>
      </div>
    </div>

    <!-- Chart controls -->
    <div
      v-if="showControls && !isLoading && !error"
      class="absolute top-4 right-4 flex gap-2 z-20"
    >
      <button
        @click="toggleFullscreen"
        class="p-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg transition-colors"
        title="Toggle Fullscreen"
      >
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 11-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd" />
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { createCIRXDatafeed } from '~/composables/useTradingViewDatafeed'

// Props
const props = defineProps({
  symbol: {
    type: String,
    default: 'USDT/CIRX' // Default to USDT/CIRX trading pair
  },
  interval: {
    type: String,
    default: '1D',
    validator: (value) => ['1', '3', '5', '15', '30', '60', '240', '1D', '1W', '1M'].includes(value)
  },
  height: {
    type: String,
    default: '500px'
  },
  theme: {
    type: String,
    default: 'dark',
    validator: (value) => ['light', 'dark'].includes(value)
  },
  showControls: {
    type: Boolean,
    default: true
  },
  useCustomDatafeed: {
    type: Boolean,
    default: true
  },
  datafeedUrl: {
    type: String,
    default: 'https://demo-feed-data.tradingview.com'
  },
  enableTrading: {
    type: Boolean,
    default: false
  },
  autosize: {
    type: Boolean,
    default: true
  }
})

// Emits
const emit = defineEmits(['ready', 'error', 'symbolChange', 'intervalChange'])

// State
const chartContainer = ref(null)
const isLoading = ref(true)
const error = ref(null)
const chartWidget = ref(null)
const containerId = ref(`tv_chart_container_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`)

// Chart initialization
const initChart = async () => {
  if (!chartContainer.value) {
    console.error('Chart container not available')
    return
  }

  try {
    isLoading.value = true
    error.value = null

    // Wait for TradingView to be available
    await waitForTradingView()

    // Clear any existing chart
    if (chartWidget.value) {
      try {
        chartWidget.value.remove()
      } catch (e) {
        console.warn('Error removing existing chart:', e)
      }
      chartWidget.value = null
    }

    // Wait for next tick to ensure DOM is ready
    await nextTick()

    // Create chart widget
    const datafeed = props.useCustomDatafeed 
      ? createCIRXDatafeed() 
      : new window.Datafeeds.UDFCompatibleDatafeed(props.datafeedUrl)

    const widgetOptions = {
      symbol: props.symbol,
      interval: props.interval,
      container: containerId.value,
      datafeed: datafeed,
      library_path: '/', // Using CDN, so library path is not needed
      
      // Appearance
      theme: props.theme === 'dark' ? 'Dark' : 'Light',
      autosize: props.autosize,
      fullscreen: false,
      
      // Localization
      locale: 'en',
      
      // Features
      disabled_features: [
        'use_localstorage_for_settings',
        'volume_force_overlay',
        'create_volume_indicator_by_default'
      ],
      enabled_features: [
        'study_templates'
      ],
      
      // Overrides for CIRX theming
      overrides: {
        'paneProperties.background': props.theme === 'dark' ? '#1f2937' : '#ffffff',
        'paneProperties.vertGridProperties.color': props.theme === 'dark' ? '#374151' : '#e5e7eb',
        'paneProperties.horzGridProperties.color': props.theme === 'dark' ? '#374151' : '#e5e7eb',
        'symbolWatermarkProperties.transparency': 90,
        'scalesProperties.textColor': props.theme === 'dark' ? '#d1d5db' : '#374151',
        'mainSeriesProperties.candleStyle.upColor': '#10b981', // Green for up candles
        'mainSeriesProperties.candleStyle.downColor': '#ef4444', // Red for down candles
        'mainSeriesProperties.candleStyle.drawWick': true,
        'mainSeriesProperties.candleStyle.drawBorder': true,
        'mainSeriesProperties.candleStyle.borderColor': '#6b7280',
        'mainSeriesProperties.candleStyle.borderUpColor': '#10b981',
        'mainSeriesProperties.candleStyle.borderDownColor': '#ef4444',
        'mainSeriesProperties.candleStyle.wickUpColor': '#10b981',
        'mainSeriesProperties.candleStyle.wickDownColor': '#ef4444'
      },

      // Loading screen
      loading_screen: {
        backgroundColor: props.theme === 'dark' ? '#1f2937' : '#ffffff',
        foregroundColor: props.theme === 'dark' ? '#d1d5db' : '#374151'
      },

      // Toolbar
      toolbar_bg: props.theme === 'dark' ? '#111827' : '#f9fafb',
      
      // Custom CSS (if needed)
      custom_css_url: undefined, // Can add custom CSS file path if needed
      
      // Trading features (if enabled)
      ...(props.enableTrading && {
        enabled_features: [
          ...['study_templates'],
          'trading_notifications'
        ]
      }),
      
      // Debug mode (disable in production)
      debug: import.meta.env.MODE === 'development'
    }

    console.log('🚀 Initializing TradingView chart with options:', widgetOptions)
    
    chartWidget.value = new window.TradingView.widget(widgetOptions)

    // Set up event handlers
    chartWidget.value.onChartReady(() => {
      console.log('✅ TradingView chart ready')
      isLoading.value = false
      emit('ready', chartWidget.value)
    })

    // Listen for symbol changes
    chartWidget.value.subscribe('symbol', (symbolInfo) => {
      emit('symbolChange', symbolInfo)
    })

    // Listen for interval changes  
    chartWidget.value.subscribe('interval', (interval) => {
      emit('intervalChange', interval)
    })

  } catch (err) {
    console.error('❌ Chart initialization failed:', err)
    error.value = err.message || 'Failed to initialize chart'
    isLoading.value = false
    emit('error', err)
  }
}

// Wait for TradingView library to be available
const waitForTradingView = (maxRetries = 30, retryDelay = 100) => {
  return new Promise((resolve, reject) => {
    let retries = 0

    const checkTradingView = () => {
      if (window.TradingView && window.Datafeeds) {
        resolve()
        return
      }

      retries++
      if (retries >= maxRetries) {
        reject(new Error('TradingView library failed to load'))
        return
      }

      setTimeout(checkTradingView, retryDelay)
    }

    checkTradingView()
  })
}

// Toggle fullscreen
const toggleFullscreen = () => {
  if (chartWidget.value) {
    try {
      chartWidget.value.fullscreen()
    } catch (e) {
      console.warn('Fullscreen toggle failed:', e)
    }
  }
}

// Update chart symbol
const updateSymbol = (newSymbol) => {
  if (chartWidget.value && newSymbol !== props.symbol) {
    try {
      chartWidget.value.setSymbol(newSymbol, props.interval)
    } catch (e) {
      console.error('Failed to update symbol:', e)
    }
  }
}

// Update chart interval
const updateInterval = (newInterval) => {
  if (chartWidget.value && newInterval !== props.interval) {
    try {
      chartWidget.value.setResolution(newInterval)
    } catch (e) {
      console.error('Failed to update interval:', e)
    }
  }
}

// Watch for prop changes
watch(() => props.symbol, updateSymbol)
watch(() => props.interval, updateInterval)
watch(() => props.theme, () => {
  // Theme changes require chart recreation
  initChart()
})

// Lifecycle
onMounted(() => {
  nextTick(() => {
    initChart()
  })
})

onBeforeUnmount(() => {
  if (chartWidget.value) {
    try {
      chartWidget.value.remove()
    } catch (e) {
      console.warn('Error removing chart widget:', e)
    }
    chartWidget.value = null
  }
})

// Expose methods for parent component
defineExpose({
  chart: chartWidget,
  updateSymbol,
  updateInterval,
  toggleFullscreen,
  refresh: initChart
})
</script>

<style scoped>
.tradingview-chart-wrapper {
  position: relative;
  background: theme('colors.gray.900');
  border-radius: 0.5rem;
  overflow: hidden;
}

.tradingview-chart-container {
  position: relative;
  min-height: 300px;
}

/* Dark theme overrides */
:deep(.tv-chart) {
  border-radius: 0.5rem;
}

/* Ensure chart fills container */
:deep(iframe) {
  border-radius: 0.5rem;
}
</style>