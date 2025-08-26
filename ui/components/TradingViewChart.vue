<template>
  <div class="tradingview-chart-wrapper relative">
    <!-- TradingView Chart using nuxt-tradingview module -->
    <Chart 
      :options="chartOptions"
      :style="{ height: height, width: '100%' }"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'

// Props
const props = defineProps({
  symbol: {
    type: String,
    default: 'BTCUSDT' // Use a real trading pair that TradingView supports
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
  autosize: {
    type: Boolean,
    default: true
  }
})

// Chart options computed property
const chartOptions = computed(() => ({
  // Symbol and interval
  symbol: props.symbol,
  interval: props.interval,
  
  // Appearance
  theme: props.theme,
  autosize: props.autosize,
  
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
    backgroundColor: props.theme === 'dark' ? '#1f2937' : '#ffffff' 
  },
  
  // Toolbar styling
  toolbar_bg: props.theme === 'dark' ? '#111827' : '#f9fafb'
}))
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